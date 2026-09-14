<?php
/*
 * Created on   : Mon Sep 14 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : LaunchDataTest.php
 * License      : Apache-2.0
 * License Uri  : https://www.apache.org/licenses/LICENSE-2.0
 */

declare(strict_types=1);

namespace Tests\Cmi5;

use ELearningToolkit\Cmi5\{Cmi5, Cmi5Exception, LaunchData, LaunchMode, LearnerPreferences, MoveOn, Session};
use PHPUnit\Framework\TestCase;

final class LaunchDataTest extends TestCase {
    use SessionFixture;

    public function test_document_carries_the_context_template_and_launch_values(): void {
        $document = LaunchData::document($this->unit(), $this->session(), 'https://lms.example.org/lernen/kurs/1', 'abc-456');

        $this->assertSame([['objectType' => 'Activity', 'id' => self::PUBLISHER_ID]], $document['contextTemplate']['contextActivities']['grouping']);
        $this->assertSame([Cmi5::EXTENSION_SESSION_ID => self::SESSION_ID], $document['contextTemplate']['extensions']);
        $this->assertSame('Normal', $document['launchMode']);
        $this->assertSame('CompletedAndPassed', $document['moveOn']);
        $this->assertSame(0.8, $document['masteryScore']);
        $this->assertSame('Start=1', $document['launchParameters']);
        $this->assertSame('https://lms.example.org/lernen/kurs/1', $document['returnURL']);
        $this->assertSame(['courseStructure' => 'xyz-123-9999', 'alternate' => 'abc-456'], $document['entitlementKey']);
    }

    public function test_lms_overrides_win_and_optional_values_stay_out(): void {
        $session = Session::forUnit($this->unit(), self::REGISTRATION, self::ACTIVITY_ID, self::SESSION_ID, LaunchMode::Review, moveOn: MoveOn::Completed, masteryScore: null);
        $withoutScore = new \ELearningToolkit\Cmi5\Session(self::REGISTRATION, self::ACTIVITY_ID, self::SESSION_ID, self::PUBLISHER_ID, LaunchMode::Review, MoveOn::Completed);

        $document = LaunchData::document($this->unit(), $withoutScore);

        $this->assertSame('Completed', LaunchData::document($this->unit(), $session)['moveOn']);
        $this->assertSame('Review', $document['launchMode']);
        $this->assertArrayNotHasKey('masteryScore', $document);
        $this->assertArrayNotHasKey('returnURL', $document);
        $this->assertSame(['courseStructure' => 'xyz-123-9999'], $document['entitlementKey']);
    }

    public function test_learner_preferences(): void {
        $this->assertSame(['languagePreference' => 'de-DE,en-US', 'audioPreference' => 'off'], LearnerPreferences::document(['de-DE', 'en-US'], false));
        $this->assertSame(['languagePreference' => '', 'audioPreference' => ''], LearnerPreferences::document([], null));

        $this->expectException(Cmi5Exception::class);
        LearnerPreferences::document(['Deutsch!'], true);
    }
}
