<?php
/*
 * Created on   : Mon Sep 14 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : SatisfiedScope.php
 * License      : Apache-2.0
 * License Uri  : https://www.apache.org/licenses/LICENSE-2.0
 */

declare(strict_types=1);

namespace ELearningToolkit\Cmi5;

/** Worauf sich ein `satisfied` bezieht (cmi5 9.3.9, 9.4). */
enum SatisfiedScope: string {
    case Block = Cmi5::ACTIVITY_TYPE_BLOCK;
    case Course = Cmi5::ACTIVITY_TYPE_COURSE;
}
