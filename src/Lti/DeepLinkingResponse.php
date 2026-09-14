<?php
/*
 * Created on   : Mon Sep 14 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : DeepLinkingResponse.php
 * License      : Apache-2.0
 * License Uri  : https://www.apache.org/licenses/LICENSE-2.0
 */

declare(strict_types=1);

namespace ELearningToolkit\Lti;

use DateTimeImmutable;
use Jose\Component\Core\JWK;

/**
 * Deep-Linking-Antwort (Deep Linking 2.0, 4.5; Security Framework 5.2).
 *
 * Das Tool signiert sie mit seinem eigenen Schlüssel und sendet sie als
 * Formularfeld {@see self::FORM_PARAMETER} an die Rücksprungadresse. Die
 * Plattform prüft Aussteller (`client_id`), Empfänger (eigener Issuer), Nonce,
 * das zurückgegebene `data` und ob die Inhalte angenommen werden.
 */
final class DeepLinkingResponse {
    public const FORM_PARAMETER = 'JWT';

    /**
     * Toolseite: Antwort bauen.
     *
     * @param  list<array<string, mixed>>  $items
     * @return array{jwt: string, returnUrl: string}
     */
    public static function build(
        Registration $platform,
        string $deploymentId,
        DeepLinkingSettings $settings,
        array $items,
        JWK $toolKey,
        string $nonce,
        DateTimeImmutable $now,
        ?string $message = null,
        ?string $log = null,
        ?string $errorMessage = null,
        ?string $errorLog = null,
        int $ttlSeconds = 300,
    ): array {
        $settings->assertAcceptable($items);

        $claims = [
            'iss' => $platform->clientId,
            'aud' => $platform->issuer,
            'iat' => $now->getTimestamp(),
            'exp' => $now->getTimestamp() + $ttlSeconds,
            'nonce' => $nonce,
            Claims::MESSAGE_TYPE => MessageType::DeepLinkingResponse->value,
            Claims::VERSION => Claims::LTI_VERSION,
            Claims::DEPLOYMENT_ID => $deploymentId,
            Claims::DL_CONTENT_ITEMS => $items,
        ];

        // data muss zurück, wenn die Anfrage eins hatte (4.5.5).
        if ($settings->data !== null) {
            $claims[Claims::DL_DATA] = $settings->data;
        }

        foreach ([Claims::DL_MSG => $message, Claims::DL_LOG => $log, Claims::DL_ERROR_MSG => $errorMessage, Claims::DL_ERROR_LOG => $errorLog] as $claim => $value) {
            if ($value !== null) {
                $claims[$claim] = $value;
            }
        }

        return ['jwt' => JwtSigner::sign($claims, $toolKey), 'returnUrl' => $settings->returnUrl];
    }

    /**
     * Plattformseite: Antwort prüfen.
     *
     * @param  DeepLinkingSettings  $settings  die Einstellungen der ursprünglichen Anfrage
     * @return array{items: list<array<string, mixed>>, message: string|null, log: string|null, errorMessage: string|null, errorLog: string|null}
     *
     * @throws LtiException
     */
    public static function validate(
        string $jwt,
        Registration $tool,
        string $platformIssuer,
        string $expectedDeploymentId,
        DeepLinkingSettings $settings,
        NonceStore $nonces,
        DateTimeImmutable $now,
        ?JwtVerifier $verifier = null,
        int $nonceLifetimeSeconds = 3600,
    ): array {
        $claims = ($verifier ?? new JwtVerifier)->verify($jwt, $tool->keySet, $now);

        if (($claims['iss'] ?? null) !== $tool->clientId) {
            throw new LtiException(LtiException::ISSUER_MISMATCH);
        }

        Audience::assert($claims, $platformIssuer);

        if (($claims[Claims::MESSAGE_TYPE] ?? null) !== MessageType::DeepLinkingResponse->value) {
            throw new LtiException(LtiException::UNSUPPORTED_MESSAGE_TYPE);
        }
        if (($claims[Claims::VERSION] ?? null) !== Claims::LTI_VERSION) {
            throw new LtiException(LtiException::UNSUPPORTED_VERSION);
        }
        if (($claims[Claims::DEPLOYMENT_ID] ?? null) !== $expectedDeploymentId) {
            throw new LtiException(LtiException::UNKNOWN_DEPLOYMENT);
        }
        if ($settings->data !== null && ($claims[Claims::DL_DATA] ?? null) !== $settings->data) {
            throw new LtiException(LtiException::INVALID_CLAIM, 'data');
        }

        $items = $claims[Claims::DL_CONTENT_ITEMS] ?? [];
        if (!is_array($items) || !array_is_list($items)) {
            throw new LtiException(LtiException::INVALID_CLAIM, 'content_items');
        }
        foreach ($items as $index => $item) {
            if (!is_array($item)) {
                throw new LtiException(LtiException::INVALID_CLAIM, 'content_items[' . $index . ']');
            }
        }
        /** @var list<array<string, mixed>> $items */
        $settings->assertAcceptable($items);

        $nonce = $claims['nonce'] ?? null;
        if (!is_string($nonce) || $nonce === '') {
            throw new LtiException(LtiException::MISSING_CLAIM, 'nonce');
        }
        if (!$nonces->consume($nonce, $now->modify('+' . $nonceLifetimeSeconds . ' seconds'))) {
            throw new LtiException(LtiException::NONCE_REUSED);
        }

        $text = static fn (string $claim): ?string => is_string($claims[$claim] ?? null) ? $claims[$claim] : null;

        return [
            'items' => $items,
            'message' => $text(Claims::DL_MSG),
            'log' => $text(Claims::DL_LOG),
            'errorMessage' => $text(Claims::DL_ERROR_MSG),
            'errorLog' => $text(Claims::DL_ERROR_LOG),
        ];
    }

    private function __construct() {}
}
