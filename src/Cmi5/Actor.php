<?php
/*
 * Created on   : Mon Sep 14 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : Actor.php
 * License      : Apache-2.0
 * License Uri  : https://www.apache.org/licenses/LICENSE-2.0
 */

declare(strict_types=1);

namespace ELearningToolkit\Cmi5;

/** Der Lernende als xAPI-Agent. cmi5 verlangt den Identifikator `account`. */
final class Actor {
    /** @return array{objectType: 'Agent', account: array{homePage: string, name: string}} */
    public static function forAccount(string $homePage, string $name): array {
        return ['objectType' => 'Agent', 'account' => ['homePage' => $homePage, 'name' => $name]];
    }

    private function __construct() {}
}
