<?php
/*
 * Created on   : Mon Sep 14 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : StatementValidator.php
 * License      : Apache-2.0
 * License Uri  : https://www.apache.org/licenses/LICENSE-2.0
 */

declare(strict_types=1);

namespace ELearningToolkit\XApi;

use CommonToolkit\Helper\Data\{EmailHelper, StringHelper, WebLinkHelper};

/**
 * Prüft ein Statement nach xAPI 1.0.3 (IEEE 9274.1.1).
 *
 * Geprüft wird, was ein LRS nach der Spezifikation ablehnen muss: fehlende
 * Pflichtangaben, unbekannte Eigenschaften, falsche Typen, ungültige
 * Kennungen, Zeitangaben und Wertebereiche. Das Statement kommt als
 * dekodiertes JSON mit assoziativen Arrays.
 */
final class StatementValidator {
    public const UNKNOWN_PROPERTY = 'unknown_property';
    public const MISSING_PROPERTY = 'missing_property';
    public const INVALID_TYPE = 'invalid_type';
    public const INVALID_UUID = 'invalid_uuid';
    public const INVALID_IRI = 'invalid_iri';
    public const INVALID_AGENT = 'invalid_agent';
    public const INVALID_GROUP = 'invalid_group';
    public const INVALID_OBJECT = 'invalid_object';
    public const INVALID_SCORE = 'invalid_score';
    public const INVALID_DURATION = 'invalid_duration';
    public const INVALID_TIMESTAMP = 'invalid_timestamp';
    public const INVALID_LANGUAGE_MAP = 'invalid_language_map';
    public const INVALID_VERSION = 'invalid_version';
    public const INVALID_CONTEXT = 'invalid_context';

    private const STATEMENT_PROPERTIES = ['id', 'actor', 'verb', 'object', 'result', 'context', 'timestamp', 'stored', 'authority', 'version', 'attachments'];
    private const SUB_STATEMENT_PROPERTIES = ['objectType', 'actor', 'verb', 'object', 'result', 'context', 'timestamp', 'attachments'];
    private const AGENT_PROPERTIES = ['objectType', 'name', 'mbox', 'mbox_sha1sum', 'openid', 'account'];
    private const GROUP_PROPERTIES = ['objectType', 'name', 'member', 'mbox', 'mbox_sha1sum', 'openid', 'account'];
    private const IFI_PROPERTIES = ['mbox', 'mbox_sha1sum', 'openid', 'account'];
    private const ACTIVITY_DEFINITION_PROPERTIES = ['name', 'description', 'type', 'moreInfo', 'extensions', 'interactionType', 'correctResponsesPattern', 'choices', 'scale', 'source', 'target', 'steps'];
    private const CONTEXT_ACTIVITY_KINDS = ['parent', 'grouping', 'category', 'other'];

    /**
     * @param  array<mixed>  $statement
     * @return list<Violation>
     */
    public function violations(array $statement): array {
        $out = [];

        if ($statement !== [] && array_is_list($statement)) {
            return [new Violation(self::INVALID_TYPE, '')];
        }

        $this->allowOnly($statement, self::STATEMENT_PROPERTIES, '', $out);

        if (array_key_exists('id', $statement)) {
            $this->uuid($statement['id'], 'id', $out);
        }

        $this->statementBody($statement, '', false, $out);

        if (array_key_exists('stored', $statement)) {
            $this->timestamp($statement['stored'], 'stored', $out);
        }
        if (array_key_exists('authority', $statement)) {
            $this->actor($statement['authority'], 'authority', $out);
        }
        if (array_key_exists('version', $statement) && (!is_string($statement['version']) || preg_match('/^1\.0(\.\d+)?$/D', $statement['version']) !== 1)) {
            $out[] = new Violation(self::INVALID_VERSION, 'version');
        }

        return $out;
    }

