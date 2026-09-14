<?php
/*
 * Created on   : Mon Sep 14 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : ManifestTest.php
 * License      : Apache-2.0
 * License Uri  : https://www.apache.org/licenses/LICENSE-2.0
 */

declare(strict_types=1);

namespace Tests\Scorm;

use ELearningToolkit\Package\PackageException;
use ELearningToolkit\Scorm\{Manifest, ScormVersion};
use PHPUnit\Framework\TestCase;

/**
 * Manifest-Parser für SCORM-Pakete. Die Fixtures bilden echte Paketformen ab:
 * SCORM 1.2 mit `adlcp:scormtype`, SCORM 2004 mit `adlcp:scormType` und
 * anderem Namensraum.
 */
final class ManifestTest extends TestCase {
    /**
     * Der Parser lief früher mit `LIBXML_NOENT`. Das Flag ERSETZT Entitäten, statt
     * sie zu unterbinden, und löste damit `<!ENTITY x SYSTEM "file:///...">` auf.
     * Ein hochgeladenes Paket konnte so lesbare Serverdateien im Titel zurückliefern.
     */
    public function test_external_file_entities_are_not_resolved(): void {
        $secret = (string) tempnam(sys_get_temp_dir(), 'xxe');
        file_put_contents($secret, 'GEHEIMNIS-XXE-KANARIENVOGEL');

        try {
            $xml = <<<XML
            <?xml version="1.0" encoding="UTF-8"?>
            <!DOCTYPE manifest [<!ENTITY xxe SYSTEM "file://{$secret}">]>
            <manifest identifier="M" version="1.0" xmlns="http://www.imsproject.org/xsd/imscp_rootv1p1p2"
                      xmlns:adlcp="http://www.adlnet.org/xsd/adlcp_rootv1p2">
              <organizations default="O"><organization identifier="O"><title>&xxe;</title>
                <item identifier="I" identifierref="R"><title>&xxe;</title></item>
              </organization></organizations>
              <resources><resource identifier="R" adlcp:scormtype="sco" href="index.html"/></resources>
            </manifest>
            XML;

            try {
                $manifest = Manifest::fromXml($xml);
            } catch (PackageException) {
                // Auch eine Ablehnung des Manifests ist ein sicheres Ergebnis.
                $this->addToAssertionCount(1);

                return;
            }

            $this->assertStringNotContainsString('KANARIENVOGEL', $manifest->title);
            $this->assertStringNotContainsString('KANARIENVOGEL', (string) json_encode($manifest->items));
        } finally {
            @unlink($secret);
        }
    }

    private function scorm12(): string {
        return <<<'XML'
<?xml version="1.0" encoding="UTF-8"?>
<manifest identifier="MANIFEST-1" version="1.0"
          xmlns="http://www.imsproject.org/xsd/imscp_rootv1p1p2"
          xmlns:adlcp="http://www.adlnet.org/xsd/adlcp_rootv1p2">
  <metadata>
    <schema>ADL SCORM</schema>
    <schemaversion>1.2</schemaversion>
  </metadata>
  <organizations default="ORG-1">
    <organization identifier="ORG-1">
      <title>Brandschutzunterweisung</title>
      <item identifier="ITEM-1" identifierref="RES-1">
        <title>Grundlagen</title>
      </item>
      <item identifier="ITEM-2" identifierref="RES-2">
        <title>Merkblatt</title>
      </item>
    </organization>
  </organizations>
  <resources>
    <resource identifier="RES-1" type="webcontent" adlcp:scormtype="sco" href="start.html">
      <file href="start.html"/>
    </resource>
    <resource identifier="RES-2" type="webcontent" adlcp:scormtype="asset" href="merkblatt.pdf">
      <file href="merkblatt.pdf"/>
    </resource>
  </resources>
</manifest>
XML;
    }

    private function scorm2004(): string {
        return <<<'XML'
<?xml version="1.0" encoding="UTF-8"?>
<manifest identifier="MANIFEST-2" version="1.0"
          xmlns="http://www.imsglobal.org/xsd/imscp_v1p1"
          xmlns:adlcp="http://www.adlnet.org/xsd/adlcp_v1p3">
  <metadata>
    <schema>ADL SCORM</schema>
    <schemaversion>2004 4th Edition</schemaversion>
  </metadata>
  <organizations default="ORG-A">
    <organization identifier="ORG-A">
      <title>Datenschutz</title>
      <item identifier="I1" identifierref="R1">
        <title>Modul 1</title>
      </item>
    </organization>
  </organizations>
  <resources>
    <resource identifier="R1" type="webcontent" adlcp:scormType="sco" href="index.html"/>
  </resources>
</manifest>
XML;
    }

    public function test_detects_scorm_12_with_title_and_launch_file(): void {
        $manifest = Manifest::fromXml($this->scorm12());

        $this->assertSame(ScormVersion::Scorm12, $manifest->version);
        $this->assertSame('Brandschutzunterweisung', $manifest->title);
        $this->assertSame('start.html', $manifest->launchHref);
        $this->assertSame('API', $manifest->version->apiObjectName());
        $this->assertSame('cmi.core.lesson_status', $manifest->version->completionKey());
        $this->assertSame('cmi.core.lesson_location', $manifest->version->locationKey());
    }

