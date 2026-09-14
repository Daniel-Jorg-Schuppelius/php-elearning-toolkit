<?php
/*
 * Created on   : Mon Sep 14 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : Keys.php
 * License      : Apache-2.0
 * License Uri  : https://www.apache.org/licenses/LICENSE-2.0
 */

declare(strict_types=1);

namespace ELearningToolkit\Lti;

use Jose\Component\Core\{JWK, JWKSet};
use Jose\Component\KeyManagement\JWKFactory;
use Throwable;

/**
 * Signierschlüssel und Schlüsselmengen (LTI Security Framework 6).
 *
 * Jeder Schlüssel trägt eine `kid`: Über sie wählt die Gegenseite in der
 * veröffentlichten Schlüsselmenge den passenden aus — das macht Rotation
 * möglich, ohne dass laufende Token ungültig werden.
 */
final class Keys {
    /** Privaten RSA-Schlüssel aus PEM laden. */
    public static function privateKeyFromPem(string $pem, string $kid, ?string $password = null): JWK {
        if ($kid === '') {
            throw new LtiException(LtiException::MISSING_KEY_ID);
        }

        try {
            $key = JWKFactory::createFromKey($pem, $password, ['kid' => $kid, 'alg' => 'RS256', 'use' => 'sig']);
        } catch (Throwable $e) {
            throw new LtiException(LtiException::INVALID_CLAIM, 'private key', $e);
        }

        if ($key->get('kty') !== 'RSA' || !$key->has('d')) {
            throw new LtiException(LtiException::INVALID_CLAIM, 'private key');
        }

        return $key;
    }

    /** Neuen RSA-Schlüssel erzeugen (für Ersteinrichtung und Rotation). */
    public static function generate(string $kid, int $bits = 2048): JWK {
        if ($kid === '') {
            throw new LtiException(LtiException::MISSING_KEY_ID);
        }

        return JWKFactory::createRSAKey($bits, ['kid' => $kid, 'alg' => 'RS256', 'use' => 'sig']);
    }

    /**
     * Öffentliche Schlüsselmenge zum Veröffentlichen — nie mit privaten Anteilen.
     *
     * @param  list<JWK>  $keys
     * @return array{keys: list<array<string, mixed>>}
     */
    public static function publicKeySet(array $keys): array {
        return ['keys' => array_map(static fn (JWK $key): array => $key->toPublic()->all(), $keys)];
    }

    /**
     * Schlüsselmenge der Gegenseite, wie sie ihre JWKS-URL liefert.
     *
     * @param  string|array<string, mixed>  $jwks
     */
    public static function keySet(string|array $jwks): JWKSet {
        try {
            $data = is_string($jwks) ? json_decode($jwks, true, 32, JSON_THROW_ON_ERROR) : $jwks;

            if (!is_array($data)) {
                throw new LtiException(LtiException::MALFORMED_TOKEN, 'jwks');
            }

            return JWKSet::createFromKeyData($data);
        } catch (LtiException $e) {
            throw $e;
        } catch (Throwable $e) {
            throw new LtiException(LtiException::MALFORMED_TOKEN, 'jwks', $e);
        }
    }

    private function __construct() {}
}
