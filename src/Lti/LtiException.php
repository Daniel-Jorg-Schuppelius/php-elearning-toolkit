<?php
/*
 * Created on   : Mon Sep 14 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : LtiException.php
 * License      : Apache-2.0
 * License Uri  : https://www.apache.org/licenses/LICENSE-2.0
 */

declare(strict_types=1);

namespace ELearningToolkit\Lti;

use RuntimeException;
use Throwable;

/** Fehler rund um LTI. Trägt einen maschinellen Grund statt einer fertigen Meldung. */
final class LtiException extends RuntimeException {
    public const MALFORMED_TOKEN = 'malformed_token';
    public const UNSUPPORTED_ALGORITHM = 'unsupported_algorithm';
    public const MISSING_KEY_ID = 'missing_key_id';
    public const UNKNOWN_KEY = 'unknown_key';
    public const INVALID_SIGNATURE = 'invalid_signature';
    public const EXPIRED = 'expired';
    public const ISSUED_IN_FUTURE = 'issued_in_future';
    public const MISSING_CLAIM = 'missing_claim';
    public const INVALID_CLAIM = 'invalid_claim';
    public const ISSUER_MISMATCH = 'issuer_mismatch';
    public const AUDIENCE_MISMATCH = 'audience_mismatch';
    public const NONCE_REUSED = 'nonce_reused';
    public const UNKNOWN_DEPLOYMENT = 'unknown_deployment';
    public const UNSUPPORTED_MESSAGE_TYPE = 'unsupported_message_type';
    public const UNSUPPORTED_VERSION = 'unsupported_version';
    public const INVALID_LOGIN_REQUEST = 'invalid_login_request';
    public const INVALID_AUTHENTICATION_REQUEST = 'invalid_authentication_request';
    public const STATE_MISMATCH = 'state_mismatch';

    public function __construct(
        public readonly string $reason,
        public readonly string $detail = '',
        ?Throwable $previous = null,
    ) {
        parent::__construct($detail !== '' ? "{$reason}: {$detail}" : $reason, 0, $previous);
    }
}
