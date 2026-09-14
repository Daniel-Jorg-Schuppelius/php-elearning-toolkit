<?php
/*
 * Created on   : Mon Sep 14 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : StatementValidatorTest.php
 * License      : Apache-2.0
 * License Uri  : https://www.apache.org/licenses/LICENSE-2.0
 */

declare(strict_types=1);

namespace Tests\XApi;

use ELearningToolkit\XApi\{StatementException, StatementValidator, Verbs};
use PHPUnit\Framework\TestCase;

final class StatementValidatorTest extends TestCase {
    /**
     * @param  array<string, mixed>  $overrides
     * @return array<string, mixed>
     */
    private function statement(array $overrides = []): array {
        return array_replace([
            'id' => '9fe4b3f2-6b1e-4c2a-8f1d-2a3b4c5d6e7f',
            'actor' => ['objectType' => 'Agent', 'name' => 'Erika Muster', 'mbox' => 'mailto:erika@example.org'],
            'verb' => ['id' => Verbs::COMPLETED, 'display' => ['de-DE' => 'abgeschlossen']],
            'object' => ['id' => 'https://example.org/kurse/brandschutz', 'definition' => ['name' => ['de' => 'Brandschutz']]],
            'result' => ['success' => true, 'completion' => true, 'score' => ['scaled' => 0.9, 'raw' => 9, 'min' => 0, 'max' => 10], 'duration' => 'PT12M3.25S'],
            'context' => ['registration' => 'ec531277-b57b-4c15-8d91-d292c5b2b8f7', 'contextActivities' => ['parent' => [['id' => 'https://example.org/kurse']]]],
            'timestamp' => '2026-09-14T10:15:30.123+02:00',
        ], $overrides);
    }

    /**
     * @param  array<string, mixed>  $statement
     * @return list<string> "reason@path"
     */
    private function problems(array $statement): array {
        return array_map(static fn ($v): string => $v->reason . '@' . $v->path, (new StatementValidator)->violations($statement));
    }

    public function test_a_typical_statement_is_valid(): void {
        $this->assertSame([], $this->problems($this->statement()));
    }

    public function test_actor_verb_and_object_are_required(): void {
        $statement = $this->statement();
        unset($statement['actor'], $statement['verb'], $statement['object']);

        $problems = $this->problems($statement);

        $this->assertContains('missing_property@actor', $problems);
        $this->assertContains('missing_property@verb', $problems);
        $this->assertContains('missing_property@object', $problems);
    }

    public function test_an_agent_has_exactly_one_identifier(): void {
        $this->assertContains('invalid_agent@actor', $this->problems($this->statement(['actor' => ['name' => 'Niemand']])));
        $this->assertContains('invalid_agent@actor', $this->problems($this->statement([
            'actor' => ['mbox' => 'mailto:a@example.org', 'mbox_sha1sum' => str_repeat('a', 40)],
        ])));
        $this->assertContains('invalid_agent@actor.mbox', $this->problems($this->statement(['actor' => ['mbox' => 'a@example.org']])), 'mbox braucht das mailto-Schema.');
        $this->assertContains('missing_property@actor.account.name', $this->problems($this->statement(['actor' => ['account' => ['homePage' => 'https://lms.example.org']]])));
        $this->assertSame([], $this->problems($this->statement(['actor' => ['account' => ['homePage' => 'https://lms.example.org', 'name' => 'u-17']]])));
    }

    public function test_an_anonymous_group_needs_members_that_are_agents(): void {
        $this->assertContains('invalid_group@actor', $this->problems($this->statement(['actor' => ['objectType' => 'Group']])));
        $this->assertContains('invalid_agent@actor.member[0]', $this->problems($this->statement([
            'actor' => ['objectType' => 'Group', 'member' => [['objectType' => 'Group', 'member' => []]]],
        ])));
        $this->assertSame([], $this->problems($this->statement([
            'actor' => ['objectType' => 'Group', 'member' => [['mbox' => 'mailto:a@example.org']]],
        ])));
    }

    public function test_iris_uuids_and_versions_are_checked(): void {
        $this->assertContains('invalid_iri@verb.id', $this->problems($this->statement(['verb' => ['id' => 'abgeschlossen']])));
        $this->assertContains('invalid_iri@object.id', $this->problems($this->statement(['object' => ['id' => 'kurs/1']])));
        $this->assertContains('invalid_uuid@id', $this->problems($this->statement(['id' => 'nicht-eindeutig'])));
        $this->assertContains('invalid_version@version', $this->problems($this->statement(['version' => '2.0.0'])));
        $this->assertSame([], $this->problems($this->statement(['object' => ['id' => 'urn:uuid:9fe4b3f2-6b1e-4c2a-8f1d-2a3b4c5d6e7f'], 'version' => '1.0.3'])));
    }

