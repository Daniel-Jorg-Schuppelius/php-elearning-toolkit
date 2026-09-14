<?php
/*
 * Created on   : Mon Sep 14 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : Score.php
 * License      : Apache-2.0
 * License Uri  : https://www.apache.org/licenses/LICENSE-2.0
 */

declare(strict_types=1);

namespace ELearningToolkit\Scorm;

/**
 * Punkte in den Wertebereich von `cmi.score.scaled` (−1 bis 1) bringen.
 *
 * SCORM 1.2 kennt `cmi.score.scaled` nicht. Dort kommen Rohpunkte gegen ein
 * Maximum, das Autorenwerkzeuge oft weglassen — die Vorgabe ist dann 100.
 */
final class Score {
    public static function scaledFromRaw(mixed $raw, mixed $max = 100, mixed $min = 0): ?float {
        if (!is_numeric($raw)) {
            return null;
        }

        // Ein leerer Wert ist nicht numerisch und fällt damit auf die Vorgabe.
        $maxValue = is_numeric($max) ? (float) $max : 100.0;
        $minValue = is_numeric($min) ? (float) $min : 0.0;

        if ($maxValue <= $minValue) {
            return null;
        }

        $scaled = ((float) $raw - $minValue) / ($maxValue - $minValue);

        return max(-1.0, min(1.0, $scaled));
    }
}
