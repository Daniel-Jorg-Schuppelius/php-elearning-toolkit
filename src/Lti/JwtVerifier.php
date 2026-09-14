<?php
/*
 * Created on   : Mon Sep 14 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : JwtVerifier.php
 * License      : Apache-2.0
 * License Uri  : https://www.apache.org/licenses/LICENSE-2.0
 */

declare(strict_types=1);

namespace ELearningToolkit\Lti;

use DateTimeImmutable;
use Jose\Component\Core\{AlgorithmManager, JWKSet};
use Jose\Component\Signature\Algorithm\RS256;
use Jose\Component\Signature\JWSVerifier;
use Jose\Component\Signature\Serializer\CompactSerializer;
use JsonException;
use Throwable;

/**
 * Prüft Signatur und Gültigkeitszeitraum eines JWT.
 *
 * **Nur RS256.** LTI 1.3 schreibt RS256 vor; ein Token mit `none` oder einem
 * symmetrischen Verfahren wird abgelehnt, bevor irgendein Schlüssel ins Spiel
 * kommt — sonst ließe sich der öffentliche Schlüssel als HMAC-Geheimnis
 * missbrauchen.
 *
 * Aussteller, Empfänger und Nonce prüft die jeweilige Nachrichtenprüfung; hier
 * geht es nur um Echtheit und Zeit.
 */
final class JwtVerifier {
    public function __construct(private readonly int $leewaySeconds = 60) {}

    /**
     * @return array<string, mixed> die Claims
     */
    public function verify(string $jwt, JWKSet $keys, DateTimeImmutable $now): array {
        try {
            $jws = (new CompactSerializer)->unserialize($jwt);
        } catch (Throwable $e) {
            throw new LtiException(LtiException::MALFORMED_TOKEN, '', $e);
        }

        if ($jws->countSignatures() !== 1) {
            throw new LtiException(LtiException::MALFORMED_TOKEN, 'signatures');
        }

        $header = $jws->getSignature(0)->getProtectedHeader();

        if (($header['alg'] ?? null) !== 'RS256') {
            throw new LtiException(LtiException::UNSUPPORTED_ALGORITHM, is_string($header['alg'] ?? null) ? $header['alg'] : '');
        }

        $kid = $header['kid'] ?? null;
        if (!is_string($kid) || $kid === '') {
            throw new LtiException(LtiException::MISSING_KEY_ID);
        }
        if (!$keys->has($kid)) {
            throw new LtiException(LtiException::UNKNOWN_KEY, $kid);
        }

        $key = $keys->get($kid);
        if ($key->get('kty') !== 'RSA') {
            throw new LtiException(LtiException::UNSUPPORTED_ALGORITHM, 'kty');
        }

        if (!(new JWSVerifier(new AlgorithmManager([new RS256])))->verifyWithKey($jws, $key->toPublic(), 0)) {
            throw new LtiException(LtiException::INVALID_SIGNATURE);
        }

        try {
            $claims = json_decode((string) $jws->getPayload(), true, 64, JSON_THROW_ON_ERROR);
        } catch (JsonException $e) {
            throw new LtiException(LtiException::MALFORMED_TOKEN, 'payload', $e);
        }

        if (!is_array($claims) || array_is_list($claims)) {
            throw new LtiException(LtiException::MALFORMED_TOKEN, 'payload');
        }

        $timestamp = $now->getTimestamp();

        if (!is_int($claims['exp'] ?? null)) {
            throw new LtiException(LtiException::MISSING_CLAIM, 'exp');
        }
        if (!is_int($claims['iat'] ?? null)) {
            throw new LtiException(LtiException::MISSING_CLAIM, 'iat');
        }
        if ($timestamp > $claims['exp'] + $this->leewaySeconds) {
            throw new LtiException(LtiException::EXPIRED);
        }
        if ($timestamp < $claims['iat'] - $this->leewaySeconds) {
            throw new LtiException(LtiException::ISSUED_IN_FUTURE);
        }

        /** @var array<string, mixed> $claims */
        return $claims;
    }
}
