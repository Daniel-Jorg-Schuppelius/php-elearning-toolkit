<?php
/*
 * Created on   : Mon Sep 14 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : Registration.php
 * License      : Apache-2.0
 * License Uri  : https://www.apache.org/licenses/LICENSE-2.0
 */

declare(strict_types=1);

namespace ELearningToolkit\Lti;

use Jose\Component\Core\JWKSet;

/**
 * Registrierung zwischen einer Plattform (Issuer) und einem Tool (Client-ID).
 *
 * Dieselbe Beziehung, von beiden Seiten gesehen:
 * - Auf der **Toolseite** beschreibt sie die Plattform: deren Issuer, die eigene
 *   Client-ID, die öffentlichen Schlüssel und den Autorisierungsendpunkt der
 *   Plattform sowie die bekannten Deployments.
 * - Auf der **Plattformseite** beschreibt sie das Tool: den eigenen Issuer, die
 *   Client-ID des Tools, dessen öffentliche Schlüssel, seine Redirect-URIs und
 *   die Deployments.
 */
final class Registration {
    /**
     * @param  list<string>  $deploymentIds
     * @param  list<string>  $redirectUris
     */
    public function __construct(
        public readonly string $issuer,
        public readonly string $clientId,
        /** Öffentliche Schlüssel der Gegenseite. */
        public readonly JWKSet $keySet,
        public readonly array $deploymentIds = [],
        public readonly ?string $authorizationEndpoint = null,
        public readonly array $redirectUris = [],
    ) {}

    public function hasDeployment(string $deploymentId): bool {
        return in_array($deploymentId, $this->deploymentIds, true);
    }

    /** Redirect-URIs werden exakt verglichen, nie per Präfix. */
    public function allowsRedirectUri(string $uri): bool {
        return in_array($uri, $this->redirectUris, true);
    }
}
