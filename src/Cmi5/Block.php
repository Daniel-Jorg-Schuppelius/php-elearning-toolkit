<?php
/*
 * Created on   : Mon Sep 14 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : Block.php
 * License      : Apache-2.0
 * License Uri  : https://www.apache.org/licenses/LICENSE-2.0
 */

declare(strict_types=1);

namespace ELearningToolkit\Cmi5;

/** Ein Block gruppiert AUs und weitere Blöcke (cmi5 13.1.2). */
final class Block {
    /**
     * @param  array<string, string>  $title
     * @param  array<string, string>  $description
     * @param  list<string>  $objectiveIds
     * @param  list<Block|AssignableUnit>  $children
     */
    public function __construct(
        public readonly string $id,
        public readonly array $title,
        public readonly array $description,
        public readonly array $objectiveIds,
        public readonly array $children,
    ) {}

    /** @return list<AssignableUnit> alle AUs darunter, in Dokumentreihenfolge */
    public function assignableUnits(): array {
        $units = [];

        foreach ($this->children as $child) {
            if ($child instanceof AssignableUnit) {
                $units[] = $child;
            } else {
                array_push($units, ...$child->assignableUnits());
            }
        }

        return $units;
    }
}
