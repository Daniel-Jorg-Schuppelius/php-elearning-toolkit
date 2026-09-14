<?php
/*
 * Created on   : Mon Sep 14 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : ManifestItem.php
 * License      : Apache-2.0
 * License Uri  : https://www.apache.org/licenses/LICENSE-2.0
 */

declare(strict_types=1);

namespace ELearningToolkit\Scorm;

/** Ein Eintrag der gewählten Organisation eines Manifests. */
final class ManifestItem {
    public function __construct(
        public readonly string $identifier,
        public readonly string $title,
        /** Startdatei relativ zum Manifest, `null` bei reinen Gliederungsknoten. */
        public readonly ?string $href,
        /** Nur ein SCO meldet Status an die Laufzeit; ein Asset nie. */
        public readonly bool $isSco,
    ) {}
}
