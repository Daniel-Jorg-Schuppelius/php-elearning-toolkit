<?php
/*
 * Created on   : Mon Sep 14 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : Outcome.php
 * License      : Apache-2.0
 * License Uri  : https://www.apache.org/licenses/LICENSE-2.0
 */

declare(strict_types=1);

namespace ELearningToolkit\XApi;

/** Was ein Statement für den Fortschritt einer Lerneinheit bedeutet. */
enum Outcome: string {
    /** Kein Einfluss auf den Fortschritt. */
    case None = 'none';
    /** Die Einheit ist erfüllt. */
    case Completed = 'completed';
    /** Gescheitert — schließt nie ab. */
    case Failed = 'failed';
}
