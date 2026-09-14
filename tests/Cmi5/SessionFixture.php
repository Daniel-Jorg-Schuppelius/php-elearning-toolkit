<?php
/*
 * Created on   : Mon Sep 14 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : SessionFixture.php
 * License      : Apache-2.0
 * License Uri  : https://www.apache.org/licenses/LICENSE-2.0
 */

declare(strict_types=1);

namespace Tests\Cmi5;

use ELearningToolkit\Cmi5\{AssignableUnit, Cmi5, LaunchMethod, LaunchMode, MoveOn, Session};
use ELearningToolkit\XApi\Statement;

/** Gemeinsame Bausteine der cmi5-Tests. */
trait SessionFixture {
    private const REGISTRATION = '760e3480-ba55-4991-94b0-01820dbd23a2';
    private const ACTIVITY_ID = 'https://lms.example.org/activities/au-17';
    private const PUBLISHER_ID = 'https://example.org/au/brand';
    private const SESSION_ID = 'sitzung-1';

    private function unit(MoveOn $moveOn = MoveOn::CompletedAndPassed, ?float $masteryScore = 0.8): AssignableUnit {
        return new AssignableUnit(
            self::PUBLISHER_ID,
            ['de-DE' => 'Brandschutz'],
            [],
            'brand/start.html',
            $moveOn,
            $masteryScore,
            LaunchMethod::AnyWindow,
            'Start=1',
            'xyz-123-9999',
            null,
            [],
        );
    }

    private function session(LaunchMode $mode = LaunchMode::Normal): Session {
        return Session::forUnit($this->unit(), self::REGISTRATION, self::ACTIVITY_ID, self::SESSION_ID, $mode);
    }

    /**
     * Ein Statement der AU mit korrekter Kontextvorlage.
     *
     * @param  array<string, mixed>  $result
     */
    private function auStatement(string $verb, array $result = [], bool $defined = true, bool $moveOn = false): Statement {
        $categories = [];
        if ($defined) {
            $categories[] = ['id' => Cmi5::CATEGORY_CMI5];
        }
        if ($moveOn) {
            $categories[] = ['id' => Cmi5::CATEGORY_MOVE_ON];
        }

        $context = [
            'registration' => self::REGISTRATION,
            'contextActivities' => ['grouping' => [['id' => self::PUBLISHER_ID]]],
            'extensions' => [Cmi5::EXTENSION_SESSION_ID => self::SESSION_ID],
        ];
        if ($categories !== []) {
            $context['contextActivities']['category'] = $categories;
        }

        $data = [
            'actor' => ['objectType' => 'Agent', 'account' => ['homePage' => 'https://lms.example.org', 'name' => 'u-17']],
            'verb' => ['id' => $verb],
            'object' => ['id' => self::ACTIVITY_ID],
            'context' => $context,
        ];
        if ($result !== []) {
            $data['result'] = $result;
        }

        return Statement::fromArray($data);
    }
}
