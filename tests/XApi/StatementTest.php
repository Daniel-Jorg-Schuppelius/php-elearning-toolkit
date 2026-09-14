<?php
/*
 * Created on   : Mon Sep 14 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : StatementTest.php
 * License      : Apache-2.0
 * License Uri  : https://www.apache.org/licenses/LICENSE-2.0
 */

declare(strict_types=1);

namespace Tests\XApi;

use ELearningToolkit\XApi\{Statement, StatementException, Verbs};
use PHPUnit\Framework\TestCase;

final class StatementTest extends TestCase {
    public function test_reads_the_fields_that_matter_for_progress(): void {
        $statement = Statement::fromJson((string) json_encode([
            'id' => '9FE4B3F2-6B1E-4C2A-8F1D-2A3B4C5D6E7F',
            'actor' => ['mbox' => 'mailto:erika@example.org'],
            'verb' => ['id' => Verbs::PASSED],
            'object' => ['id' => 'https://example.org/kurse/brandschutz'],
            'result' => ['success' => true, 'score' => ['scaled' => 1]],
            'context' => ['registration' => 'EC531277-B57B-4C15-8D91-D292C5B2B8F7'],
            'timestamp' => '2026-09-14T10:15:30Z',
        ]));

        $this->assertSame('9fe4b3f2-6b1e-4c2a-8f1d-2a3b4c5d6e7f', $statement->id, 'UUIDs werden kleingeschrieben verglichen.');
        $this->assertSame(Verbs::PASSED, $statement->verbId);
        $this->assertSame('Activity', $statement->objectType);
        $this->assertSame('https://example.org/kurse/brandschutz', $statement->objectId);
        $this->assertTrue($statement->success);
        $this->assertNull($statement->completion);
        $this->assertSame(1.0, $statement->scoreScaled);
        $this->assertSame('ec531277-b57b-4c15-8d91-d292c5b2b8f7', $statement->registration);
        $this->assertSame('2026-09-14T10:15:30Z', $statement->timestamp);
        $this->assertArrayHasKey('result', $statement->raw);
    }

    public function test_broken_json_has_its_own_reason(): void {
        try {
            Statement::fromJson('{"actor":');
            $this->fail('Kaputtes JSON wurde angenommen.');
        } catch (StatementException $e) {
            $this->assertSame(StatementException::INVALID_JSON, $e->reason);
        }
    }

    public function test_invalid_statements_never_become_objects(): void {
        $this->expectException(StatementException::class);
        Statement::fromArray(['actor' => ['mbox' => 'mailto:a@example.org'], 'verb' => ['id' => Verbs::COMPLETED]]);
    }
}
