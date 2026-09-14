<?php
/*
 * Created on   : Mon Sep 14 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : LmsStatements.php
 * License      : Apache-2.0
 * License Uri  : https://www.apache.org/licenses/LICENSE-2.0
 */

declare(strict_types=1);

namespace ELearningToolkit\Cmi5;

use DateTimeImmutable;
use ELearningToolkit\XApi\Verbs;

/**
 * Statements, die das LMS selbst schreibt: `launched`, `abandoned`, `waived`,
 * `satisfied` (cmi5 9.3).
 *
 * Statement-ID und Zeitpunkt kommen vom Aufrufer — das Toolkit erzeugt keine
 * Zufallswerte und liest keine Uhr.
 */
final class LmsStatements {
    /**
     * Vor jedem Start (cmi5 9.3.1, 9.6.3).
     *
     * @param  array<string, mixed>  $actor
     * @param  string  $launchUrl  Voll qualifizierte Adresse der AU OHNE die cmi5-Startparameter
     * @return array<string, mixed>
     */
    public static function launched(string $id, array $actor, Session $session, AssignableUnit $unit, string $launchUrl, DateTimeImmutable $at): array {
        $statement = self::base($id, $actor, Verbs::LAUNCHED, 'launched', self::auObject($session), $session->registration, $session->sessionId, $session->publisherId, false, $at);

        $extensions = [
            Cmi5::EXTENSION_SESSION_ID => $session->sessionId,
            Cmi5::EXTENSION_LAUNCH_MODE => $session->launchMode->value,
            Cmi5::EXTENSION_LAUNCH_URL => $launchUrl,
            Cmi5::EXTENSION_MOVE_ON => $session->moveOn->value,
        ];
        if ($unit->launchParameters !== null) {
            $extensions[Cmi5::EXTENSION_LAUNCH_PARAMETERS] = $unit->launchParameters;
        }
        if ($session->masteryScore !== null) {
            $extensions[Cmi5::EXTENSION_MASTERY_SCORE] = $session->masteryScore;
        }
        $statement['context']['extensions'] = $extensions;

        return $statement;
    }

    /**
     * Sitzung ohne `terminated` beendet (cmi5 9.3.6, 9.5.4.2). Die Dauer ist Pflicht.
     *
     * @param  array<string, mixed>  $actor
     * @return array<string, mixed>
     */
    public static function abandoned(string $id, array $actor, Session $session, string $duration, DateTimeImmutable $at): array {
        $statement = self::base($id, $actor, Verbs::ABANDONED, 'abandoned', self::auObject($session), $session->registration, $session->sessionId, $session->publisherId, false, $at);
        $statement['result'] = ['duration' => $duration];

        return $statement;
    }

    /**
     * Erlass (cmi5 9.3.7). Braucht eine eigene, eindeutige Sitzungs-ID.
     *
     * @param  array<string, mixed>  $actor
     * @return array<string, mixed>
     */
    public static function waived(string $id, array $actor, Session $waiverSession, WaiveReason $reason, DateTimeImmutable $at): array {
        $statement = self::base($id, $actor, Verbs::WAIVED, 'waived', self::auObject($waiverSession), $waiverSession->registration, $waiverSession->sessionId, $waiverSession->publisherId, true, $at);
        $statement['result'] = [
            'success' => true,
            'completion' => true,
            'extensions' => [Cmi5::RESULT_EXTENSION_REASON => $reason->value],
        ];

        return $statement;
    }

    /**
     * Block oder Kurs erfüllt (cmi5 9.3.9). Die Objekt-ID vergibt das LMS und darf
     * nicht der Kennung aus der Kursstruktur entsprechen; diese steht in `grouping`.
     *
     * @param  array<string, mixed>  $actor
     * @return array<string, mixed>
     */
    public static function satisfied(string $id, array $actor, string $registration, string $sessionId, string $lmsObjectId, string $publisherId, SatisfiedScope $scope, DateTimeImmutable $at): array {
        $object = ['objectType' => 'Activity', 'id' => $lmsObjectId, 'definition' => ['type' => $scope->value]];

        return self::base($id, $actor, Verbs::SATISFIED, 'satisfied', $object, strtolower($registration), $sessionId, $publisherId, false, $at);
    }

    /** @return array{objectType: string, id: string} */
    private static function auObject(Session $session): array {
        return ['objectType' => 'Activity', 'id' => $session->activityId];
    }

    /**
     * @param  array<string, mixed>  $actor
     * @param  array<string, mixed>  $object
     * @return array<string, mixed>
     */
    private static function base(string $id, array $actor, string $verb, string $display, array $object, string $registration, string $sessionId, string $publisherId, bool $moveOn, DateTimeImmutable $at): array {
        $categories = [['objectType' => 'Activity', 'id' => Cmi5::CATEGORY_CMI5]];
        if ($moveOn) {
            $categories[] = ['objectType' => 'Activity', 'id' => Cmi5::CATEGORY_MOVE_ON];
        }

        return [
            'id' => strtolower($id),
            'actor' => $actor,
            'verb' => ['id' => $verb, 'display' => ['en-US' => $display]],
            'object' => $object,
            'context' => [
                'registration' => $registration,
                'contextActivities' => [
                    'category' => $categories,
                    'grouping' => [['objectType' => 'Activity', 'id' => $publisherId]],
                ],
                'extensions' => [Cmi5::EXTENSION_SESSION_ID => $sessionId],
            ],
            'timestamp' => $at->format('Y-m-d\TH:i:s.vP'),
        ];
    }

    private function __construct() {}
}
