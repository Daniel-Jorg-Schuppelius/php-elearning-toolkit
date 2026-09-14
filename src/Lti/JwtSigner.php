<?php
/*
 * Created on   : Mon Sep 14 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : JwtSigner.php
 * License      : Apache-2.0
 * License Uri  : https://www.apache.org/licenses/LICENSE-2.0
 */

declare(strict_types=1);

namespace ELearningToolkit\Lti;

use Jose\Component\Core\{AlgorithmManager, JWK};
use Jose\Component\Signature\Algorithm\RS256;
use Jose\Component\Signature\JWSBuilder;
use Jose\Component\Signature\Serializer\CompactSerializer;
use JsonException;

/** Signiert Claims als JWT mit RS256 und `kid` im Kopf. */
final class JwtSigner {
    /**
     * @param  array<string, mixed>  $claims
     */
    public static function sign(array $claims, JWK $privateKey): string {
        $kid = $privateKey->has('kid') ? $privateKey->get('kid') : null;

        if (!is_string($kid) || $kid === '') {
            throw new LtiException(LtiException::MISSING_KEY_ID);
        }

        try {
            $payload = json_encode($claims, JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
        } catch (JsonException $e) {
            throw new LtiException(LtiException::INVALID_CLAIM, 'payload', $e);
        }

        $jws = (new JWSBuilder(new AlgorithmManager([new RS256])))
            ->create()
            ->withPayload($payload)
            ->addSignature($privateKey, ['alg' => 'RS256', 'kid' => $kid, 'typ' => 'JWT'])
            ->build();

        return (new CompactSerializer)->serialize($jws, 0);
    }

    private function __construct() {}
}
