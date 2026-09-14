<?php
/*
 * Created on   : Mon Sep 14 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : PackageExtractorTest.php
 * License      : Apache-2.0
 * License Uri  : https://www.apache.org/licenses/LICENSE-2.0
 */

declare(strict_types=1);

namespace Tests\Package;

use ELearningToolkit\Package\{PackageException, PackageExtractor};
use PHPUnit\Framework\TestCase;
use ZipArchive;

/**
 * Sicheres Entpacken fremder SCORM-Pakete. Der Test spielt die Angriffe durch,
 * die dabei zählen: Zip-Slip, ausführbarer Code und übergroße Archive.
 */
final class PackageExtractorTest extends TestCase {
    private string $workDir;

    protected function setUp(): void {
        parent::setUp();
        $this->workDir = sys_get_temp_dir() . '/scorm-test-' . bin2hex(random_bytes(6));
        mkdir($this->workDir, 0775, true);
    }

    protected function tearDown(): void {
        $this->removeTree($this->workDir);
        parent::tearDown();
    }

    /** @param array<string, string> $entries */
    private function makeZip(array $entries): string {
        $path = $this->workDir . '/package.zip';
        $zip = new ZipArchive;
        $zip->open($path, ZipArchive::CREATE | ZipArchive::OVERWRITE);

        foreach ($entries as $name => $contents) {
            $zip->addFromString($name, $contents);
        }

        $zip->close();

        return $path;
    }

    private function manifest(): string {
        return '<?xml version="1.0"?><manifest xmlns:adlcp="http://www.adlnet.org/xsd/adlcp_rootv1p2">'
            . '<metadata><schema>ADL SCORM</schema><schemaversion>1.2</schemaversion></metadata>'
            . '<organizations default="O"><organization identifier="O"><title>T</title>'
            . '<item identifier="I" identifierref="R"><title>Start</title></item></organization></organizations>'
            . '<resources><resource identifier="R" adlcp:scormtype="sco" href="start.html"/></resources></manifest>';
    }

    private function removeTree(string $dir): void {
        if (!is_dir($dir)) {
            return;
        }

        foreach (scandir($dir) ?: [] as $entry) {
            if ($entry === '.' || $entry === '..') {
                continue;
            }

            $path = $dir . '/' . $entry;
            is_dir($path) ? $this->removeTree($path) : unlink($path);
        }

        rmdir($dir);
    }

    private function reasonOf(callable $action): string {
        try {
            $action();
        } catch (PackageException $e) {
            return $e->reason;
        }

        $this->fail('Erwartete PackageException blieb aus.');
    }

    public function test_extracts_a_valid_package(): void {
        $zip = $this->makeZip([
            'imsmanifest.xml' => $this->manifest(),
            'start.html' => '<html>Hallo</html>',
            'assets/logo.png' => 'binär',
        ]);
        $target = $this->workDir . '/out';

        $result = (new PackageExtractor)->extract($zip, $target);

        $this->assertStringContainsString('ADL SCORM', $result->descriptorXml);
        $this->assertSame('', $result->descriptorDirectory);
        $this->assertSame(3, $result->files);
        $this->assertSame('start.html', $result->resolve('start.html'));
        $this->assertFileExists($target . '/start.html');
        $this->assertFileExists($target . '/assets/logo.png');
    }

    public function test_manifest_in_a_subfolder_shifts_every_reference(): void {
        // Viele Pakete sind als Ordner gezippt. Die Verweise im Manifest gelten
        // dann relativ zu diesem Ordner, nicht zum Zielordner.
        $zip = $this->makeZip([
            'kurs/imsmanifest.xml' => $this->manifest(),
            'kurs/start.html' => '<html></html>',
            'kurs/tief/imsmanifest.xml' => '<manifest/>',
        ]);

        $result = (new PackageExtractor)->extract($zip, $this->workDir . '/out');

        $this->assertSame('kurs/', $result->descriptorDirectory, 'Das flachste Manifest gewinnt.');
        $this->assertSame('kurs/start.html', $result->resolve('start.html'));
        $this->assertStringContainsString('ADL SCORM', $result->descriptorXml);
    }

    public function test_rejects_paths_outside_the_target(): void {
        $zip = $this->makeZip([
            'imsmanifest.xml' => $this->manifest(),
            '../../boeser.txt' => 'nicht hierhin',
        ]);

        $this->assertSame(PackageException::PATH_ESCAPE, $this->reasonOf(fn () => (new PackageExtractor)->extract($zip, $this->workDir . '/out')));
    }

