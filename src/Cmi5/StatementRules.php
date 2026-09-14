<?php
/*
 * Created on   : Mon Sep 14 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : StatementRules.php
 * License      : Apache-2.0
 * License Uri  : https://www.apache.org/licenses/LICENSE-2.0
 */

declare(strict_types=1);

namespace ELearningToolkit\Cmi5;

use ELearningToolkit\XApi\{Statement, Verbs, Violation};

/**
 * Prüft ein Statement, das eine AU in einer cmi5-Sitzung sendet (cmi5 7.1.3, 9.x).
 *
 * Voraussetzung ist ein gültiges xAPI-Statement ({@see Statement::fromArray()}).
 * Geprüft wird, was cmi5 zusätzlich verlangt: Kontextvorlage, Reihenfolge in der
 * Sitzung, Pflichtangaben je Verb, Startmodus und masteryScore.
 *
 * „cmi5 defined" Statements tragen die cmi5-Kategorie, „cmi5 allowed" nicht —
 * letztere sind nur zwischen `initialized` und `terminated` erlaubt und gehen in
 * keine Erfüllungsregel ein.
 */
final class StatementRules {
    public const SESSION_ENDED = 'session_ended';
    public const ACTOR_REQUIRES_ACCOUNT = 'actor_requires_account';
    public const REGISTRATION_MISMATCH = 'registration_mismatch';
    public const SESSION_MISMATCH = 'session_mismatch';
    public const PUBLISHER_MISMATCH = 'publisher_mismatch';
    public const ACTIVITY_MISMATCH = 'activity_mismatch';
    public const NOT_INITIALIZED = 'not_initialized';
    public const ALREADY_INITIALIZED = 'already_initialized';
    public const LMS_ONLY_VERB = 'lms_only_verb';
    public const UNDEFINED_VERB = 'undefined_verb';
    public const RESULT_REQUIRED = 'result_required';
    public const PROPERTY_NOT_ALLOWED = 'property_not_allowed';
    public const DURATION_REQUIRED = 'duration_required';
    public const MOVE_ON_CATEGORY_REQUIRED = 'move_on_category_required';
    public const MOVE_ON_CATEGORY_NOT_ALLOWED = 'move_on_category_not_allowed';
    public const NOT_IN_NORMAL_MODE = 'not_in_normal_mode';
    public const ALREADY_COMPLETED = 'already_completed';
    public const ALREADY_PASSED = 'already_passed';
    public const MASTERY_SCORE_VIOLATED = 'mastery_score_violated';
    public const MASTERY_SCORE_MISMATCH = 'mastery_score_mismatch';

    /** Verben, die nur das LMS verwenden darf. */
    private const LMS_VERBS = [Verbs::LAUNCHED, Verbs::ABANDONED, Verbs::WAIVED, Verbs::SATISFIED];

    /** Verben, die eine AU als „cmi5 defined" senden darf. */
    private const AU_VERBS = [Verbs::INITIALIZED, Verbs::COMPLETED, Verbs::PASSED, Verbs::FAILED, Verbs::TERMINATED];

    private const DURATION_VERBS = [Verbs::COMPLETED, Verbs::PASSED, Verbs::FAILED, Verbs::TERMINATED];

    private const SATISFACTION_VERBS = [Verbs::COMPLETED, Verbs::PASSED, Verbs::FAILED];

    private const CATEGORY_PATH = 'context.contextActivities.category';

