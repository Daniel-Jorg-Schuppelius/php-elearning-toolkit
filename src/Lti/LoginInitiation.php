<?php
/*
 * Created on   : Mon Sep 14 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : LoginInitiation.php
 * License      : Apache-2.0
 * License Uri  : https://www.apache.org/licenses/LICENSE-2.0
 */

declare(strict_types=1);

namespace ELearningToolkit\Lti;

use CommonToolkit\Helper\Data\WebLinkHelper;

/**
 * Login-Start durch die Plattform, empfangen vom Tool (Security Framework 5.1.1.1,
 * LTI Core 4.1).
 *
 * Die Anfrage ist **unsigniert**. Nichts daraus ist vertrauenswürdig, bis das
 * `id_token` geprüft ist — insbesondere nicht `target_link_uri`; das Ziel
 * nimmt das Tool am Ende aus dem signierten Claim.
 */
final class LoginInitiation {
    private function __construct(
        public readonly string $issuer,
        public readonly string $loginHint,
        public readonly string $targetLinkUri,
        public readonly ?string $messageHint,
        public readonly ?string $deploymentId,
        public readonly ?string $clientId,
    ) {}

    /**
     * @param  array<mixed>  $parameters  GET- oder POST-Parameter
     *
     * @throws LtiException
     */
    public static function fromParameters(array $parameters): self {
        $issuer = $parameters['iss'] ?? null;
        $loginHint = $parameters['login_hint'] ?? null;
        $target = $parameters['target_link_uri'] ?? null;

        if (!is_string($issuer) || !WebLinkHelper::isAbsoluteIri($issuer)) {
            throw new LtiException(LtiException::INVALID_LOGIN_REQUEST, 'iss');
        }
        if (!is_string($loginHint) || $loginHint === '') {
            throw new LtiException(LtiException::INVALID_LOGIN_REQUEST, 'login_hint');
        }
        if (!is_string($target) || !WebLinkHelper::isAbsoluteIri($target)) {
            throw new LtiException(LtiException::INVALID_LOGIN_REQUEST, 'target_link_uri');
        }

        $deploymentId = self::optional($parameters, 'lti_deployment_id');
        if ($deploymentId !== null && !Ascii::isIdentifier($deploymentId)) {
            throw new LtiException(LtiException::INVALID_LOGIN_REQUEST, 'lti_deployment_id');
        }

        return new self($issuer, $loginHint, $target, self::optional($parameters, 'lti_message_hint'), $deploymentId, self::optional($parameters, 'client_id'));
    }

    /**
     * Plattformseite: Adresse des Login-Starts beim Tool (Security Framework 5.1.1.1,
     * LTI Core 4.1).
     *
     * `login_hint` und `lti_message_hint` sind für das Tool undurchsichtig; die
     * Plattform erkennt sie in der Authentifizierungsanfrage wieder.
     *
     * @throws LtiException
     */
    public static function toolLoginUrl(string $toolLoginUrl, Registration $tool, string $loginHint, string $targetLinkUri, ?string $messageHint = null, ?string $deploymentId = null): string {
        if (!WebLinkHelper::isAbsoluteIri($toolLoginUrl)) {
            throw new LtiException(LtiException::INVALID_LOGIN_REQUEST, 'login url');
        }
        if ($loginHint === '') {
            throw new LtiException(LtiException::INVALID_LOGIN_REQUEST, 'login_hint');
        }
        if (!WebLinkHelper::isAbsoluteIri($targetLinkUri)) {
            throw new LtiException(LtiException::INVALID_LOGIN_REQUEST, 'target_link_uri');
        }
        if ($deploymentId !== null && !$tool->hasDeployment($deploymentId)) {
            throw new LtiException(LtiException::UNKNOWN_DEPLOYMENT, $deploymentId);
        }

        $parameters = [
            'iss' => $tool->issuer,
            'login_hint' => $loginHint,
            'target_link_uri' => $targetLinkUri,
            'client_id' => $tool->clientId,
        ];
        if ($deploymentId !== null) {
            $parameters['lti_deployment_id'] = $deploymentId;
        }
        if ($messageHint !== null && $messageHint !== '') {
            $parameters['lti_message_hint'] = $messageHint;
        }

        return $toolLoginUrl . (str_contains($toolLoginUrl, '?') ? '&' : '?') . http_build_query($parameters, '', '&', PHP_QUERY_RFC3986);
    }

    /**
     * Adresse der Authentifizierungsanfrage an die Plattform (Security Framework 5.1.1.2).
     *
     * `state` und `nonce` erzeugt die Anwendung und bindet sie an die Browser-Sitzung.
     */
    public function authenticationRequestUrl(Registration $platform, string $redirectUri, string $state, string $nonce): string {
        if ($this->issuer !== $platform->issuer) {
            throw new LtiException(LtiException::ISSUER_MISMATCH, $this->issuer);
        }
        if ($this->clientId !== null && $this->clientId !== $platform->clientId) {
            throw new LtiException(LtiException::INVALID_LOGIN_REQUEST, 'client_id');
        }
        if ($platform->authorizationEndpoint === null || $platform->authorizationEndpoint === '') {
            throw new LtiException(LtiException::INVALID_LOGIN_REQUEST, 'authorization endpoint');
        }
        if ($state === '' || $nonce === '') {
            throw new LtiException(LtiException::INVALID_LOGIN_REQUEST, 'state/nonce');
        }

        $parameters = [
            'scope' => 'openid',
            'response_type' => 'id_token',
            'response_mode' => 'form_post',
            'prompt' => 'none',
            'client_id' => $platform->clientId,
            'redirect_uri' => $redirectUri,
            'login_hint' => $this->loginHint,
            'state' => $state,
            'nonce' => $nonce,
        ];
        // lti_message_hint geht unverändert zurück (LTI Core 4.1.1).
        if ($this->messageHint !== null) {
            $parameters['lti_message_hint'] = $this->messageHint;
        }

        $endpoint = $platform->authorizationEndpoint;

        return $endpoint . (str_contains($endpoint, '?') ? '&' : '?') . http_build_query($parameters, '', '&', PHP_QUERY_RFC3986);
    }

    /** @param array<mixed> $parameters */
    private static function optional(array $parameters, string $name): ?string {
        $value = $parameters[$name] ?? null;

        return is_string($value) && $value !== '' ? $value : null;
    }
}