    public function test_unknown_properties_are_rejected(): void {
        $this->assertContains('unknown_property@bonus', $this->problems($this->statement(['bonus' => true])));
        $this->assertContains('unknown_property@result.note', $this->problems($this->statement(['result' => ['note' => 1]])));
    }

    public function test_score_ranges(): void {
        $this->assertContains('invalid_score@result.score.scaled', $this->problems($this->statement(['result' => ['score' => ['scaled' => 1.5]]])));
        $this->assertContains('invalid_score@result.score.raw', $this->problems($this->statement(['result' => ['score' => ['raw' => 11, 'min' => 0, 'max' => 10]]])));
        $this->assertContains('invalid_score@result.score.min', $this->problems($this->statement(['result' => ['score' => ['min' => 10, 'max' => 10]]])));
        $this->assertContains('invalid_score@result.score.raw', $this->problems($this->statement(['result' => ['score' => ['raw' => '9']]])), 'Punkte sind Zahlen, keine Zeichenketten.');
    }

    public function test_durations_accept_fractions_of_seconds(): void {
        foreach (['PT12M3.25S', 'P1DT2H', 'PT0S', 'P3W'] as $duration) {
            $this->assertTrue(StatementValidator::isDuration($duration), $duration);
        }
        foreach (['PT', 'P', '12 Minuten', 'PT1.5M', "PT1S\n"] as $duration) {
            $this->assertFalse(StatementValidator::isDuration($duration), $duration);
        }
    }

    public function test_timestamps(): void {
        foreach (['2026-09-14T10:15:30Z', '2026-09-14T10:15:30.123456789+02:00', '2026-09-14T10:15', '2026-09-14T10:15:30+0200'] as $timestamp) {
            $this->assertTrue(StatementValidator::isTimestamp($timestamp), $timestamp);
        }
        foreach (['2026-09-14', '2026-02-30T10:00:00Z', '2026-09-14T25:00:00Z', '2026-09-14T10:15:30-00:00', "2026-09-14T10:15:30Z\n"] as $timestamp) {
            $this->assertFalse(StatementValidator::isTimestamp($timestamp), $timestamp);
        }
    }

    public function test_revision_and_platform_only_describe_activities(): void {
        $problems = $this->problems($this->statement([
            'object' => ['objectType' => 'Agent', 'mbox' => 'mailto:b@example.org'],
            'context' => ['platform' => 'workDiary'],
        ]));

        $this->assertContains('invalid_context@context.platform', $problems);
    }

    public function test_a_sub_statement_cannot_nest_another(): void {
        $problems = $this->problems($this->statement([
            'object' => [
                'objectType' => 'SubStatement',
                'actor' => ['mbox' => 'mailto:a@example.org'],
                'verb' => ['id' => Verbs::ATTEMPTED],
                'object' => ['objectType' => 'SubStatement'],
            ],
        ]));

        $this->assertContains('invalid_object@object.object.objectType', $problems);
        $this->assertContains('unknown_property@object.id', $this->problems($this->statement([
            'object' => ['objectType' => 'SubStatement', 'id' => '9fe4b3f2-6b1e-4c2a-8f1d-2a3b4c5d6e7f', 'actor' => ['mbox' => 'mailto:a@example.org'], 'verb' => ['id' => Verbs::ATTEMPTED], 'object' => ['id' => 'https://example.org/a']],
        ])));
    }

    public function test_language_maps_and_extensions(): void {
        $this->assertContains('invalid_language_map@verb.display.deutsch!', $this->problems($this->statement(['verb' => ['id' => Verbs::COMPLETED, 'display' => ['deutsch!' => 'x']]])));
        $this->assertContains('invalid_iri@result.extensions.punkte', $this->problems($this->statement(['result' => ['extensions' => ['punkte' => 3]]])));
    }

    public function test_assert_valid_reports_the_first_violation(): void {
        try {
            (new StatementValidator)->assertValid($this->statement(['verb' => ['id' => 'x']]));
            $this->fail('Ungültiges Statement wurde angenommen.');
        } catch (StatementException $e) {
            $this->assertSame('invalid_iri', $e->reason);
            $this->assertSame('verb.id', $e->path);
        }
    }
}
