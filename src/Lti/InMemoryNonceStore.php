<?php
/*
 * Created on   : Mon Sep 14 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : InMemoryNonceStore.php
 * License      : Apache-2.0
 * License Uri  : https://www.apache.org/licenses/LICENSE-2.0
 */

declare(strict_types=1);

namespace ELearningToolkit\Lti;

use DateTimeImmutable;

/** Nonce-Speicher für Tests und Kurzläufer; im Betrieb gehört die Ablage in einen geteilten Speicher. */
final class InMemoryNonceStore implements NonceStore {
    /** @var array<string, int> Nonce → Ablaufzeitpunkt */
    private array $used = [];

    public function consume(string $nonce, DateTimeImmutable $expiresAt): bool {
        if (isset($this->used[$nonce])) {
            return false;
        }

        $this->used[$nonce] = $expiresAt->getTimestamp();

        return true;
    }
}
