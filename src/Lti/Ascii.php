<?php
/*
 * Created on   : Mon Sep 14 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : Ascii.php
 * License      : Apache-2.0
 * License Uri  : https://www.apache.org/licenses/LICENSE-2.0
 */

declare(strict_types=1);

namespace ELearningToolkit\Lti;

/** Kennungen in LTI: höchstens 255 ASCII-Zeichen, groß-/kleinschreibungsgenau. */
final class Ascii {
    public static function isIdentifier(mixed $value): bool {
        return is_string($value) && $value !== '' && strlen($value) <= 255 && mb_check_encoding($value, 'ASCII');
    }

    private function __construct() {}
}
