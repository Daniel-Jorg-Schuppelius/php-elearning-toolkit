<?php
/*
 * Created on   : Mon Sep 14 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : FetchError.php
 * License      : Apache-2.0
 * License Uri  : https://www.apache.org/licenses/LICENSE-2.0
 */

declare(strict_types=1);

namespace ELearningToolkit\Cmi5;

/** Fehlercodes der Fetch-URL (cmi5 8.2.3.2). */
enum FetchError: string {
    /** Token wurde schon ausgegeben oder die Sitzung ist abgelaufen. */
    case AlreadyInUseOrExpired = '1';
    /** Alle anderen Sicherheitsprobleme, etwa ein ungültiger Token. */
    case SecurityError = '2';
    /** Anwendungs- oder Umgebungsfehler. */
    case ApplicationError = '3';
}
