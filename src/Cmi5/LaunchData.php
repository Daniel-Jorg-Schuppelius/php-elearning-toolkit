<?php
/*
 * Created on   : Mon Sep 14 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : LaunchData.php
 * License      : Apache-2.0
 * License Uri  : https://www.apache.org/licenses/LICENSE-2.0
 */

declare(strict_types=1);

namespace ELearningToolkit\Cmi5;

/**
 * Das Dokument `LMS.LaunchData`, das das LMS vor jedem Start in die State-API
 * schreibt (cmi5 10). Schlüssel: Aktivitäts-ID der AU, Agent, Registrierung und
 * {@see Cmi5::STATE_LAUNCH_DATA}.
 */
final class LaunchData {
    /**
     * @return array<string, mixed>
     */
    public static function document(AssignableUnit $unit, Session $session, ?string $returnUrl = null, ?string $alternateEntitlementKey = null): array {
        $document = [
            'contextTemplate' => [
                'contextActivities' => [
                    'grouping' => [['objectType' => 'Activity', 'id' => $session->publisherId]],
                ],
                'extensions' => [Cmi5::EXTENSION_SESSION_ID => $session->sessionId],
            ],
            'launchMode' => $session->launchMode->value,
            'moveOn' => $session->moveOn->value,
        ];

        if ($unit->launchParameters !== null) {
            $document['launchParameters'] = $unit->launchParameters;
        }
        if ($session->masteryScore !== null) {
            $document['masteryScore'] = $session->masteryScore;
        }
        if ($returnUrl !== null && $returnUrl !== '') {
            $document['returnURL'] = $returnUrl;
        }
        if ($unit->entitlementKey !== null) {
            $document['entitlementKey'] = ['courseStructure' => $unit->entitlementKey]
                + ($alternateEntitlementKey !== null ? ['alternate' => $alternateEntitlementKey] : []);
        }

        return $document;
    }

    private function __construct() {}
}
