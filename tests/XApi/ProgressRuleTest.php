<?php
/*
 * Created on   : Mon Sep 14 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : ProgressRuleTest.php
 * License      : Apache-2.0
 * License Uri  : https://www.apache.org/licenses/LICENSE-2.0
 */

declare(strict_types=1);

namespace Tests\XApi;

use ELearningToolkit\XApi\{Outcome, ProgressRule, Statement, Verbs};
use PHPUnit\Framework\TestCase;

final class ProgressRuleTest extends TestCase {
    private function statement(string $verb, ?bool $success = null): Statement {
        $data = [
            'actor' => ['mbox' => 'mailto:erika@example.org'],
            'verb' => ['id' => $verb],
            'object' => ['id' => 'https://example.org/kurse/brandschutz'],
        ];
        if ($success !== null) {
            $data['result'] = ['success' => $success];
        }

        return Statement::fromArray($data);
    }

    public function test_completion_verbs_complete(): void {
        $this->assertSame(Outcome::Completed, ProgressRule::evaluate($this->statement(Verbs::COMPLETED)));
        $this->assertSame(Outcome::Completed, ProgressRule::evaluate($this->statement(Verbs::PASSED, true)));
        $this->assertSame(Outcome::Completed, ProgressRule::evaluate($this->statement(Verbs::DOD_ISD_COMPLETED)));
    }

    public function test_completed_with_failure_is_never_a_proof(): void {
        $this->assertSame(Outcome::Failed, ProgressRule::evaluate($this->statement(Verbs::COMPLETED, false)));
        $this->assertSame(Outcome::Failed, ProgressRule::evaluate($this->statement(Verbs::FAILED)));
    }

    public function test_other_verbs_do_not_touch_progress(): void {
        $this->assertSame(Outcome::None, ProgressRule::evaluate($this->statement(Verbs::EXPERIENCED)));
        $this->assertSame(Outcome::None, ProgressRule::evaluate($this->statement(Verbs::LAUNCHED)));
    }
}
