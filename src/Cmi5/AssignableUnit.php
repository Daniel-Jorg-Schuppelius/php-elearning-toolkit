<?php
/*
 * Created on   : Mon Sep 14 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : AssignableUnit.php
 * License      : Apache-2.0
 * License Uri  : https://www.apache.org/licenses/LICENSE-2.0
 */

declare(strict_types=1);

namespace ELearningToolkit\Cmi5;

/**
 * Eine AU der Kursstruktur (cmi5 13.1.4).
 *
 * `id` ist die Kennung des Herausgebers. Das LMS vergibt für den Start eine
 * eigene `activityId` und führt diese Kennung als Publisher-ID in `grouping`.
 */
final class AssignableUnit {
    /**
     * @param  array<string, string>  $title
     * @param  array<string, string>  $description
     * @param  list<string>  $objectiveIds
     */
    public function __construct(
        public readonly string $id,
        public readonly array $title,
        public readonly array $description,
        /** Relativ zur Kursstruktur oder voll qualifiziert, wie im Paket angegeben. */
        public readonly string $url,
        public readonly MoveOn $moveOn,
        /** Skaliert 0 bis 1; `null`, wenn die Kursstruktur keinen Wert nennt. */
        public readonly ?float $masteryScore,
        public readonly LaunchMethod $launchMethod,
        public readonly ?string $launchParameters,
        public readonly ?string $entitlementKey,
        public readonly ?string $activityType,
        public readonly array $objectiveIds,
    ) {}

    /** Nennt die Adresse ein eigenes Schema? Sonst liegt die AU im Paket. */
    public function hasAbsoluteUrl(): bool {
        return preg_match('/^[a-z][a-z0-9+.\-]*:/iD', $this->url) === 1;
    }
}
