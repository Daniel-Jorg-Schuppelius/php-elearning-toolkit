<?php
/*
 * Created on   : Mon Sep 14 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : AuthenticationRequest.php
 * License      : Apache-2.0
 * License Uri  : https://www.apache.org/licenses/LICENSE-2.0
 */

declare(strict_types=1);

namespace ELearningToolkit\Lti;

/**
 * Authentifizierungsanfrage des Tools, empfangen von der Plattform
 * (Security Framework 5.1.1.2).
 *
 * Die Plattform prüft `redirect_uri` exakt gegen die Registrierung, bevor sie ein
 * `id_token` herausgibt — sonst ginge der Token an eine Adresse des Angreifers.
 * Ob der angemeldete Mensch zu `login_hint` passt, entscheidet die Anwendung.
 */
final class AuthenticationRequest {
    private function __construct(
        public readonly string $clientId,
        public readonly string $redirectUri,
        public readonly string $loginHint,
        public readonly string $nonce,
        public readonly ?string $state,
        public readonly ?string $messageHint,
    ) {}

    /**
     * @param  array<mixed>  $parameters
     *
     * @throws LtiException
     */
    public static function fromParameters(array $parameters, Registration $tool): self {
        $scope = $parameters['scope'] ?? null;
        if (!is_string($scope) || !in_array('openid', preg_split('/\s+/', trim($scope)) ?: [], true)) {
            throw new LtiException(LtiException::INVALID_AUTHENTICATION_REQUEST, 'scope');
        }

        foreach (['response_type' => 'id_token', 'response_mode' => 'form_post', 'prompt' => 'none'] as $name => $expected) {
            if (($parameters[$name] ?? null) !== $expected) {
                throw new LtiException(LtiException::INVALID_AUTHENTICATION_REQUEST, $name);
            }
        }

        if (($parameters['client_id'] ?? null) !== $tool->clientId) {
            throw new LtiException(LtiException::INVALID_AUTHENTICATION_REQUEST, 'client_id');
        }

        $redirectUri = $parameters['redirect_uri'] ?? null;
        if (!is_string($redirectUri) || !$tool->allowsRedirectUri($redirectUri)) {
            throw new LtiException(LtiException::INVALID_AUTHENTICATION_REQUEST, 'redirect_uri');
        }

        foreach (['login_hint', 'nonce'] as $required) {
            if (!is_string($parameters[$required] ?? null) || $parameters[$required] === '') {
                throw new LtiException(LtiException::INVALID_AUTHENTICATION_REQUEST, $required);
            }
        }

        $state = $parameters['state'] ?? null;
        $hint = $parameters['lti_message_hint'] ?? null;

        return new self(
            $tool->clientId,
            $redirectUri,
            (string) $parameters['login_hint'],
            (string) $parameters['nonce'],
            is_string($state) && $state !== '' ? $state : null,
            is_string($hint) && $hint !== '' ? $hint : null,
        );
    }
}
