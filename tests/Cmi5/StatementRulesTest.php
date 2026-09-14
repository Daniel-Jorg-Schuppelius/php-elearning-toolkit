<?php
/*
 * Created on   : Mon Sep 14 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : StatementRulesTest.php
 * License      : Apache-2.0
 * License Uri  : https://www.apache.org/licenses/LICENSE-2.0
 */

declare(strict_types=1);

namespace Tests\Cmi5;

use ELearningToolkit\Cmi5\{LaunchMode, StatementRules};
use ELearningToolkit\XApi\{Statement, Verbs, Violation};
use PHPUnit\Framework\TestCase;

final class StatementRulesTest extends TestCase {
    use SessionFixture;

    /** @return list<string> */
    private function problems(Statement $statement, ?\ELearningToolkit\Cmi5\Session $session = null): array {
        return array_map(static fn (Violation $v): string => $v->reason . '@' . $v->path, StatementRules::check($statement, $session ?? $this->session()));
    }

    public function test_a_complete_session_runs_through_and_satisfies_the_unit(): void {
        $session = $this->session();
        $flow = [
            $this->auStatement(Verbs::INITIALIZED),
            $this->auStatement('http://adlnet.gov/expapi/verbs/answered', [], false),
            $this->auStatement(Verbs::COMPLETED, ['completion' => true, 'duration' => 'PT5M'], true, true),
            $this->auStatement(Verbs::PASSED, ['success' => true, 'duration' => 'PT6M', 'score' => ['scaled' => 0.85]], true, true),
            $this->auStatement(Verbs::TERMINATED, ['duration' => 'PT7M']),
        ];

        foreach ($flow as $statement) {
            $this->assertSame([], $this->problems($statement, $session), $statement->verbId);
            $session = StatementRules::apply($statement, $session);
        }

        $this->assertTrue($session->isSatisfied());
        $this->assertTrue($session->hasEnded());
        $this->assertSame(['session_ended@'], $this->problems($this->auStatement(Verbs::INITIALIZED), $session), 'Nach terminated nimmt die Sitzung nichts mehr an.');
    }

    public function test_initialized_comes_first_and_only_once(): void {
        $this->assertContains('not_initialized@verb.id', $this->problems($this->auStatement(Verbs::COMPLETED, ['completion' => true, 'duration' => 'PT1M'], true, true)));
        $this->assertContains('not_initialized@verb.id', $this->problems($this->auStatement('http://adlnet.gov/expapi/verbs/answered', [], false)), 'Auch „cmi5 allowed" erst nach initialized.');

        $started = $this->session()->with(initialized: true);
        $this->assertContains('already_initialized@verb.id', $this->problems($this->auStatement(Verbs::INITIALIZED), $started));
    }

    public function test_lms_verbs_are_not_for_the_au(): void {
        $started = $this->session()->with(initialized: true);

        $this->assertContains('lms_only_verb@verb.id', $this->problems($this->auStatement(Verbs::WAIVED), $started));
        $this->assertContains('undefined_verb@verb.id', $this->problems($this->auStatement(Verbs::EXPERIENCED), $started));
    }

    public function test_result_obligations_per_verb(): void {
        $started = $this->session()->with(initialized: true);

        $this->assertContains('duration_required@result.duration', $this->problems($this->auStatement(Verbs::COMPLETED, ['completion' => true], true, true), $started));
        $this->assertContains('result_required@result.completion', $this->problems($this->auStatement(Verbs::COMPLETED, ['duration' => 'PT1M'], true, false), $started));
        $this->assertContains('result_required@result.success', $this->problems($this->auStatement(Verbs::FAILED, ['success' => true, 'duration' => 'PT1M'], true, true), $started));
        $this->assertContains('property_not_allowed@result.success', $this->problems($this->auStatement(Verbs::TERMINATED, ['success' => true, 'duration' => 'PT1M'], true, true), $started));
        $this->assertContains('property_not_allowed@result.score', $this->problems($this->auStatement(Verbs::COMPLETED, ['completion' => true, 'duration' => 'PT1M', 'score' => ['scaled' => 1]], true, true), $started));
    }

    public function test_move_on_category_exactly_when_an_outcome_is_carried(): void {
        $started = $this->session()->with(initialized: true);

        $this->assertContains('move_on_category_required@context.contextActivities.category', $this->problems($this->auStatement(Verbs::COMPLETED, ['completion' => true, 'duration' => 'PT1M'], true, false), $started));
        $this->assertContains('move_on_category_not_allowed@context.contextActivities.category', $this->problems($this->auStatement(Verbs::TERMINATED, ['duration' => 'PT1M'], true, true), $started));
    }

    public function test_mastery_score_decides_between_passed_and_failed(): void {
        $started = $this->session()->with(initialized: true);

        $this->assertContains('mastery_score_violated@result.score.scaled', $this->problems($this->auStatement(Verbs::PASSED, ['success' => true, 'duration' => 'PT1M', 'score' => ['scaled' => 0.5]], true, true), $started));
        $this->assertContains('mastery_score_violated@result.score.scaled', $this->problems($this->auStatement(Verbs::FAILED, ['success' => false, 'duration' => 'PT1M', 'score' => ['scaled' => 0.9]], true, true), $started));
        $this->assertSame([], $this->problems($this->auStatement(Verbs::FAILED, ['success' => false, 'duration' => 'PT1M', 'score' => ['scaled' => 0.5]], true, true), $started));
    }

    public function test_browse_and_review_record_no_satisfaction(): void {
        $browsing = $this->session(LaunchMode::Browse)->with(initialized: true);

        $this->assertContains('not_in_normal_mode@verb.id', $this->problems($this->auStatement(Verbs::COMPLETED, ['completion' => true, 'duration' => 'PT1M'], true, true), $browsing));
    }

    public function test_repeats_within_the_registration_are_rejected(): void {
        $done = $this->session()->with(initialized: true, completed: true, passed: true);

        $this->assertContains('already_completed@verb.id', $this->problems($this->auStatement(Verbs::COMPLETED, ['completion' => true, 'duration' => 'PT1M'], true, true), $done));
        $this->assertContains('already_passed@verb.id', $this->problems($this->auStatement(Verbs::PASSED, ['success' => true, 'duration' => 'PT1M'], true, true), $done));
    }

    public function test_the_context_template_must_match_the_launch(): void {
        $other = new \ELearningToolkit\Cmi5\Session('ec531277-b57b-4c15-8d91-d292c5b2b8f7', 'https://lms.example.org/activities/anders', 'andere-sitzung', 'https://example.org/au/anders', LaunchMode::Normal, \ELearningToolkit\Cmi5\MoveOn::Completed);

        $problems = $this->problems($this->auStatement(Verbs::INITIALIZED), $other);

        $this->assertContains('registration_mismatch@context.registration', $problems);
        $this->assertContains('session_mismatch@context.extensions', $problems);
        $this->assertContains('publisher_mismatch@context.contextActivities.grouping', $problems);
        $this->assertContains('activity_mismatch@object.id', $problems);
    }

    public function test_completed_alone_does_not_satisfy_completed_and_passed(): void {
        $session = $this->session()->with(initialized: true);
        $session = StatementRules::apply($this->auStatement(Verbs::COMPLETED, ['completion' => true, 'duration' => 'PT1M'], true, true), $session);

        $this->assertFalse($session->isSatisfied(), 'Abgeschlossen ist nicht bestanden.');
        $this->assertTrue($session->with(waived: true)->isSatisfied(), 'Ein Erlass zählt wie erfüllt.');
    }
}