    /**
     * @param  array<mixed>  $statement
     *
     * @throws StatementException beim ersten Verstoß
     */
    public function assertValid(array $statement): void {
        $violations = $this->violations($statement);

        if ($violations !== []) {
            throw StatementException::fromViolation($violations[0]);
        }
    }

    /**
     * Gemeinsamer Teil von Statement und SubStatement.
     *
     * @param  array<mixed>  $statement
     * @param  list<Violation>  $out
     *
     * @param-out list<Violation> $out
     */
    private function statementBody(array $statement, string $prefix, bool $isSubStatement, array &$out): void {
        foreach (['actor', 'verb', 'object'] as $required) {
            if (!array_key_exists($required, $statement)) {
                $out[] = new Violation(self::MISSING_PROPERTY, self::path($prefix, $required));
            }
        }

        if (array_key_exists('actor', $statement)) {
            $this->actor($statement['actor'], self::path($prefix, 'actor'), $out);
        }
        if (array_key_exists('verb', $statement)) {
            $this->verb($statement['verb'], self::path($prefix, 'verb'), $out);
        }

        $objectType = null;
        if (array_key_exists('object', $statement)) {
            $objectType = $this->object($statement['object'], self::path($prefix, 'object'), $isSubStatement, $out);
        }
        if (array_key_exists('result', $statement)) {
            $this->result($statement['result'], self::path($prefix, 'result'), $out);
        }
        if (array_key_exists('context', $statement)) {
            $this->context($statement['context'], self::path($prefix, 'context'), $objectType, $out);
        }
        if (array_key_exists('timestamp', $statement)) {
            $this->timestamp($statement['timestamp'], self::path($prefix, 'timestamp'), $out);
        }
        if (array_key_exists('attachments', $statement)) {
            $this->attachments($statement['attachments'], self::path($prefix, 'attachments'), $out);
        }
    }

    /**
     * @param  list<Violation>  $out
     *
     * @param-out list<Violation> $out
     */
    private function actor(mixed $actor, string $path, array &$out): void {
        if (!self::isObject($actor)) {
            $out[] = new Violation(self::INVALID_TYPE, $path);

            return;
        }

        match ($actor['objectType'] ?? 'Agent') {
            'Agent' => $this->agent($actor, $path, $out),
            'Group' => $this->group($actor, $path, $out),
            default => $out[] = new Violation(self::INVALID_AGENT, self::path($path, 'objectType')),
        };
    }

    /**
     * Ein Agent hat genau einen Identifikator (IFI).
     *
     * @param  array<mixed>  $agent
     * @param  list<Violation>  $out
     *
     * @param-out list<Violation> $out
     */
    private function agent(array $agent, string $path, array &$out): void {
        $this->allowOnly($agent, self::AGENT_PROPERTIES, $path, $out);

        if (self::ifiCount($agent) !== 1) {
            $out[] = new Violation(self::INVALID_AGENT, $path);
        }

        $this->ifis($agent, $path, $out);
        $this->optionalString($agent, 'name', $path, $out);
    }

    /**
     * Eine anonyme Gruppe braucht Mitglieder, eine benannte genau einen Identifikator.
     *
     * @param  array<mixed>  $group
     * @param  list<Violation>  $out
     *
     * @param-out list<Violation> $out
     */
    private function group(array $group, string $path, array &$out): void {
        $this->allowOnly($group, self::GROUP_PROPERTIES, $path, $out);

        $ifis = self::ifiCount($group);
        $hasMembers = array_key_exists('member', $group);

        if ($ifis > 1 || ($ifis === 0 && !$hasMembers)) {
            $out[] = new Violation(self::INVALID_GROUP, $path);
        }

        if ($hasMembers) {
            $members = $group['member'];

            if (!is_array($members) || !array_is_list($members) || ($ifis === 0 && $members === [])) {
                $out[] = new Violation(self::INVALID_GROUP, self::path($path, 'member'));
            } else {
                foreach ($members as $index => $member) {
                    $memberPath = self::path($path, 'member') . '[' . $index . ']';

                    // Mitglieder sind Agents, nie wieder Gruppen.
                    if (!self::isObject($member) || ($member['objectType'] ?? 'Agent') !== 'Agent') {
                        $out[] = new Violation(self::INVALID_AGENT, $memberPath);

                        continue;
                    }

                    $this->agent($member, $memberPath, $out);
                }
            }
        }

        $this->ifis($group, $path, $out);
        $this->optionalString($group, 'name', $path, $out);
    }