    /** @return list<Violation> */
    public static function check(Statement $statement, Session $session): array {
        $raw = $statement->raw;
        $context = self::map($raw['context'] ?? null);
        $result = self::map($raw['result'] ?? null);
        $extensions = self::map($context['extensions'] ?? null);
        $activities = self::map($context['contextActivities'] ?? null);
        $categories = self::activityIds($activities['category'] ?? null);
        $hasMoveOnCategory = in_array(Cmi5::CATEGORY_MOVE_ON, $categories, true);
        $out = [];

        if ($session->hasEnded()) {
            return [new Violation(self::SESSION_ENDED, '')];
        }

        $actor = self::map($raw['actor'] ?? null);
        if (($actor['objectType'] ?? 'Agent') !== 'Agent' || !is_array($actor['account'] ?? null)) {
            $out[] = new Violation(self::ACTOR_REQUIRES_ACCOUNT, 'actor');
        }

        // Die Kontextvorlage gilt für jedes Statement der Sitzung (cmi5 10.2.1).
        if (strtolower((string) ($statement->registration ?? '')) !== strtolower($session->registration)) {
            $out[] = new Violation(self::REGISTRATION_MISMATCH, 'context.registration');
        }
        if (($extensions[Cmi5::EXTENSION_SESSION_ID] ?? null) !== $session->sessionId) {
            $out[] = new Violation(self::SESSION_MISMATCH, 'context.extensions');
        }
        if (!in_array($session->publisherId, self::activityIds($activities['grouping'] ?? null), true)) {
            $out[] = new Violation(self::PUBLISHER_MISMATCH, 'context.contextActivities.grouping');
        }

        if (!in_array(Cmi5::CATEGORY_CMI5, $categories, true)) {
            // „cmi5 allowed": nur innerhalb der Sitzung, nie mit moveOn-Kategorie.
            if (!$session->initialized) {
                $out[] = new Violation(self::NOT_INITIALIZED, 'verb.id');
            }
            if ($hasMoveOnCategory) {
                $out[] = new Violation(self::MOVE_ON_CATEGORY_NOT_ALLOWED, self::CATEGORY_PATH);
            }

            return $out;
        }

        $verb = $statement->verbId;

        if ($statement->objectId !== $session->activityId) {
            $out[] = new Violation(self::ACTIVITY_MISMATCH, 'object.id');
        }
        if (in_array($verb, self::LMS_VERBS, true)) {
            $out[] = new Violation(self::LMS_ONLY_VERB, 'verb.id');

            return $out;
        }
        if (!in_array($verb, self::AU_VERBS, true)) {
            $out[] = new Violation(self::UNDEFINED_VERB, 'verb.id');

            return $out;
        }

        if ($verb === Verbs::INITIALIZED) {
            if ($session->initialized) {
                $out[] = new Violation(self::ALREADY_INITIALIZED, 'verb.id');
            }
        } elseif (!$session->initialized) {
            $out[] = new Violation(self::NOT_INITIALIZED, 'verb.id');
        }

        // success: true bei passed, false bei failed, sonst verboten (cmi5 9.5.2).
        $expectedSuccess = match ($verb) {
            Verbs::PASSED => true,
            Verbs::FAILED => false,
            default => null,
        };
        if ($expectedSuccess === null) {
            if (array_key_exists('success', $result)) {
                $out[] = new Violation(self::PROPERTY_NOT_ALLOWED, 'result.success');
            }
        } elseif (($result['success'] ?? null) !== $expectedSuccess) {
            $out[] = new Violation(self::RESULT_REQUIRED, 'result.success');
        }

        // completion: true bei completed, sonst verboten (cmi5 9.5.3).
        if ($verb === Verbs::COMPLETED) {
            if (($result['completion'] ?? null) !== true) {
                $out[] = new Violation(self::RESULT_REQUIRED, 'result.completion');
            }
        } elseif (array_key_exists('completion', $result)) {
            $out[] = new Violation(self::PROPERTY_NOT_ALLOWED, 'result.completion');
        }

        // score nur bei passed und failed (cmi5 9.5.1).
        if (!in_array($verb, [Verbs::PASSED, Verbs::FAILED], true) && array_key_exists('score', $result)) {
            $out[] = new Violation(self::PROPERTY_NOT_ALLOWED, 'result.score');
        }

        if (in_array($verb, self::DURATION_VERBS, true) && !is_string($result['duration'] ?? null)) {
            $out[] = new Violation(self::DURATION_REQUIRED, 'result.duration');
        }

        // moveOn-Kategorie genau dann, wenn success oder completion vorkommen (cmi5 9.6.2.2).
        $carriesOutcome = array_key_exists('success', $result) || array_key_exists('completion', $result);
        if ($carriesOutcome && !$hasMoveOnCategory) {
            $out[] = new Violation(self::MOVE_ON_CATEGORY_REQUIRED, self::CATEGORY_PATH);
        }
        if (!$carriesOutcome && $hasMoveOnCategory) {
            $out[] = new Violation(self::MOVE_ON_CATEGORY_NOT_ALLOWED, self::CATEGORY_PATH);
        }

        if (in_array($verb, self::SATISFACTION_VERBS, true) && !$session->launchMode->recordsSatisfaction()) {
            $out[] = new Violation(self::NOT_IN_NORMAL_MODE, 'verb.id');
        }
        if ($verb === Verbs::COMPLETED && $session->completed) {
            $out[] = new Violation(self::ALREADY_COMPLETED, 'verb.id');
        }
        if ($verb === Verbs::PASSED && $session->passed) {
            $out[] = new Violation(self::ALREADY_PASSED, 'verb.id');
        }

        if ($session->masteryScore !== null && in_array($verb, [Verbs::PASSED, Verbs::FAILED], true)) {
            $scaled = $statement->scoreScaled;
            if ($scaled !== null && ($verb === Verbs::PASSED ? $scaled < $session->masteryScore : $scaled >= $session->masteryScore)) {
                $out[] = new Violation(self::MASTERY_SCORE_VIOLATED, 'result.score.scaled');
            }

            $declared = $extensions[Cmi5::EXTENSION_MASTERY_SCORE] ?? null;
            if ($declared !== null && (!is_numeric($declared) || abs((float) $declared - $session->masteryScore) > 0.00001)) {
                $out[] = new Violation(self::MASTERY_SCORE_MISMATCH, 'context.extensions');
            }
        }

        return $out;
    }

    /** Folgezustand nach einem angenommenen Statement. Vorher {@see self::check()}. */
    public static function apply(Statement $statement, Session $session): Session {
        $activities = self::map(self::map($statement->raw['context'] ?? null)['contextActivities'] ?? null);

        if (!in_array(Cmi5::CATEGORY_CMI5, self::activityIds($activities['category'] ?? null), true)) {
            return $session;
        }

        return match ($statement->verbId) {
            Verbs::INITIALIZED => $session->with(initialized: true),
            Verbs::COMPLETED => $session->with(completed: true),
            Verbs::PASSED => $session->with(passed: true),
            Verbs::TERMINATED => $session->with(terminated: true),
            default => $session,
        };
    }

    /** @return array<mixed> */
    private static function map(mixed $value): array {
        return is_array($value) ? $value : [];
    }

    /**
     * IDs einer Kontextaktivitäten-Art; xAPI erlaubt ein Objekt oder eine Liste.
     *
     * @return list<string>
     */
    private static function activityIds(mixed $value): array {
        if (!is_array($value)) {
            return [];
        }

        $list = array_is_list($value) ? $value : [$value];
        $ids = [];

        foreach ($list as $activity) {
            if (is_array($activity) && is_string($activity['id'] ?? null)) {
                $ids[] = $activity['id'];
            }
        }

        return $ids;
    }
}
