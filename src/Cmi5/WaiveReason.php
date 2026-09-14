<?php
/*
 * Created on   : Mon Sep 14 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : WaiveReason.php
 * License      : Apache-2.0
 * License Uri  : https://www.apache.org/licenses/LICENSE-2.0
 */

declare(strict_types=1);

namespace ELearningToolkit\Cmi5;

/** Gründe für einen Erlass (cmi5 9.5.5.2). */
enum WaiveReason: string {
    case TestedOut = 'Tested Out';
    case EquivalentAu = 'Equivalent AU';
    case EquivalentOutsideActivity = 'Equivalent Outside Activity';
    case Administrative = 'Administrative';
}