    /**
     * @param  array<mixed>  $actor
     * @param  list<Violation>  $out
     *
     * @param-out list<Violation> $out
     */
    private function ifis(array $actor, string $path, array &$out): void {
        if (array_key_exists('mbox', $actor)) {
            $mbox = $actor['mbox'];

            if (!is_string($mbox) || !str_starts_with($mbox, 'mailto:') || !EmailHelper::isEmail(substr($mbox, 7))) {
                $out[] = new Violation(self::INVALID_AGENT, self::path($path, 'mbox'));
            }
        }

        if (array_key_exists('mbox_sha1sum', $actor) && (!is_string($actor['mbox_sha1sum']) || preg_match('/^[0-9a-f]{40}$/iD', $actor['mbox_sha1sum']) !== 1)) {
            $out[] = new Violation(self::INVALID_AGENT, self::path($path, 'mbox_sha1sum'));
        }

        if (array_key_exists('openid', $actor)) {
            $this->iri($actor['openid'], self::path($path, 'openid'), $out);
        }

        if (array_key_exists('account', $actor)) {
            $account = $actor['account'];
            $accountPath = self::path($path, 'account');

            if (!self::isObject($account)) {
                $out[] = new Violation(self::INVALID_TYPE, $accountPath);

                return;
            }

            $this->allowOnly($account, ['homePage', 'name'], $accountPath, $out);

            if (!array_key_exists('homePage', $account)) {
                $out[] = new Violation(self::MISSING_PROPERTY, self::path($accountPath, 'homePage'));
            } else {
                $this->iri($account['homePage'], self::path($accountPath, 'homePage'), $out);
            }

            if (!isset($account['name']) || !is_string($account['name']) || $account['name'] === '') {
                $out[] = new Violation(self::MISSING_PROPERTY, self::path($accountPath, 'name'));
            }
        }
    }

    /**
     * @param  list<Violation>  $out
     *
     * @param-out list<Violation> $out
     */
    private function verb(mixed $verb, string $path, array &$out): void {
        if (!self::isObject($verb)) {
            $out[] = new Violation(self::INVALID_TYPE, $path);

            return;
        }

        $this->allowOnly($verb, ['id', 'display'], $path, $out);

        if (!array_key_exists('id', $verb)) {
            $out[] = new Violation(self::MISSING_PROPERTY, self::path($path, 'id'));
        } else {
            $this->iri($verb['id'], self::path($path, 'id'), $out);
        }

        if (array_key_exists('display', $verb)) {
            $this->languageMap($verb['display'], self::path($path, 'display'), $out);
        }
    }

    /**
     * @param  list<Violation>  $out
     *
     * @param-out list<Violation> $out
     *
     * @return string|null Objektart, sofern erkennbar
     */
    private function object(mixed $object, string $path, bool $insideSubStatement, array &$out): ?string {
        if (!self::isObject($object)) {
            $out[] = new Violation(self::INVALID_TYPE, $path);

            return null;
        }

        $type = $object['objectType'] ?? 'Activity';

        switch ($type) {
            case 'Activity':
                $this->activity($object, $path, $out);
                break;
            case 'Agent':
                $this->agent($object, $path, $out);
                break;
            case 'Group':
                $this->group($object, $path, $out);
                break;
            case 'StatementRef':
                $this->allowOnly($object, ['objectType', 'id'], $path, $out);
                $this->uuid($object['id'] ?? null, self::path($path, 'id'), $out);
                break;
            case 'SubStatement':
                if ($insideSubStatement) {
                    // Ein SubStatement darf kein weiteres enthalten.
                    $out[] = new Violation(self::INVALID_OBJECT, self::path($path, 'objectType'));
                    break;
                }
                $this->allowOnly($object, self::SUB_STATEMENT_PROPERTIES, $path, $out);
                $this->statementBody($object, $path, true, $out);
                break;
            default:
                $out[] = new Violation(self::INVALID_OBJECT, self::path($path, 'objectType'));

                return null;
        }

        return is_string($type) ? $type : null;
    }

