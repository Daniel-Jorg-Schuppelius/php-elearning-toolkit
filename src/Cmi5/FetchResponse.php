<?php
/*
 * Created on   : Mon Sep 14 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : FetchResponse.php
 * License      : Apache-2.0
 * License Uri  : https://www.apache.org/licenses/LICENSE-2.0
 */

declare(strict_types=1);

namespace ELearningToolkit\Cmi5;

/**
 * Antwortkörper der Fetch-URL.
 *
 * Die Fetch-URL antwortet immer mit HTTP 200 und JSON, auch im Fehlerfall.
 * Einen Token gibt sie genau einmal je Sitzung aus. Der Text des Fehlers ist
 * Sache des LMS.
 */
final class FetchResponse {
    /** @return array{auth-token: string} */
    public static function token(string $authToken): array {
        return ['auth-token' => $authToken];
    }

    /** @return array{error-code: string, error-text: string} */
    public static function error(FetchError $error, string $text): array {
        return ['error-code' => $error->value, 'error-text' => $text];
    }

    private function __construct() {}
}
