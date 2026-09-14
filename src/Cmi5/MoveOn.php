<?php
/*
 * Created on   : Mon Sep 14 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : MoveOn.php
 * License      : Apache-2.0
 * License Uri  : https://www.apache.org/licenses/LICENSE-2.0
 */

declare(strict_types=1);

namespace ELearningToolkit\Cmi5;

/**
 * Wann gilt eine AU als erfüllt?
 *
 * Vorgabe laut Kursstruktur ist `NotApplicable`: Die AU gilt dann sofort als
 * erfüllt, das LMS schreibt dafür selbst ein `satisfied`.
 */
enum MoveOn: string {
    case Passed = 'Passed';
    case Completed = 'Completed';
    case CompletedAndPassed = 'CompletedAndPassed';
    case CompletedOrPassed = 'CompletedOrPassed';
    case NotApplicable = 'NotApplicable';

    public function isSatisfiedBy(bool $completed, bool $passed): bool {
        return match ($this) {
            self::Passed => $passed,
            self::Completed => $completed,
            self::CompletedAndPassed => $completed && $passed,
            self::CompletedOrPassed => $completed || $passed,
            self::NotApplicable => true,
        };
    }
}