    /**
     * @param  array<mixed>  $activity
     * @param  list<Violation>  $out
     *
     * @param-out list<Violation> $out
     */
    private function activity(array $activity, string $path, array &$out): void {
        $this->allowOnly($activity, ['objectType', 'id', 'definition'], $path, $out);

        if (!array_key_exists('id', $activity)) {
            $out[] = new Violation(self::MISSING_PROPERTY, self::path($path, 'id'));
        } else {
            $this->iri($activity['id'], self::path($path, 'id'), $out);
        }

        if (!array_key_exists('definition', $activity)) {
            return;
        }

        $definition = $activity['definition'];
        $definitionPath = self::path($path, 'definition');

        if (!self::isObject($definition)) {
            $out[] = new Violation(self::INVALID_TYPE, $definitionPath);

            return;
        }

        $this->allowOnly($definition, self::ACTIVITY_DEFINITION_PROPERTIES, $definitionPath, $out);

        foreach (['name', 'description'] as $map) {
            if (array_key_exists($map, $definition)) {
                $this->languageMap($definition[$map], self::path($definitionPath, $map), $out);
            }
        }
        foreach (['type', 'moreInfo'] as $iri) {
            if (array_key_exists($iri, $definition)) {
                $this->iri($definition[$iri], self::path($definitionPath, $iri), $out);
            }
        }
        if (array_key_exists('extensions', $definition)) {
            $this->extensions($definition['extensions'], self::path($definitionPath, 'extensions'), $out);
        }
    }

    /**
     * @param  list<Violation>  $out
     *
     * @param-out list<Violation> $out
     */
    private function result(mixed $result, string $path, array &$out): void {
        if (!self::isObject($result)) {
            $out[] = new Violation(self::INVALID_TYPE, $path);

            return;
        }

        $this->allowOnly($result, ['score', 'success', 'completion', 'response', 'duration', 'extensions'], $path, $out);

        foreach (['success', 'completion'] as $flag) {
            if (array_key_exists($flag, $result) && !is_bool($result[$flag])) {
                $out[] = new Violation(self::INVALID_TYPE, self::path($path, $flag));
            }
        }

        $this->optionalString($result, 'response', $path, $out);

        if (array_key_exists('duration', $result) && (!is_string($result['duration']) || !self::isDuration($result['duration']))) {
            $out[] = new Violation(self::INVALID_DURATION, self::path($path, 'duration'));
        }

        if (array_key_exists('extensions', $result)) {
            $this->extensions($result['extensions'], self::path($path, 'extensions'), $out);
        }

        if (array_key_exists('score', $result)) {
            $this->score($result['score'], self::path($path, 'score'), $out);
        }
    }

    /**
     * @param  list<Violation>  $out
     *
     * @param-out list<Violation> $out
     */
    private function score(mixed $score, string $path, array &$out): void {
        if (!self::isObject($score)) {
            $out[] = new Violation(self::INVALID_TYPE, $path);

            return;
        }

        $this->allowOnly($score, ['scaled', 'raw', 'min', 'max'], $path, $out);

        foreach (['scaled', 'raw', 'min', 'max'] as $key) {
            if (array_key_exists($key, $score) && !is_int($score[$key]) && !is_float($score[$key])) {
                $out[] = new Violation(self::INVALID_SCORE, self::path($path, $key));

                return;
            }
        }

        $scaled = $score['scaled'] ?? null;
        if ($scaled !== null && ($scaled < -1 || $scaled > 1)) {
            $out[] = new Violation(self::INVALID_SCORE, self::path($path, 'scaled'));
        }

        $min = $score['min'] ?? null;
        $max = $score['max'] ?? null;
        $raw = $score['raw'] ?? null;

        if ($min !== null && $max !== null && $min >= $max) {
            $out[] = new Violation(self::INVALID_SCORE, self::path($path, 'min'));
        }
        if ($raw !== null && (($min !== null && $raw < $min) || ($max !== null && $raw > $max))) {
            $out[] = new Violation(self::INVALID_SCORE, self::path($path, 'raw'));
        }
    }

