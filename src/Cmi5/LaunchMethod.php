<?php
/*
 * Created on   : Mon Sep 14 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : LaunchMethod.php
 * License      : Apache-2.0
 * License Uri  : https://www.apache.org/licenses/LICENSE-2.0
 */

declare(strict_types=1);

namespace ELearningToolkit\Cmi5;

/** Fensterkontext, den eine AU verlangt. Vorgabe laut Kursstruktur: `AnyWindow`. */
enum LaunchMethod: string {
    /** Das LMS wählt den Fensterkontext, auch ein eingebetteter Rahmen ist erlaubt. */
    case AnyWindow = 'AnyWindow';
    /** Eigenes Fenster oder Umleitung des aktuellen — kein eingebetteter Rahmen. */
    case OwnWindow = 'OwnWindow';

    public function allowsEmbedding(): bool {
        return $this === self::AnyWindow;
    }
}
