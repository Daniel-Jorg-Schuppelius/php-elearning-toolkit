<?php
/*
 * Created on   : Mon Sep 14 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : LanguageMap.php
 * License      : Apache-2.0
 * License Uri  : https://www.apache.org/licenses/LICENSE-2.0
 */

declare(strict_types=1);

namespace ELearningToolkit\Cmi5;

/** Texte je Sprache (`langstring`), wie sie die Kursstruktur führt. */
final class LanguageMap {
    /**
     * Bestpassenden Text wählen: exakte Sprache, dann gleiche Hauptsprache,
     * sonst der erste vorhandene Text.
     *
     * @param  array<string, string>  $map
     */
    public static function pick(array $map, string ...$preferred): string {
        if ($map === []) {
            return '';
        }

        $lower = array_change_key_case($map, CASE_LOWER);

        foreach ($preferred as $language) {
            $language = strtolower($language);

            if (isset($lower[$language])) {
                return $lower[$language];
            }

            $primary = explode('-', $language)[0];
            foreach ($lower as $tag => $text) {
                if (explode('-', $tag)[0] === $primary) {
                    return $text;
                }
            }
        }

        return (string) reset($map);
    }

    private function __construct() {}
}