    /**
     * @param  list<Violation>  $out
     *
     * @param-out list<Violation> $out
     */
    private function context(mixed $context, string $path, ?string $objectType, array &$out): void {
        if (!self::isObject($context)) {
            $out[] = new Violation(self::INVALID_TYPE, $path);

            return;
        }

        $this->allowOnly($context, ['registration', 'instructor', 'team', 'contextActivities', 'revision', 'platform', 'language', 'statement', 'extensions'], $path, $out);

        if (array_key_exists('registration', $context)) {
            $this->uuid($context['registration'], self::path($path, 'registration'), $out);
        }
        if (array_key_exists('instructor', $context)) {
            $this->actor($context['instructor'], self::path($path, 'instructor'), $out);
        }
        if (array_key_exists('team', $context)) {
            $team = $context['team'];

            if (!self::isObject($team) || ($team['objectType'] ?? null) !== 'Group') {
                $out[] = new Violation(self::INVALID_GROUP, self::path($path, 'team'));
            } else {
                $this->group($team, self::path($path, 'team'), $out);
            }
        }

        // `revision` und `platform` beschreiben eine Aktivität — bei anderen Objekten verboten.
        foreach (['revision', 'platform'] as $key) {
            if (!array_key_exists($key, $context)) {
                continue;
            }
            if (!is_string($context[$key])) {
                $out[] = new Violation(self::INVALID_TYPE, self::path($path, $key));
            } elseif ($objectType !== null && $objectType !== 'Activity') {
                $out[] = new Violation(self::INVALID_CONTEXT, self::path($path, $key));
            }
        }

        if (array_key_exists('language', $context) && (!is_string($context['language']) || !self::isLanguageTag($context['language']))) {
            $out[] = new Violation(self::INVALID_LANGUAGE_MAP, self::path($path, 'language'));
        }

        if (array_key_exists('statement', $context)) {
            $statement = $context['statement'];
            $statementPath = self::path($path, 'statement');

            if (!self::isObject($statement) || ($statement['objectType'] ?? null) !== 'StatementRef') {
                $out[] = new Violation(self::INVALID_OBJECT, $statementPath);
            } else {
                $this->allowOnly($statement, ['objectType', 'id'], $statementPath, $out);
                $this->uuid($statement['id'] ?? null, self::path($statementPath, 'id'), $out);
            }
        }

        if (array_key_exists('contextActivities', $context)) {
            $this->contextActivities($context['contextActivities'], self::path($path, 'contextActivities'), $out);
        }

        if (array_key_exists('extensions', $context)) {
            $this->extensions($context['extensions'], self::path($path, 'extensions'), $out);
        }
    }

    /**
     * Jede Art ist eine Aktivität oder eine Liste von Aktivitäten.
     *
     * @param  list<Violation>  $out
     *
     * @param-out list<Violation> $out
     */
    private function contextActivities(mixed $activities, string $path, array &$out): void {
        if (!self::isObject($activities)) {
            $out[] = new Violation(self::INVALID_TYPE, $path);

            return;
        }

        $this->allowOnly($activities, self::CONTEXT_ACTIVITY_KINDS, $path, $out);

        foreach (self::CONTEXT_ACTIVITY_KINDS as $kind) {
            if (!array_key_exists($kind, $activities)) {
                continue;
            }

            $value = $activities[$kind];
            $list = is_array($value) && array_is_list($value) && $value !== [] ? $value : [$value];

            foreach ($list as $index => $activity) {
                $activityPath = self::path($path, $kind) . (count($list) > 1 || is_array($value) && array_is_list($value) ? '[' . $index . ']' : '');

                if (!self::isObject($activity) || ($activity['objectType'] ?? 'Activity') !== 'Activity') {
                    $out[] = new Violation(self::INVALID_OBJECT, $activityPath);

                    continue;
                }

                $this->activity($activity, $activityPath, $out);
            }
        }
    }

