<?php
/*
 * Created on   : Mon Sep 14 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : LaunchPath.php
 * License      : Apache-2.0
 * License Uri  : https://www.apache.org/licenses/LICENSE-2.0
 */

declare(strict_types=1);

namespace ELearningToolkit\Scorm;

/**
 * Startpfad eines Pakets für eine URL kodieren.
 *
 * Jedes Segment einzeln: Wer den ganzen Pfad kodiert, macht aus `/` ein `%2F`,
 * und relative Verweise im Paket (`js/app.js`, `../bilder/a.png`) zeigen ins
 * Leere. Die Abfrage bleibt, wie das Manifest sie nennt.
 */
final class LaunchPath {
    public static function encode(string $href): string {
        [$path, $query] = array_pad(explode('?', ltrim($href, '/'), 2), 2, null);

        $encoded = implode('/', array_map('rawurlencode', explode('/', (string) $path)));

        return $encoded . ($query !== null && $query !== '' ? '?' . $query : '');
    }
}
