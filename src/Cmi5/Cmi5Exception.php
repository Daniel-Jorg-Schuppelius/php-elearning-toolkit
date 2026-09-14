<?php
/*
 * Created on   : Mon Sep 14 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : Cmi5Exception.php
 * License      : Apache-2.0
 * License Uri  : https://www.apache.org/licenses/LICENSE-2.0
 */

declare(strict_types=1);

namespace ELearningToolkit\Cmi5;

use RuntimeException;
use Throwable;

/** Fehler rund um cmi5. Trägt einen maschinellen Grund statt einer fertigen Meldung. */
final class Cmi5Exception extends RuntimeException {
    public const UNREADABLE = 'unreadable';
    public const MISSING_COURSE = 'missing_course';
    public const INVALID_COURSE = 'invalid_course';
    public const INVALID_BLOCK = 'invalid_block';
    public const INVALID_AU = 'invalid_au';
    public const INVALID_MOVE_ON = 'invalid_move_on';
    public const INVALID_MASTERY_SCORE = 'invalid_mastery_score';
    public const INVALID_LAUNCH_METHOD = 'invalid_launch_method';
    public const DUPLICATE_ID = 'duplicate_id';
    public const INVALID_URL = 'invalid_url';
    public const PARAMETER_COLLISION = 'parameter_collision';
    public const UNKNOWN_OBJECTIVE = 'unknown_objective';
    public const INVALID_LANGUAGE = 'invalid_language';

    public function __construct(
        public readonly string $reason,
        public readonly string $detail = '',
        ?Throwable $previous = null,
    ) {
        parent::__construct($detail !== '' ? "{$reason}: {$detail}" : $reason, 0, $previous);
    }
}