    /**
     * @param  list<Violation>  $out
     *
     * @param-out list<Violation> $out
     */
    private function attachments(mixed $attachments, string $path, array &$out): void {
        if (!is_array($attachments) || !array_is_list($attachments)) {
            $out[] = new Violation(self::INVALID_TYPE, $path);

            return;
        }

        foreach ($attachments as $index => $attachment) {
            $attachmentPath = $path . '[' . $index . ']';

            if (!self::isObject($attachment)) {
                $out[] = new Violation(self::INVALID_TYPE, $attachmentPath);

                continue;
            }

            $this->allowOnly($attachment, ['usageType', 'display', 'description', 'contentType', 'length', 'sha2', 'fileUrl'], $attachmentPath, $out);

            foreach (['usageType', 'display', 'contentType', 'length', 'sha2'] as $required) {
                if (!array_key_exists($required, $attachment)) {
                    $out[] = new Violation(self::MISSING_PROPERTY, self::path($attachmentPath, $required));
                }
            }

            if (array_key_exists('usageType', $attachment)) {
                $this->iri($attachment['usageType'], self::path($attachmentPath, 'usageType'), $out);
            }
            if (array_key_exists('fileUrl', $attachment)) {
                $this->iri($attachment['fileUrl'], self::path($attachmentPath, 'fileUrl'), $out);
            }
            foreach (['display', 'description'] as $map) {
                if (array_key_exists($map, $attachment)) {
                    $this->languageMap($attachment[$map], self::path($attachmentPath, $map), $out);
                }
            }
            if (array_key_exists('length', $attachment) && (!is_int($attachment['length']) || $attachment['length'] < 0)) {
                $out[] = new Violation(self::INVALID_TYPE, self::path($attachmentPath, 'length'));
            }
            foreach (['contentType', 'sha2'] as $string) {
                if (array_key_exists($string, $attachment) && !is_string($attachment[$string])) {
                    $out[] = new Violation(self::INVALID_TYPE, self::path($attachmentPath, $string));
                }
            }
        }
    }

    /**
     * @param  list<Violation>  $out
     *
     * @param-out list<Violation> $out
     */
    private function timestamp(mixed $value, string $path, array &$out): void {
        if (!is_string($value) || !self::isTimestamp($value)) {
            $out[] = new Violation(self::INVALID_TIMESTAMP, $path);
        }
    }

    /**
     * @param  list<Violation>  $out
     *
     * @param-out list<Violation> $out
     */
    private function uuid(mixed $value, string $path, array &$out): void {
        if (!is_string($value) || !StringHelper::isUuid($value)) {
            $out[] = new Violation(self::INVALID_UUID, $path);
        }
    }

    /**
     * @param  list<Violation>  $out
     *
     * @param-out list<Violation> $out
     */
    private function iri(mixed $value, string $path, array &$out): void {
        if (!is_string($value) || !WebLinkHelper::isAbsoluteIri($value)) {
            $out[] = new Violation(self::INVALID_IRI, $path);
        }
    }

    /**
     * @param  list<Violation>  $out
     *
     * @param-out list<Violation> $out
     */
    private function languageMap(mixed $map, string $path, array &$out): void {
        if (!self::isObject($map)) {
            $out[] = new Violation(self::INVALID_LANGUAGE_MAP, $path);

            return;
        }

        foreach ($map as $tag => $text) {
            if (!is_string($tag) || !self::isLanguageTag($tag) || !is_string($text)) {
                $out[] = new Violation(self::INVALID_LANGUAGE_MAP, self::path($path, (string) $tag));
            }
        }
    }

