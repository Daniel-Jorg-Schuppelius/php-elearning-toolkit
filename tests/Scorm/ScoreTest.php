<?php
/*
 * Created on   : Mon Sep 14 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : ScoreTest.php
 * License      : Apache-2.0
 * License Uri  : https://www.apache.org/licenses/LICENSE-2.0
 */

declare(strict_types=1);

namespace Tests\Scorm;

use ELearningToolkit\Scorm\Score;
use PHPUnit\Framework\TestCase;

final class ScoreTest extends TestCase {
    public function test_raw_points_against_the_default_maximum(): void {
        // SCORM 1.2 kennt cmi.score.scaled nicht; ohne Maximum gilt 100.
        $this->assertSame(0.8, Score::scaledFromRaw('80'));
        $this->assertSame(0.8, Score::scaledFromRaw(80, ''));
    }

    public function test_respects_minimum_and_maximum(): void {
        $this->assertSame(0.5, Score::scaledFromRaw(15, 20, 10));
        $this->assertSame(1.0, Score::scaledFromRaw(150, 100), 'Über dem Maximum wird gekappt.');
    }

    public function test_unusable_values_give_no_score(): void {
        $this->assertNull(Score::scaledFromRaw(''));
        $this->assertNull(Score::scaledFromRaw('viel'));
        $this->assertNull(Score::scaledFromRaw(5, 0, 0), 'Maximum gleich Minimum ergibt keine Skala.');
    }
}
