<?php
/*
 * Created on   : Mon Sep 14 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : LaunchValidator.php
 * License      : Apache-2.0
 * License Uri  : https://www.apache.org/licenses/LICENSE-2.0
 */

declare(strict_types=1);

namespace ELearningToolkit\Lti;

use CommonToolkit\Helper\Data\WebLinkHelper;
use DateTimeImmutable;

/**
 * Prüft ein `id_token` auf der Toolseite (Security Framework 5.1.3, LTI Core 5.3).
 *
 * Reihenfolge ist Absicht: Erst Echtheit, Aussteller und Empfänger, dann der
 * Inhalt — und die Nonce wird **zuletzt** verbraucht. Sonst könnte jemand mit
 * gefälschten Token die Nonces echter Starts verbrennen.
 */
final class LaunchValidator {
    public function __construct(
        private readonly NonceStore $nonces,
        private readonly JwtVerifier $verifier = new JwtVerifier,
        private readonly int $nonceLifetimeSeconds = 3600,
    ) {}

    /**
     * @param  string|null  $expectedNonce  die an die Browser-Sitzung gebundene Nonce
     * @param  string|null  $expectedTargetLinkUri  `target_link_uri` aus dem Login-Start
     *
     * @throws LtiException
     */
    public function validate(string $idToken, Registration $platform, DateTimeImmutable $now, ?string $expectedNonce = null, ?string $expectedTargetLinkUri = null): LaunchMessage {
        $claims = $this->verifier->verify($idToken, $platform->keySet, $now);

        if (($claims['iss'] ?? null) !== $platform->issuer) {
            throw new LtiException(LtiException::ISSUER_MISMATCH);
        }

        Audience::assert($claims, $platform->clientId);

        $nonce = $claims['nonce'] ?? null;
        if (!is_string($nonce) || $nonce === '') {
            throw new LtiException(LtiException::MISSING_CLAIM, 'nonce');
        }
        if ($expectedNonce !== null && !hash_equals($expectedNonce, $nonce)) {
            throw new LtiException(LtiException::INVALID_CLAIM, 'nonce');
        }

        if (($claims[Claims::VERSION] ?? null) !== Claims::LTI_VERSION) {
            throw new LtiException(LtiException::UNSUPPORTED_VERSION);
        }

        $deploymentId = $claims[Claims::DEPLOYMENT_ID] ?? null;
        if (!Ascii::isIdentifier($deploymentId)) {
            throw new LtiException(LtiException::INVALID_CLAIM, 'deployment_id');
        }
        /** @var string $deploymentId */
        if (!$platform->hasDeployment($deploymentId)) {
            throw new LtiException(LtiException::UNKNOWN_DEPLOYMENT, $deploymentId);
        }

        $typeValue = $claims[Claims::MESSAGE_TYPE] ?? null;
        $type = is_string($typeValue) ? MessageType::tryFrom($typeValue) : null;
        if ($type === null || $type === MessageType::DeepLinkingResponse) {
            throw new LtiException(LtiException::UNSUPPORTED_MESSAGE_TYPE, is_string($typeValue) ? $typeValue : '');
        }

        $roles = $claims[Claims::ROLES] ?? null;
        if (!is_array($roles) || !array_is_list($roles)) {
            throw new LtiException(LtiException::MISSING_CLAIM, 'roles');
        }
        foreach ($roles as $role) {
            if (!is_string($role)) {
                throw new LtiException(LtiException::INVALID_CLAIM, 'roles');
            }
        }
        /** @var list<string> $roles */
        $subject = null;
        if (array_key_exists('sub', $claims)) {
            if (!Ascii::isIdentifier($claims['sub'])) {
                throw new LtiException(LtiException::INVALID_CLAIM, 'sub');
            }
            $subject = (string) $claims['sub'];
        }

        $targetLinkUri = null;
        $resourceLinkId = null;
        $settings = null;

        if ($type === MessageType::ResourceLinkRequest) {
            $target = $claims[Claims::TARGET_LINK_URI] ?? null;
            if (!is_string($target) || !WebLinkHelper::isAbsoluteIri($target)) {
                throw new LtiException(LtiException::MISSING_CLAIM, 'target_link_uri');
            }
            if ($expectedTargetLinkUri !== null && $target !== $expectedTargetLinkUri) {
                throw new LtiException(LtiException::INVALID_CLAIM, 'target_link_uri');
            }
            $targetLinkUri = $target;

            $link = $claims[Claims::RESOURCE_LINK] ?? null;
            if (!is_array($link) || !Ascii::isIdentifier($link['id'] ?? null)) {
                throw new LtiException(LtiException::MISSING_CLAIM, 'resource_link.id');
            }
            $resourceLinkId = (string) $link['id'];
        } else {
            $settings = DeepLinkingSettings::fromClaim($claims[Claims::DL_SETTINGS] ?? null);
        }

        $context = $claims[Claims::CONTEXT] ?? null;
        if ($context !== null && (!is_array($context) || !Ascii::isIdentifier($context['id'] ?? null))) {
            throw new LtiException(LtiException::INVALID_CLAIM, 'context.id');
        }

        // Zuletzt: Erst ein in allem gültiger Start verbraucht seine Nonce.
        if (!$this->nonces->consume($nonce, $now->modify('+' . $this->nonceLifetimeSeconds . ' seconds'))) {
            throw new LtiException(LtiException::NONCE_REUSED);
        }

        return new LaunchMessage($type, $platform->issuer, $deploymentId, $subject, $roles, $targetLinkUri, $resourceLinkId, $settings, $claims);
    }
}