    /**
     * @param  list<Violation>  $out
     *
     * @param-out list<Violation> $out
     */
    private function extensions(mixed $extensions, string $path, array &$out): void {
        if (!self::isObject($extensions)) {
            $out[] = new Violation(self::INVALID_TYPE, $path);

            return;
        }

        foreach (array_keys($extensions) as $key) {
            if (!is_string($key) || !WebLinkHelper::isAbsoluteIri($key)) {
                $out[] = new Violation(self::INVALID_IRI, self::path($path, (string) $key));
            }
        }
    }

    /**
     * @param  array<mixed>  $object
     * @param  list<Violation>  $out
     *
     * @param-out list<Violation> $out
     */
    private function optionalString(array $object, string $key, string $path, array &$out): void {
        if (array_key_exists($key, $object) && !is_string($object[$key])) {
            $out[] = new Violation(self::INVALID_TYPE, self::path($path, $key));
        }
    }

    /**
     * @param  array<mixed>  $object
     * @param  list<string>  $allowed
     * @param  list<Violation>  $out
     *
     * @param-out list<Violation> $out
     */
    private function allowOnly(array $object, array $allowed, string $path, array &$out): void {
        foreach (array_keys($object) as $key) {
            if (!in_array($key, $allowed, true)) {
                $out[] = new Violation(self::UNKNOWN_PROPERTY, self::path($path, (string) $key));
            }
        }
    }

    /** @param array<mixed> $actor */
    private static function ifiCount(array $actor): int {
        return count(array_intersect(array_keys($actor), self::IFI_PROPERTIES));
    }

    /**
     * Ein JSON-Objekt als assoziatives Array. Ein leeres Array kann ein leeres
     * Objekt sein und gilt deshalb als Objekt.
     *
     * @phpstan-assert-if-true array<mixed> $value
     */
    private static function isObject(mixed $value): bool {
        return is_array($value) && ($value === [] || !array_is_list($value));
    }

    /** ISO-8601-Dauer, Sekunden mit Bruchteil — so senden es Autorenwerkzeuge. */
    public static function isDuration(string $value): bool {
        return preg_match('/^P(?!$)(\d+Y)?(\d+M)?(\d+W)?(\d+D)?(T(?=\d)(\d+H)?(\d+M)?(\d+(\.\d+)?S)?)?$/D', $value) === 1;
    }

    /**
     * ISO-8601-Zeitpunkt mit Datum und Uhrzeit.
     *
     * `-00:00` ist ausdrücklich verboten: Es bedeutet „Zeitzone unbekannt" und
     * macht den Zeitpunkt mehrdeutig.
     */
    public static function isTimestamp(string $value): bool {
        if (preg_match('/^(\d{4})-(\d{2})-(\d{2})T(\d{2}):(\d{2})(?::(\d{2})(?:\.\d+)?)?(Z|[+-]\d{2}(?::?\d{2})?)?$/D', $value, $m) !== 1) {
            return false;
        }

        $offset = $m[7] ?? '';
        if (in_array($offset, ['-00', '-0000', '-00:00'], true)) {
            return false;
        }

        $second = isset($m[6]) && $m[6] !== '' ? (int) $m[6] : 0;

        return checkdate((int) $m[2], (int) $m[3], (int) $m[1])
            && (int) $m[4] <= 23 && (int) $m[5] <= 59 && $second <= 60;
    }

    /** Sprachkennung nach BCP 47 in der üblichen Form (`de`, `de-DE`, `zh-Hant-TW`). */
    private static function isLanguageTag(string $tag): bool {
        return preg_match('/^[a-z]{2,8}(-[a-z0-9]{1,8})*$/iD', $tag) === 1;
    }

    private static function path(string $prefix, string $name): string {
        return $prefix === '' ? $name : $prefix . '.' . $name;
    }
}
