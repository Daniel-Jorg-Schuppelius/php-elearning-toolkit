<?php
/*
 * Created on   : Mon Sep 14 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : LmsStatementsTest.php
 * License      : Apache-2.0
 * License Uri  : https://www.apache.org/licenses/LICENSE-2.0
 */

declare(strict_types=1);

namespace Tests\Cmi5;

use DateTimeImmutable;
use ELearningToolkit\Cmi5\{Actor, Cmi5, LmsStatements, SatisfiedScope, Session, WaiveReason};
use ELearningToolkit\XApi\{StatementValidator, Verbs, Violation};
use PHPUnit\Framework\TestCase;

final class LmsStatementsTest extends TestCase {
    use SessionFixture;

    /** @param array<string, mixed> $statement */
    private function assertValidXApi(array $statement): void {
        $problems = array_map(static fn (Violation $v): string => $v->reason . '@' . $v->path, (new StatementValidator)->violations($statement));
        $this->assertSame([], $problems);
    }

    private function at(): DateTimeImmutable {
        return new DateTimeImmutable('2026-09-14T10:15:30.123+02:00');
    }

    public function test_launched_carries_all_launch_extensions(): void {
        $statement = LmsStatements::launched('9FE4B3F2-6B1E-4C2A-8F1D-2A3B4C5D6E7F', Actor::forAccount('https://lms.example.org', 'u-17'), $this->session(), $this->unit(), 'https://content.example.org/brand/start.html', $this->at());

        $this->assertValidXApi($statement);
        $this->assertSame('9fe4b3f2-6b1e-4c2a-8f1d-2a3b4c5d6e7f', $statement['id']);
        $this->assertSame(Verbs::LAUNCHED, $statement['verb']['id']);
        $this->assertSame(self::ACTIVITY_ID, $statement['object']['id']);
        $extensions = $statement['context']['extensions'];
        $this->assertSame(self::SESSION_ID, $extensions[Cmi5::EXTENSION_SESSION_ID]);
        $this->assertSame('Normal', $extensions[Cmi5::EXTENSION_LAUNCH_MODE]);
        $this->assertSame('https://content.example.org/brand/start.html', $extensions[Cmi5::EXTENSION_LAUNCH_URL]);
        $this->assertSame('CompletedAndPassed', $extensions[Cmi5::EXTENSION_MOVE_ON]);
        $this->assertSame('Start=1', $extensions[Cmi5::EXTENSION_LAUNCH_PARAMETERS]);
        $this->assertSame(0.8, $extensions[Cmi5::EXTENSION_MASTERY_SCORE]);
        $this->assertSame('2026-09-14T10:15:30.123+02:00', $statement['timestamp']);
    }

    public function test_abandoned_has_a_duration_and_no_move_on(): void {
        $statement = LmsStatements::abandoned('01890a5d-ac96-774b-bcce-b302099a8057', Actor::forAccount('https://lms.example.org', 'u-17'), $this->session(), 'PT42M', $this->at());

        $this->assertValidXApi($statement);
        $this->assertSame(['duration' => 'PT42M'], $statement['result']);
        $this->assertSame([['objectType' => 'Activity', 'id' => Cmi5::CATEGORY_CMI5]], $statement['context']['contextActivities']['category']);
    }

    public function test_waived_is_successful_complete_and_names_its_reason(): void {
        $waiver = new Session(self::REGISTRATION, self::ACTIVITY_ID, 'erlass-sitzung', self::PUBLISHER_ID, \ELearningToolkit\Cmi5\LaunchMode::Normal, \ELearningToolkit\Cmi5\MoveOn::Passed);
        $statement = LmsStatements::waived('01890a5d-ac96-774b-bcce-b302099a8058', Actor::forAccount('https://lms.example.org', 'u-17'), $waiver, WaiveReason::Administrative, $this->at());

        $this->assertValidXApi($statement);
        $this->assertTrue($statement['result']['success']);
        $this->assertTrue($statement['result']['completion']);
        $this->assertSame('Administrative', $statement['result']['extensions'][Cmi5::RESULT_EXTENSION_REASON]);
        $this->assertContains(['objectType' => 'Activity', 'id' => Cmi5::CATEGORY_MOVE_ON], $statement['context']['contextActivities']['category']);
    }

    public function test_satisfied_refers_to_the_lms_block_object(): void {
        $statement = LmsStatements::satisfied('01890a5d-ac96-774b-bcce-b302099a8059', Actor::forAccount('https://lms.example.org', 'u-17'), self::REGISTRATION, self::SESSION_ID, 'https://lms.example.org/blocks/42', 'https://example.org/block/praxis', SatisfiedScope::Block, $this->at());

        $this->assertValidXApi($statement);
        $this->assertSame(['objectType' => 'Activity', 'id' => 'https://lms.example.org/blocks/42', 'definition' => ['type' => Cmi5::ACTIVITY_TYPE_BLOCK]], $statement['object']);
        $this->assertSame([['objectType' => 'Activity', 'id' => 'https://example.org/block/praxis']], $statement['context']['contextActivities']['grouping']);
    }
}
