<?php
/*
 * Created on   : Mon Sep 14 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : Violation.php
 * License      : Apache-2.0
 * License Uri  : https://www.apache.org/licenses/LICENSE-2.0
 */

declare(strict_types=1);

namespace ELearningToolkit\XApi;

/** Ein Verstoß gegen die xAPI-Regeln: maschineller Grund und Fundstelle. */
final class Violation {
    public function __construct(
        public readonly string $reason,
        /** Punktpfad zur Eigenschaft, z. B. `actor.mbox`; leer für das Statement selbst. */
        public readonly string $path,
    ) {}
}