    public function test_does_not_extract_executable_files(): void {
        $zip = $this->makeZip([
            'imsmanifest.xml' => $this->manifest(),
            'start.html' => '<html></html>',
            'shell.php' => '<?php system($_GET["cmd"]); ?>',
            '.htaccess' => 'php_flag engine on',
            'sub/Tool.PHAR' => 'x',
        ]);
        $target = $this->workDir . '/out';

        $result = (new PackageExtractor)->extract($zip, $target);

        $this->assertFileDoesNotExist($target . '/shell.php', 'Ausführbarer Code gehört nicht in den Auslieferungspfad.');
        $this->assertFileDoesNotExist($target . '/.htaccess');
        $this->assertFileDoesNotExist($target . '/sub/Tool.PHAR', 'Die Endung zählt ohne Rücksicht auf Groß-/Kleinschreibung.');
        $this->assertSame(2, $result->files);
    }

    public function test_caps_the_extracted_size(): void {
        $zip = $this->makeZip([
            'imsmanifest.xml' => $this->manifest(),
            'gross.bin' => str_repeat('A', 5000),
        ]);

        $this->assertSame(PackageException::TOO_LARGE, $this->reasonOf(fn () => (new PackageExtractor(maxBytes: 1000))->extract($zip, $this->workDir . '/out')));
    }

    public function test_caps_the_number_of_files(): void {
        $zip = $this->makeZip([
            'imsmanifest.xml' => $this->manifest(),
            'a.html' => 'a',
            'b.html' => 'b',
        ]);

        $this->assertSame(PackageException::TOO_MANY_FILES, $this->reasonOf(fn () => (new PackageExtractor(maxFiles: 2))->extract($zip, $this->workDir . '/out')));
    }

    public function test_package_without_manifest_is_rejected_and_cleaned_up(): void {
        $zip = $this->makeZip(['start.html' => '<html></html>']);
        $target = $this->workDir . '/out';

        $this->assertSame(PackageException::MANIFEST_MISSING, $this->reasonOf(fn () => (new PackageExtractor)->extract($zip, $target)));
        $this->assertDirectoryDoesNotExist($target, 'Einen selbst angelegten Zielordner räumt der Extractor wieder ab.');
    }

    public function test_an_existing_target_is_left_to_its_owner(): void {
        $target = $this->workDir . '/vorhanden';
        mkdir($target);
        file_put_contents($target . '/fremd.txt', 'bleibt');

        $zip = $this->makeZip(['start.html' => '<html></html>']);
        $this->reasonOf(fn () => (new PackageExtractor)->extract($zip, $target));

        $this->assertFileExists($target . '/fremd.txt');
    }

    public function test_unreadable_package_has_its_own_reason(): void {
        file_put_contents($this->workDir . '/kaputt.zip', 'kein zip');

        $this->assertSame(PackageException::UNREADABLE, $this->reasonOf(fn () => (new PackageExtractor)->extract($this->workDir . '/kaputt.zip', $this->workDir . '/out')));
        $this->assertSame(PackageException::UNREADABLE, $this->reasonOf(fn () => (new PackageExtractor)->extract($this->workDir . '/fehlt.zip', $this->workDir . '/out')));
    }

    public function test_forbidden_names(): void {
        foreach (['shell.php', '.htaccess', 'a/b.PHTML', 'run.sh'] as $name) {
            $this->assertTrue(PackageExtractor::isForbidden($name), $name);
        }
        foreach (['index.html', 'php.js', 'php', 'medien/film.mp4'] as $name) {
            $this->assertFalse(PackageExtractor::isForbidden($name), $name);
        }
    }

    public function test_a_cmi5_package_is_found_by_its_own_descriptor(): void {
        $zip = $this->makeZip([
            'kurs/cmi5.xml' => '<courseStructure/>',
            'kurs/au/index.html' => '<html></html>',
        ]);

        $result = (new PackageExtractor)->extract($zip, $this->workDir . '/out', PackageExtractor::CMI5_DESCRIPTOR);

        $this->assertSame('<courseStructure/>', $result->descriptorXml);
        $this->assertSame('kurs/au/index.html', $result->resolve('au/index.html'));

        $scormOnly = $this->makeZip(['imsmanifest.xml' => $this->manifest()]);
        $this->assertSame(PackageException::MANIFEST_MISSING, $this->reasonOf(fn () => (new PackageExtractor)->extract($scormOnly, $this->workDir . '/out2', PackageExtractor::CMI5_DESCRIPTOR)));
    }
}
