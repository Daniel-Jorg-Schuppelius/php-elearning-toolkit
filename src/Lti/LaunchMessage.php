<?php
/*
 * Created on   : Mon Sep 14 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : LaunchMessage.php
 * License      : Apache-2.0
 * License Uri  : https://www.apache.org/licenses/LICENSE-2.0
 */

declare(strict_types=1);

namespace ELearningToolkit\Lti;

/** Ein geprüfter Start, wie ihn das Tool nach der Launch-Prüfung sieht. */
final class LaunchMessage {
    /**
     * @param  list<string>  $roles
     * @param  array<string, mixed>  $claims
     */
    public function __construct(
        public readonly MessageType $type,
        public readonly string $issuer,
        public readonly string $deploymentId,
        /** `null` beim anonymen Start. */
        public readonly ?string $subject,
        public readonly array $roles,
        public readonly ?string $targetLinkUri,
        public readonly ?string $resourceLinkId,
        public readonly ?DeepLinkingSettings $deepLinkingSettings,
        public readonly array $claims,
    ) {}

    public function isAnonymous(): bool {
        return $this->subject === null;
    }

    /** @return array<string, mixed> */
    public function context(): array {
        $context = $this->claims[Claims::CONTEXT] ?? null;

        return is_array($context) ? $context : [];
    }

    /** @return array<string, mixed> */
    public function custom(): array {
        $custom = $this->claims[Claims::CUSTOM] ?? null;

        return is_array($custom) ? $custom : [];
    }
}
