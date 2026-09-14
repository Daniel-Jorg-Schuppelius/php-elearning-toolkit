<?php
/*
 * Created on   : Mon Sep 14 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : Objective.php
 * License      : Apache-2.0
 * License Uri  : https://www.apache.org/licenses/LICENSE-2.0
 */

declare(strict_types=1);

namespace ELearningToolkit\Cmi5;

/** Lernziel der Kursstruktur. */
final class Objective {
    /**
     * @param  array<string, string>  $title
     * @param  array<string, string>  $description
     */
    public function __construct(
        public readonly string $id,
        public readonly array $title,
        public readonly array $description,
    ) {}
}
