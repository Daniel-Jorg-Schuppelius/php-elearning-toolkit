<?php
/*
 * Created on   : Mon Sep 14 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : CourseStructureTest.php
 * License      : Apache-2.0
 * License Uri  : https://www.apache.org/licenses/LICENSE-2.0
 */

declare(strict_types=1);

namespace Tests\Cmi5;

use ELearningToolkit\Cmi5\{AssignableUnit, Block, Cmi5Exception, CourseStructure, LanguageMap, LaunchMethod, MoveOn};
use PHPUnit\Framework\TestCase;

final class CourseStructureTest extends TestCase {
    private function xml(string $body, string $objectives = ''): string {
        return <<<XML
<?xml version="1.0" encoding="utf-8"?>
<courseStructure xmlns="https://w3id.org/xapi/profiles/cmi5/v1/CourseStructure.xsd">
  <course id="https://example.org/kurse/arbeitsschutz">
    <title><langstring lang="de-DE">Arbeitsschutz</langstring><langstring lang="en-US">Safety</langstring></title>
    <description><langstring lang="de-DE">Jährliche Unterweisung</langstring></description>
  </course>
  {$objectives}
  {$body}
</courseStructure>
XML;
    }

    private function reasonOf(string $xml): string {
        try {
            CourseStructure::fromXml($xml);
        } catch (Cmi5Exception $e) {
            return $e->reason;
        }

        $this->fail('Erwartete Cmi5Exception blieb aus.');
    }

    public function test_reads_course_blocks_and_units_in_document_order(): void {
        $structure = CourseStructure::fromXml($this->xml(<<<'XML'
  <au id="https://example.org/au/einleitung" moveOn="Completed">
    <title><langstring lang="de-DE">Einleitung</langstring></title>
    <url>einleitung/index.html</url>
  </au>
  <block id="https://example.org/block/praxis">
    <title><langstring lang="de-DE">Praxis</langstring></title>
    <objectives><objective idref="https://example.org/ziel/brand"/></objectives>
    <au id="https://example.org/au/brand" moveOn="CompletedAndPassed" masteryScore="0.8" launchMethod="OwnWindow" activityType="http://adlnet.gov/expapi/activities/assessment">
      <title><langstring lang="de-DE">Brandschutz</langstring></title>
      <description><langstring lang="de-DE">Test</langstring></description>
      <url>https://content.example.org/brand/start.html?lang=de</url>
      <launchParameters>Start=1,QuizMode=1</launchParameters>
      <entitlementKey>xyz-123-9999</entitlementKey>
    </au>
    <block id="https://example.org/block/vertiefung">
      <title><langstring lang="de-DE">Vertiefung</langstring></title>
      <au id="https://example.org/au/erste-hilfe"><title><langstring lang="de-DE">Erste Hilfe</langstring></title><url>eh.html</url></au>
    </block>
  </block>
XML, '<objectives><objective id="https://example.org/ziel/brand"><title><langstring lang="de-DE">Brand</langstring></title></objective></objectives>'));

        $this->assertSame('https://example.org/kurse/arbeitsschutz', $structure->courseId);
        $this->assertSame('Safety', LanguageMap::pick($structure->title, 'en'));
        $this->assertSame('Arbeitsschutz', LanguageMap::pick($structure->title, 'fr', 'de'));
        $this->assertCount(1, $structure->objectives);

        $ids = array_map(static fn (AssignableUnit $u): string => $u->id, $structure->assignableUnits());
        $this->assertSame(['https://example.org/au/einleitung', 'https://example.org/au/brand', 'https://example.org/au/erste-hilfe'], $ids);
        $this->assertSame(['https://example.org/block/praxis', 'https://example.org/block/vertiefung'], array_map(static fn (Block $b): string => $b->id, $structure->blocks()));

        $intro = $structure->assignableUnits()[0];
        $this->assertSame(MoveOn::Completed, $intro->moveOn);
        $this->assertSame(LaunchMethod::AnyWindow, $intro->launchMethod, 'Vorgabe laut Spezifikation.');
        $this->assertNull($intro->masteryScore);
        $this->assertFalse($intro->hasAbsoluteUrl());

        $test = $structure->findUnit('https://example.org/au/brand');
        $this->assertNotNull($test);
        $this->assertSame(MoveOn::CompletedAndPassed, $test->moveOn);
        $this->assertSame(0.8, $test->masteryScore);
        $this->assertSame(LaunchMethod::OwnWindow, $test->launchMethod);
        $this->assertSame('Start=1,QuizMode=1', $test->launchParameters);
        $this->assertSame('xyz-123-9999', $test->entitlementKey);
        $this->assertSame('http://adlnet.gov/expapi/activities/assessment', $test->activityType);
        $this->assertTrue($test->hasAbsoluteUrl());
        $this->assertSame(['https://example.org/ziel/brand'], $structure->blocks()[0]->objectiveIds);

        $this->assertSame(MoveOn::NotApplicable, $structure->assignableUnits()[2]->moveOn, 'Ohne moveOn gilt NotApplicable.');
    }

