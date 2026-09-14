<?php
/*
 * Created on   : Mon Sep 14 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : PackageException.php
 * License      : Apache-2.0
 * License Uri  : https://www.apache.org/licenses/LICENSE-2.0
 */

declare(strict_types=1);

namespace ELearningToolkit\Package;

use RuntimeException;
use Throwable;

/**
 * Fehler beim Entpacken oder Lesen eines Lernpakets (SCORM, cmi5).
 *
 * Trägt einen **maschinellen Grund** statt einer fertigen Meldung. Das Toolkit
 * bleibt sprachneutral; übersetzt wird in der Anwendung.
 */
class PackageException extends RuntimeException {
    public const UNREADABLE = 'unreadable';
    public const TOO_MANY_FILES = 'too_many_files';
    public const TOO_LARGE = 'too_large';
    public const TARGET_UNWRITABLE = 'target_unwritable';
    public const MANIFEST_MISSING = 'manifest_missing';
    public const PATH_ESCAPE = 'path_escape';

    public function __construct(public readonly string $reason, string $message = '', ?Throwable $previous = null) {
        parent::__construct($message !== '' ? $message : $reason, 0, $previous);
    }
}
