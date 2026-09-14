<?php
/*
 * Created on   : Mon Sep 14 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : IdTokenBuilder.php
 * License      : Apache-2.0
 * License Uri  : https://www.apache.org/licenses/LICENSE-2.0
 */

declare(strict_types=1);

namespace ELearningToolkit\Lti;

use DateTimeImmutable;
use Jose\Component\Core\JWK;

/**
 * Baut das `id_token` auf der Plattformseite (Security Framework 5.1.1.3, LTI Core 5.3).
 *
 * Zusätzliche Claims (Name, E-Mail, Kontext, Custom …) kommen über
 * `$additionalClaims`; die Pflicht-Claims gewinnen immer und lassen sich darüber
 * nicht überschreiben.
 */
final class IdTokenBuilder {
    /**
     * @param  list<string>  $roles
     * @param  array<string, mixed>  $additionalClaims
     */
    public static function resourceLink(
        string $platformIssuer,
        Registration $tool,
        string $deploymentId,
        AuthenticationRequest $request,
        ?string $subject,
        array $roles,
        string $targetLinkUri,
        string $resourceLinkId,
        JWK $platformKey,
        DateTimeImmutable $now,
        array $additionalClaims = [],
        ?string $resourceLinkTitle = null,
        int $ttlSeconds = 300,
    ): string {
        $link = ['id' => $resourceLinkId];
        if ($resourceLinkTitle !== null) {
            $link['title'] = $resourceLinkTitle;
        }

        return self::sign($platformIssuer, $tool, $deploymentId, $request, $subject, $roles, MessageType::ResourceLinkRequest, [
            Claims::TARGET_LINK_URI => $targetLinkUri,
            Claims::RESOURCE_LINK => $link,
        ], $platformKey, $now, $additionalClaims, $ttlSeconds);
    }

    /**
     * @param  list<string>  $roles
     * @param  array<string, mixed>  $additionalClaims
     */
    public static function deepLinking(
        string $platformIssuer,
        Registration $tool,
        string $deploymentId,
        AuthenticationRequest $request,
        ?string $subject,
        array $roles,
        DeepLinkingSettings $settings,
        string $targetLinkUri,
        JWK $platformKey,
        DateTimeImmutable $now,
        array $additionalClaims = [],
        int $ttlSeconds = 300,
    ): string {
        return self::sign($platformIssuer, $tool, $deploymentId, $request, $subject, $roles, MessageType::DeepLinkingRequest, [
            Claims::TARGET_LINK_URI => $targetLinkUri,
            Claims::DL_SETTINGS => $settings->toClaim(),
        ], $platformKey, $now, $additionalClaims, $ttlSeconds);
    }

    /**
     * Formularfelder für das automatische POST an die `redirect_uri`.
     *
     * @return array<string, string>
     */
    public static function responseFields(string $idToken, AuthenticationRequest $request): array {
        return ['id_token' => $idToken] + ($request->state !== null ? ['state' => $request->state] : []);
    }

    /**
     * @param  list<string>  $roles
     * @param  array<string, mixed>  $messageClaims
     * @param  array<string, mixed>  $additionalClaims
     */
    private static function sign(
        string $platformIssuer,
        Registration $tool,
        string $deploymentId,
        AuthenticationRequest $request,
        ?string $subject,
        array $roles,
        MessageType $type,
        array $messageClaims,
        JWK $platformKey,
        DateTimeImmutable $now,
        array $additionalClaims,
        int $ttlSeconds,
    ): string {
        if ($request->clientId !== $tool->clientId) {
            throw new LtiException(LtiException::AUDIENCE_MISMATCH, 'client_id');
        }
        if (!$tool->hasDeployment($deploymentId)) {
            throw new LtiException(LtiException::UNKNOWN_DEPLOYMENT, $deploymentId);
        }

        $core = [
            'iss' => $platformIssuer,
            'aud' => $tool->clientId,
            'azp' => $tool->clientId,
            'iat' => $now->getTimestamp(),
            'exp' => $now->getTimestamp() + $ttlSeconds,
            'nonce' => $request->nonce,
            Claims::MESSAGE_TYPE => $type->value,
            Claims::VERSION => Claims::LTI_VERSION,
            Claims::DEPLOYMENT_ID => $deploymentId,
            Claims::ROLES => $roles,
        ] + $messageClaims;

        if ($subject !== null) {
            $core['sub'] = $subject;
        }

        return JwtSigner::sign(array_merge($additionalClaims, $core), $platformKey);
    }

    private function __construct() {}
}