    public function test_structural_errors_have_reasons(): void {
        $au = '<au id="https://example.org/au/1"><title><langstring lang="de">A</langstring></title><url>a.html</url></au>';

        $this->assertSame(Cmi5Exception::UNREADABLE, $this->reasonOf('<courseStructure><course>'));
        $this->assertSame(Cmi5Exception::MISSING_COURSE, $this->reasonOf('<courseStructure>' . $au . '</courseStructure>'));
        $this->assertSame(Cmi5Exception::INVALID_COURSE, $this->reasonOf($this->xml('')), 'Ein Kurs ohne AU ist kein Kurs.');
        $this->assertSame(Cmi5Exception::DUPLICATE_ID, $this->reasonOf($this->xml($au . $au)));
        $this->assertSame(Cmi5Exception::INVALID_AU, $this->reasonOf($this->xml('<au id="https://example.org/au/1"><title><langstring lang="de">A</langstring></title></au>')), 'Ohne url lässt sich nichts starten.');
        $this->assertSame(Cmi5Exception::INVALID_AU, $this->reasonOf($this->xml('<au id="kein-iri"><title><langstring lang="de">A</langstring></title><url>a.html</url></au>')));
        $this->assertSame(Cmi5Exception::INVALID_MOVE_ON, $this->reasonOf($this->xml(str_replace('<au ', '<au moveOn="Irgendwann" ', $au))));
        $this->assertSame(Cmi5Exception::INVALID_LAUNCH_METHOD, $this->reasonOf($this->xml(str_replace('<au ', '<au launchMethod="Popup" ', $au))));
        $this->assertSame(Cmi5Exception::INVALID_BLOCK, $this->reasonOf($this->xml('<block id="https://example.org/b"><title><langstring lang="de">B</langstring></title></block>' . $au)));
        $this->assertSame(Cmi5Exception::UNKNOWN_OBJECTIVE, $this->reasonOf($this->xml(str_replace('<url>', '<objectives><objective idref="https://example.org/ziel/fehlt"/></objectives><url>', $au))));
    }

    public function test_mastery_score_is_a_scaled_decimal_with_at_most_four_places(): void {
        $au = static fn (string $score): string => '<au id="https://example.org/au/1" masteryScore="' . $score . '"><title><langstring lang="de">A</langstring></title><url>a.html</url></au>';

        foreach (['0', '1', '0.85', '1.0000', '.5'] as $valid) {
            CourseStructure::fromXml($this->xml($au($valid)));
            $this->addToAssertionCount(1);
        }
        foreach (['1.5', '-0.1', '0.12345', 'hoch'] as $invalid) {
            $this->assertSame(Cmi5Exception::INVALID_MASTERY_SCORE, $this->reasonOf($this->xml($au($invalid))), $invalid);
        }
    }

    public function test_external_entities_are_not_resolved(): void {
        $secret = (string) tempnam(sys_get_temp_dir(), 'xxe');
        file_put_contents($secret, 'GEHEIM-KANARIENVOGEL');

        try {
            $xml = '<?xml version="1.0"?><!DOCTYPE courseStructure [<!ENTITY x SYSTEM "file://' . $secret . '">]>'
                . '<courseStructure><course id="https://example.org/k"><title><langstring lang="de">&x;</langstring></title></course>'
                . '<au id="https://example.org/au/1"><title><langstring lang="de">A</langstring></title><url>a.html</url></au></courseStructure>';

            try {
                $structure = CourseStructure::fromXml($xml);
            } catch (Cmi5Exception) {
                $this->addToAssertionCount(1);

                return;
            }

            $this->assertStringNotContainsString('KANARIENVOGEL', (string) json_encode($structure->title));
        } finally {
            @unlink($secret);
        }
    }
}
