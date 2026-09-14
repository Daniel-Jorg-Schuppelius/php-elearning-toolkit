<?php
/*
 * Created on   : Mon Sep 14 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : PackageExtractor.php
 * License      : Apache-2.0
 * License Uri  : https://www.apache.org/licenses/LICENSE-2.0
 */

declare(strict_types=1);

namespace ELearningToolkit\Package;

use CommonToolkit\Exceptions\Parsers\DocumentLimitExceededException;
use CommonToolkit\Helper\FileSystem\FileTypes\ZipFile;
use FilesystemIterator;
use InvalidArgumentException;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use SplFileInfo;
use Throwable;

/**
 * Entpackt ein Lernpaket sicher (SCORM mit `imsmanifest.xml`, cmi5 mit `cmi5.xml`).
 *
 * Die drei Gefahren beim Entpacken fremder Archive:
 *  1. **Zip-Slip**: Einträge wie `../../config/app.php` schreiben außerhalb des
 *     Zielordners. Das prüft {@see ZipFile::extract()}.
 *  2. **Ausführbarer Code**: Ein SCORM-Paket ist HTML, JavaScript und Medien.
 *     Alles, was ein Server ausführen könnte, wird gar nicht erst ausgepackt.
 *  3. **Zip-Bomben**: Dateizahl, entpackte Größe und optional das
 *     Kompressionsverhältnis sind gedeckelt.
 *
 * Legt der Extractor den Zielordner selbst an, räumt er ihn bei einem Fehler
 * wieder ab.
 */
final class PackageExtractor {
    /** Nicht auszupackende Endungen — alles, was ein Server ausführen könnte. */
    public const FORBIDDEN_EXTENSIONS = [
        'php', 'php3', 'php4', 'php5', 'php7', 'php8', 'phtml', 'phar', 'phps',
        'cgi', 'pl', 'py', 'sh', 'bash', 'exe', 'dll', 'so', 'htaccess', 'htpasswd',
    ];

    public const SCORM_DESCRIPTOR = 'imsmanifest.xml';

    public const CMI5_DESCRIPTOR = 'cmi5.xml';

    public function __construct(
        private readonly int $maxBytes = 512 * 1024 * 1024,
        private readonly int $maxFiles = 5000,
        private readonly ?float $maxRatio = null,
    ) {}

    /**
     * @param  string  $descriptor  Dateiname der Paketbeschreibung; gesucht wird die flachste Fundstelle
     */
    public function extract(string $zipPath, string $targetDir, string $descriptor = self::SCORM_DESCRIPTOR): ExtractedPackage {
        if (!is_file($zipPath)) {
            throw new PackageException(PackageException::UNREADABLE, 'Das Paket existiert nicht.');
        }

        $created = !is_dir($targetDir);
        if ($created) {
            self::createDirectory($targetDir);
        }

        try {
            try {
                ZipFile::extract(
                    $zipPath,
                    $targetDir,
                    false,
                    $this->maxFiles,
                    $this->maxBytes,
                    $this->maxRatio,
                    static fn (string $name): bool => self::isForbidden($name),
                );
            } catch (DocumentLimitExceededException $e) {
                throw new PackageException(
                    $e->getKind() === DocumentLimitExceededException::KIND_ENTRIES ? PackageException::TOO_MANY_FILES : PackageException::TOO_LARGE,
                    'Das Paket überschreitet eine Grenze.',
                    $e,
                );
            } catch (InvalidArgumentException $e) {
                throw new PackageException(PackageException::PATH_ESCAPE, 'Das Paket enthält einen Pfad außerhalb des Zielordners.', $e);
            } catch (PackageException $e) {
                throw $e;
            } catch (Throwable $e) {
                throw new PackageException(PackageException::UNREADABLE, 'Das Paket ließ sich nicht öffnen.', $e);
            }

            return $this->inventory($targetDir, $descriptor);
        } catch (PackageException $e) {
            if ($created) {
                self::removeTree($targetDir);
            }

            throw $e;
        }
    }

    /** Ist der Eintragsname eine Datei, die nicht ausgepackt werden darf? */
    public static function isForbidden(string $name): bool {
        $extension = strtolower(pathinfo($name, PATHINFO_EXTENSION));

        // `.htaccess` hat für pathinfo() keinen Namen, nur eine Endung.
        return $extension !== '' && in_array($extension, self::FORBIDDEN_EXTENSIONS, true);
    }

    private function inventory(string $targetDir, string $descriptor): ExtractedPackage {
        $root = rtrim((string) realpath($targetDir), DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR;
        $files = 0;
        $bytes = 0;
        $manifestPath = null;
        $manifestDepth = PHP_INT_MAX;

        $iterator = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($root, FilesystemIterator::SKIP_DOTS));

        /** @var SplFileInfo $file */
        foreach ($iterator as $file) {
            if (!$file->isFile()) {
                continue;
            }

            $files++;
            $bytes += (int) $file->getSize();

            if (strcasecmp($file->getFilename(), $descriptor) !== 0) {
                continue;
            }

            // Die flachste Fundstelle gewinnt; eine zweite tief im Paket ist Beiwerk.
            $relative = substr($file->getPathname(), strlen($root));
            $depth = substr_count(str_replace('\\', '/', $relative), '/');

            if ($depth < $manifestDepth) {
                $manifestDepth = $depth;
                $manifestPath = $file->getPathname();
            }
        }

        if ($manifestPath === null) {
            throw new PackageException(PackageException::MANIFEST_MISSING, 'Im Paket fehlt die ' . $descriptor . '.');
        }

        $xml = file_get_contents($manifestPath);

        if ($xml === false) {
            throw new PackageException(PackageException::UNREADABLE, 'Die ' . $descriptor . ' ist nicht lesbar.');
        }

        $directory = str_replace('\\', '/', dirname(substr($manifestPath, strlen($root))));

        return new ExtractedPackage($xml, $directory === '.' ? '' : $directory . '/', $files, $bytes);
    }

    /**
     * Ordner anlegen. Schlägt `mkdir` fehl, kann ein paralleler Prozess ihn
     * gerade angelegt haben — das ist kein Fehler.
     */
    private static function createDirectory(string $directory): void {
        if (@mkdir($directory, 0775, true) || is_dir($directory)) {
            return;
        }

        throw new PackageException(PackageException::TARGET_UNWRITABLE, 'Der Zielordner ließ sich nicht anlegen.');
    }

    private static function removeTree(string $directory): void {
        if (!is_dir($directory)) {
            return;
        }

        $iterator = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($directory, FilesystemIterator::SKIP_DOTS),
            RecursiveIteratorIterator::CHILD_FIRST,
        );

        /** @var SplFileInfo $entry */
        foreach ($iterator as $entry) {
            $entry->isDir() ? @rmdir($entry->getPathname()) : @unlink($entry->getPathname());
        }

        @rmdir($directory);
    }
}
