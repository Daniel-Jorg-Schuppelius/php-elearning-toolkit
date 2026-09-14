<?php
/*
 * Created on   : Mon Sep 14 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : CompletionRule.php
 * License      : Apache-2.0
 * License Uri  : https://www.apache.org/licenses/LICENSE-2.0
 */

declare(strict_types=1);

namespace ELearningToolkit\Scorm;

/**
 * Hat ein SCO seine Einheit erfüllt?
 *
 * SCORM 1.2 kennt einen Wert für beides: `cmi.core.lesson_status` mit
 * `passed|completed|failed|incomplete|browsed|not attempted`. SCORM 2004
 * trennt `cmi.completion_status` (`completed|incomplete|not attempted|unknown`)
 * von `cmi.success_status` (`passed|failed|unknown`).
 *
 * **Abgeschlossen ist nicht bestanden.** Meldet ein SCO `completed` und
 * zugleich `failed`, ist die Einheit nicht erfüllt. Sonst ginge ein
 * durchgefallener Test als Unterweisungsnachweis durch.
 */
final class CompletionRule {
    public static function isSatisfied(ScormVersion $version, ?string $completionStatus, ?string $successStatus): bool {
        $success = strtolower(trim((string) $successStatus));

        if ($success === 'failed') {
            return false;
        }

        $completion = strtolower(trim((string) $completionStatus));

        return match ($version) {
            ScormVersion::Scorm2004 => $completion === 'completed',
            ScormVersion::Scorm12 => in_array($completion, ['passed', 'completed'], true),
        };
    }
}
