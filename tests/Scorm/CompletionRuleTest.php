<?php
/*
 * Created on   : Mon Sep 14 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : CompletionRuleTest.php
 * License      : Apache-2.0
 * License Uri  : https://www.apache.org/licenses/LICENSE-2.0
 */

declare(strict_types=1);

namespace Tests\Scorm;

use ELearningToolkit\Scorm\{CompletionRule, ScormVersion};
use PHPUnit\Framework\TestCase;

/** Abgeschlossen ist nicht bestanden — in beiden SCORM-Fassungen. */
final class CompletionRuleTest extends TestCase {
    public function test_scorm_12_accepts_passed_and_completed(): void {
        $this->assertTrue(CompletionRule::isSatisfied(ScormVersion::Scorm12, 'passed', null));
        $this->assertTrue(CompletionRule::isSatisfied(ScormVersion::Scorm12, 'COMPLETED', null));
        $this->assertFalse(CompletionRule::isSatisfied(ScormVersion::Scorm12, 'incomplete', null));
        $this->assertFalse(CompletionRule::isSatisfied(ScormVersion::Scorm12, 'failed', null));
        $this->assertFalse(CompletionRule::isSatisfied(ScormVersion::Scorm12, null, null));
    }

    public function test_scorm_2004_needs_completion(): void {
        $this->assertTrue(CompletionRule::isSatisfied(ScormVersion::Scorm2004, 'completed', 'passed'));
        $this->assertTrue(CompletionRule::isSatisfied(ScormVersion::Scorm2004, 'completed', 'unknown'));
        $this->assertFalse(CompletionRule::isSatisfied(ScormVersion::Scorm2004, 'incomplete', 'passed'));
    }

    public function test_completed_and_failed_is_never_a_proof(): void {
        // Sonst ginge ein durchgefallener Test als Unterweisungsnachweis durch.
        $this->assertFalse(CompletionRule::isSatisfied(ScormVersion::Scorm2004, 'completed', 'failed'));
        $this->assertFalse(CompletionRule::isSatisfied(ScormVersion::Scorm12, 'completed', 'failed'));
    }
}
