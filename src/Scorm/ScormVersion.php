<?php
/*
 * Created on   : Mon Sep 14 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : ScormVersion.php
 * License      : Apache-2.0
 * License Uri  : https://www.apache.org/licenses/LICENSE-2.0
 */

declare(strict_types=1);

namespace ELearningToolkit\Scorm;

/**
 * SCORM-Fassung eines Pakets.
 *
 * Die Werte sind stabil und dürfen gespeichert werden.
 */
enum ScormVersion: string {
    case Scorm12 = 'scorm_1_2';
    case Scorm2004 = 'scorm_2004';

    /** Name des Laufzeitobjekts, das der Inhalt im Elternfenster sucht. */
    public function apiObjectName(): string {
        return match ($this) {
            self::Scorm12 => 'API',
            self::Scorm2004 => 'API_1484_11',
        };
    }

    /** Datenmodell-Schlüssel des Abschlussstatus. */
    public function completionKey(): string {
        return match ($this) {
            self::Scorm12 => 'cmi.core.lesson_status',
            self::Scorm2004 => 'cmi.completion_status',
        };
    }

    /** Datenmodell-Schlüssel der Fortsetzungsposition. */
    public function locationKey(): string {
        return match ($this) {
            self::Scorm12 => 'cmi.core.lesson_location',
            self::Scorm2004 => 'cmi.location',
        };
    }
}
