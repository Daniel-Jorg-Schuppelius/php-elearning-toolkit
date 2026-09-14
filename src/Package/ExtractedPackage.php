<?php
/*
 * Created on   : Mon Sep 14 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : ExtractedPackage.php
 * License      : Apache-2.0
 * License Uri  : https://www.apache.org/licenses/LICENSE-2.0
 */

declare(strict_types=1);

namespace ELearningToolkit\Package;

/** Ergebnis eines erfolgreichen Entpackens. */
final class ExtractedPackage {
    public function __construct(
        /** Inhalt der Paketbeschreibung (`imsmanifest.xml` bzw. `cmi5.xml`). */
        public readonly string $descriptorXml,
        /**
         * Ordner der Paketbeschreibung relativ zum Ziel, leer oder mit
         * abschließendem `/`. Viele Pakete sind als Ordner gezippt; alle
         * Verweise gelten dann relativ zu diesem Ordner.
         */
        public readonly string $descriptorDirectory,
        public readonly int $files,
        public readonly int $bytes,
    ) {}

    /** Verweis aus der Paketbeschreibung auf einen Pfad relativ zum Zielordner. */
    public function resolve(string $href): string {
        return $this->descriptorDirectory . ltrim($href, '/');
    }
}
