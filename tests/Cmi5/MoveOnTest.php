<?php
/*
 * Created on   : Mon Sep 14 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : MoveOnTest.php
 * License      : Apache-2.0
 * License Uri  : https://www.apache.org/licenses/LICENSE-2.0
 */

declare(strict_types=1);

namespace Tests\Cmi5;

use ELearningToolkit\Cmi5\{LaunchMethod, LaunchMode, MoveOn};
use PHPUnit\Framework\TestCase;

final class MoveOnTest extends TestCase {
    public function test_each_criterion(): void {
        $this->assertTrue(MoveOn::Passed->isSatisfiedBy(false, true));
        $this->assertFalse(MoveOn::Passed->isSatisfiedBy(true, false), 'Abgeschlossen ist nicht bestanden.');
        $this->assertTrue(MoveOn::Completed->isSatisfiedBy(true, false));
        $this->assertFalse(MoveOn::CompletedAndPassed->isSatisfiedBy(true, false));
        $this->assertTrue(MoveOn::CompletedAndPassed->isSatisfiedBy(true, true));
        $this->assertTrue(MoveOn::CompletedOrPassed->isSatisfiedBy(false, true));
        $this->assertTrue(MoveOn::NotApplicable->isSatisfiedBy(false, false));
    }

    public function test_only_normal_mode_records_satisfaction(): void {
        $this->assertTrue(LaunchMode::Normal->recordsSatisfaction());
        $this->assertFalse(LaunchMode::Browse->recordsSatisfaction());
        $this->assertFalse(LaunchMode::Review->recordsSatisfaction());
    }

    public function test_own_window_forbids_embedding(): void {
        $this->assertTrue(LaunchMethod::AnyWindow->allowsEmbedding());
        $this->assertFalse(LaunchMethod::OwnWindow->allowsEmbedding());
    }
}
