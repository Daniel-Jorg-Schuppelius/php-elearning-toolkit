<?php
/*
 * Created on   : Mon Sep 14 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : LaunchUrlTest.php
 * License      : Apache-2.0
 * License Uri  : https://www.apache.org/licenses/LICENSE-2.0
 */

declare(strict_types=1);

namespace Tests\Cmi5;

use ELearningToolkit\Cmi5\{Actor, Cmi5Exception, LaunchUrl};
use PHPUnit\Framework\TestCase;

final class LaunchUrlTest extends TestCase {
    private function build(string $auUrl): string {
        return LaunchUrl::build(
            $auUrl,
            'https://lms.example.org/xapi/',
            'https://lms.example.org/cmi5/fetch/abc?k=1',
            Actor::forAccount('https://lms.example.org', 'u-17'),
            '760e3480-ba55-4991-94b0-01820dbd23a2',
            'https://lms.example.org/activities/au-1',
        );
    }

    public function test_appends_encoded_parameters(): void {
        $url = $this->build('https://content.example.org/LA1/Start.html');
        [, $query] = explode('?', $url, 2);
        parse_str($query, $values);

        $this->assertStringStartsWith('https://content.example.org/LA1/Start.html?endpoint=', $url);
        $this->assertSame('https://lms.example.org/xapi/', $values['endpoint']);
        $this->assertSame('https://lms.example.org/cmi5/fetch/abc?k=1', $values['fetch'], 'Die Fetch-URL darf ihre eigene Abfrage behalten.');
        $this->assertIsString($values['actor']);
        $this->assertSame(['objectType' => 'Agent', 'account' => ['homePage' => 'https://lms.example.org', 'name' => 'u-17']], json_decode($values['actor'], true));
        $this->assertSame('760e3480-ba55-4991-94b0-01820dbd23a2', $values['registration']);
        $this->assertSame('https://lms.example.org/activities/au-1', $values['activityId']);
        $this->assertStringNotContainsString(' ', $url);
    }

    public function test_keeps_query_and_fragment_of_the_au(): void {
        $url = $this->build('https://content.example.org/start.html?lang=de#kapitel-2');

        $this->assertStringStartsWith('https://content.example.org/start.html?lang=de&endpoint=', $url);
        $this->assertStringEndsWith('#kapitel-2', $url);
    }

    public function test_a_colliding_parameter_name_is_rejected(): void {
        try {
            $this->build('https://content.example.org/start.html?registration=eigene');
            $this->fail('Kollidierende Parameter dürfen nicht überschrieben werden.');
        } catch (Cmi5Exception $e) {
            $this->assertSame(Cmi5Exception::PARAMETER_COLLISION, $e->reason);
            $this->assertSame('registration', $e->detail);
        }
    }
}
