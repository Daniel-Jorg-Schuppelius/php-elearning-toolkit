<?php
/*
 * Created on   : Mon Sep 14 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : LaunchMode.php
 * License      : Apache-2.0
 * License Uri  : https://www.apache.org/licenses/LICENSE-2.0
 */

declare(strict_types=1);

namespace ELearningToolkit\Cmi5;

/** Startmodus einer AU laut `LMS.LaunchData`. */
enum LaunchMode: string {
    /** Erfüllungsrelevante Daten MÜSSEN aufgezeichnet werden. */
    case Normal = 'Normal';
    /** Umsehen ohne Bewertung — erfüllungsrelevante Daten dürfen NICHT aufgezeichnet werden. */
    case Browse = 'Browse';
    /** Nachschauen — erfüllungsrelevante Daten dürfen NICHT aufgezeichnet werden. */
    case Review = 'Review';

    /** Dürfen `completed`, `passed` und `failed` in diesem Modus gesendet werden? */
    public function recordsSatisfaction(): bool {
        return $this === self::Normal;
    }
}
