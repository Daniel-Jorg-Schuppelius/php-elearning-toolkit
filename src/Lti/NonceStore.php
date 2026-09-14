<?php
/*
 * Created on   : Mon Sep 14 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : NonceStore.php
 * License      : Apache-2.0
 * License Uri  : https://www.apache.org/licenses/LICENSE-2.0
 */

declare(strict_types=1);

namespace ELearningToolkit\Lti;

use DateTimeImmutable;

/**
 * Speicher für einmal verwendbare Nonces.
 *
 * Eine Nonce darf nur einmal angenommen werden, sonst lässt sich ein
 * mitgeschnittenes `id_token` erneut einspielen. Die Anwendung stellt den
 * Speicher, das Toolkit hält keinen Zustand.
 */
interface NonceStore {
    /**
     * Nonce verbrauchen.
     *
     * @return bool `false`, wenn die Nonce schon verbraucht war
     */
    public function consume(string $nonce, DateTimeImmutable $expiresAt): bool;
}
