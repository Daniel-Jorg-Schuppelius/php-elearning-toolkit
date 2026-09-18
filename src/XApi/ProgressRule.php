<?php
/*
 * Created on   : Mon Sep 14 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : ProgressRule.php
 * License      : Apache-2.0
 * License Uri  : https://www.apache.org/licenses/LICENSE-2.0
 */

declare(strict_types=1);

namespace ELearningToolkit\XApi;

/**
 * Leitet aus einem Statement den Fortschritt ab.
 *
 * **Abgeschlossen ist nicht bestanden.** Ein `completed` mit
 * `result.success = false` erfüllt die Einheit nicht — sonst ginge ein
 * durchgefallener Test als Nachweis durch. Dieselbe Regel gilt für SCORM
 * ({@see \ELearningToolkit\Scorm\CompletionRule}).
 */
final class ProgressRule {
    private const COMPLETING_VERBS = [Verbs::COMPLETED, Verbs::PASSED, Verbs::DOD_ISD_COMPLETED];

    private const FAILING_VERBS = [Verbs::FAILED];

    public static function evaluate(Statement $statement): Outcome {
        return self::decide($statement->verbId, $statement->success);
    }

    /**
     * Wie {@see evaluate()}, aber auf dem rohen Statement-Array ohne
     * Validierung: ein Endpunkt, der auch unsaubere Statements von
     * Autorenwerkzeugen annimmt, wertet sie nach derselben Regel aus.
     * Maßgeblich sind nur `verb.id` und `result.success`.
     *
     * @param array<mixed> $statement
     */
    public static function evaluateRaw(array $statement): Outcome {
        $verb = is_array($statement['verb'] ?? null) ? $statement['verb'] : [];
        $result = is_array($statement['result'] ?? null) ? $statement['result'] : [];

        return self::decide(
            is_string($verb['id'] ?? null) ? $verb['id'] : null,
            is_bool($result['success'] ?? null) ? $result['success'] : null,
        );
    }

    private static function decide(?string $verbId, ?bool $success): Outcome {
        if (in_array($verbId, self::FAILING_VERBS, true)) {
            return Outcome::Failed;
        }

        if (!in_array($verbId, self::COMPLETING_VERBS, true)) {
            return Outcome::None;
        }

        return $success === false ? Outcome::Failed : Outcome::Completed;
    }
}
