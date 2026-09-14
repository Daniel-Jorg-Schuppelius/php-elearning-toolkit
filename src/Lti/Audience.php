<?php
/*
 * Created on   : Mon Sep 14 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : Audience.php
 * License      : Apache-2.0
 * License Uri  : https://www.apache.org/licenses/LICENSE-2.0
 */

declare(strict_types=1);

namespace ELearningToolkit\Lti;

/**
 * Empfängerprüfung für `id_token` und Tool-JWT (Security Framework 5.1.3, 5.2.3).
 *
 * `aud` muss den erwarteten Empfänger enthalten. Weitere Empfänger werden
 * abgelehnt: Die Spezifikation verlangt die Ablehnung nicht vertrauenswürdiger
 * zusätzlicher Empfänger, und hier ist nur einer vertrauenswürdig. Ein `azp`
 * muss dem erwarteten Empfänger entsprechen.
 */
final class Audience {
    /** @param array<string, mixed> $claims */
    public static function assert(array $claims, string $expected): void {
        $audience = $claims['aud'] ?? null;
        $list = is_string($audience) ? [$audience] : (is_array($audience) && array_is_list($audience) ? $audience : null);

        if ($list === null || !in_array($expected, $list, true)) {
            throw new LtiException(LtiException::AUDIENCE_MISMATCH, 'aud');
        }

        foreach ($list as $entry) {
            if ($entry !== $expected) {
                throw new LtiException(LtiException::AUDIENCE_MISMATCH, 'untrusted audience');
            }
        }

        if (array_key_exists('azp', $claims) && $claims['azp'] !== $expected) {
            throw new LtiException(LtiException::AUDIENCE_MISMATCH, 'azp');
        }
    }

    private function __construct() {}
}
