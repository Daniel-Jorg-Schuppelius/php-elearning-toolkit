<?php
/*
 * Created on   : Mon Sep 14 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : StatementException.php
 * License      : Apache-2.0
 * License Uri  : https://www.apache.org/licenses/LICENSE-2.0
 */

declare(strict_types=1);

namespace ELearningToolkit\XApi;

use RuntimeException;
use Throwable;

/** Ein Statement ist ungültig. Grund und Fundstelle sind maschinell lesbar. */
final class StatementException extends RuntimeException {
    public const INVALID_JSON = 'invalid_json';

    public function __construct(
        public readonly string $reason,
        public readonly string $path = '',
        ?Throwable $previous = null,
    ) {
        parent::__construct($path !== '' ? "{$reason} ({$path})" : $reason, 0, $previous);
    }

    public static function fromViolation(Violation $violation): self {
        return new self($violation->reason, $violation->path);
    }
}