    public function test_tells_sco_from_asset(): void {
        $manifest = Manifest::fromXml($this->scorm12());

        $this->assertCount(2, $manifest->items);
        $this->assertTrue($manifest->items[0]->isSco, 'Nur ein SCO meldet Fortschritt zurück.');
        $this->assertFalse($manifest->items[1]->isSco, 'Ein Asset ist Beiwerk, kein Lernobjekt.');
        $this->assertSame('ITEM-2', $manifest->items[1]->identifier);
        $this->assertSame('Merkblatt', $manifest->items[1]->title);
    }

    public function test_detects_scorm_2004_despite_other_namespace_and_spelling(): void {
        $manifest = Manifest::fromXml($this->scorm2004());

        $this->assertSame(ScormVersion::Scorm2004, $manifest->version);
        $this->assertSame('Datenschutz', $manifest->title);
        $this->assertSame('index.html', $manifest->launchHref);
        $this->assertSame('API_1484_11', $manifest->version->apiObjectName());
        $this->assertSame('cmi.completion_status', $manifest->version->completionKey());
        $this->assertTrue($manifest->items[0]->isSco, 'adlcp:scormType mit großem T muss ebenso greifen.');
    }

    public function test_works_without_organization(): void {
        $xml = <<<'XML'
<?xml version="1.0"?>
<manifest xmlns="http://www.imsglobal.org/xsd/imscp_v1p1"
          xmlns:adlcp="http://www.adlnet.org/xsd/adlcp_v1p3">
  <metadata><schema>ADL SCORM</schema><schemaversion>2004 3rd Edition</schemaversion></metadata>
  <organizations/>
  <resources>
    <resource identifier="R" type="webcontent" adlcp:scormType="sco" href="run.html"/>
  </resources>
</manifest>
XML;

        $manifest = Manifest::fromXml($xml);

        // Manche Pakete führen nur Ressourcen — dann gilt die erste startbare.
        $this->assertSame('run.html', $manifest->launchHref);
    }

    public function test_picks_the_default_organization(): void {
        $xml = <<<'XML'
<?xml version="1.0"?>
<manifest xmlns="http://www.imsproject.org/xsd/imscp_rootv1p1p2"
          xmlns:adlcp="http://www.adlnet.org/xsd/adlcp_rootv1p2">
  <metadata><schema>ADL SCORM</schema><schemaversion>1.2</schemaversion></metadata>
  <organizations default="ZWEITE">
    <organization identifier="ERSTE">
      <title>Nicht diese</title>
      <item identifier="A" identifierref="RA"><title>A</title></item>
    </organization>
    <organization identifier="ZWEITE">
      <title>Diese</title>
      <item identifier="B" identifierref="RB"><title>B</title></item>
    </organization>
  </organizations>
  <resources>
    <resource identifier="RA" adlcp:scormtype="sco" href="a.html"/>
    <resource identifier="RB" adlcp:scormtype="sco" href="b.html"/>
  </resources>
</manifest>
XML;

        $manifest = Manifest::fromXml($xml);

        $this->assertSame('Diese', $manifest->title);
        $this->assertSame('b.html', $manifest->launchHref);
    }

    public function test_invalid_xml_is_rejected_with_a_reason(): void {
        try {
            Manifest::fromXml('<manifest><nicht geschlossen>');
            $this->fail('Ungültiges XML darf kein Manifest ergeben.');
        } catch (PackageException $e) {
            $this->assertSame(PackageException::UNREADABLE, $e->reason);
        }
    }

    public function test_xml_base_shifts_the_launch_path(): void {
        // Häufiges Autorenwerkzeug-Muster: die Dateien liegen unter
        // scormcontent/, das Manifest nennt nur `index.html`.
        $xml = <<<'XML'
<?xml version="1.0" encoding="UTF-8"?>
<manifest identifier="M" version="1"
          xmlns="http://www.imsproject.org/xsd/imscp_rootv1p1p2"
          xmlns:adlcp="http://www.adlnet.org/xsd/adlcp_rootv1p2"
          xmlns:xml="http://www.w3.org/XML/1998/namespace">
  <metadata><schema>ADL SCORM</schema><schemaversion>1.2</schemaversion></metadata>
  <organizations default="ORG"><organization identifier="ORG">
    <title>Kurs</title>
    <item identifier="I" identifierref="R"><title>Teil</title></item>
  </organization></organizations>
  <resources xml:base="scormcontent/">
    <resource identifier="R" adlcp:scormtype="sco" href="index.html">
      <file href="index.html"/>
    </resource>
  </resources>
</manifest>
XML;

        $this->assertSame('scormcontent/index.html', Manifest::fromXml($xml)->launchHref);
    }
}
