<?php
/*
 * Created on   : Mon Sep 14 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : JwtTest.php
 * License      : Apache-2.0
 * License Uri  : https://www.apache.org/licenses/LICENSE-2.0
 */

declare(strict_types=1);

namespace Tests\Lti;

use DateTimeImmutable;
use ELearningToolkit\Lti\{JwtSigner, JwtVerifier, Keys, LtiException, Roles};
use Jose\Component\Core\JWK;
use PHPUnit\Framework\TestCase;

final class JwtTest extends TestCase {
    private static ?JWK $key = null;

    private static ?JWK $otherKey = null;

    private static function key(): JWK {
        return self::$key ??= Keys::generate('schluessel-2026-09');
    }

    private static function otherKey(): JWK {
        return self::$otherKey ??= Keys::generate('fremd');
    }

    private function now(): DateTimeImmutable {
        return new DateTimeImmutable('@1789372800');
    }

    /** @return array<string, mixed> */
    private function claims(int $iatOffset = 0, int $ttl = 300): array {
        $iat = $this->now()->getTimestamp() + $iatOffset;

        return ['iss' => 'https://plattform.example.org', 'aud' => 'client-1', 'iat' => $iat, 'exp' => $iat + $ttl, 'nonce' => 'n-1'];
    }

    private function reasonOf(callable $action): string {
        try {
            $action();
        } catch (LtiException $e) {
            return $e->reason;
        }

        $this->fail('Erwartete LtiException blieb aus.');
    }

    public function test_a_signed_token_verifies_against_the_public_key_set(): void {
        $jwt = JwtSigner::sign($this->claims(), self::key());
        $keys = Keys::keySet(Keys::publicKeySet([self::key(), self::otherKey()]));

        $claims = (new JwtVerifier)->verify($jwt, $keys, $this->now());

        $this->assertSame('https://plattform.example.org', $claims['iss']);
        $header = json_decode((string) base64_decode(strtr(explode('.', $jwt)[0], '-_', '+/')), true);
        $this->assertSame(['alg' => 'RS256', 'kid' => 'schluessel-2026-09', 'typ' => 'JWT'], $header);
    }

    public function test_the_public_key_set_never_contains_private_parts(): void {
        $set = Keys::publicKeySet([self::key()]);

        $this->assertCount(1, $set['keys']);
        foreach (['d', 'p', 'q', 'dp', 'dq', 'qi'] as $private) {
            $this->assertArrayNotHasKey($private, $set['keys'][0]);
        }
        $this->assertSame('schluessel-2026-09', $set['keys'][0]['kid']);
    }

    public function test_tampering_and_unknown_keys_are_rejected(): void {
        $jwt = JwtSigner::sign($this->claims(), self::key());
        [$header, , $signature] = explode('.', $jwt);
        $forged = $header . '.' . rtrim(strtr(base64_encode((string) json_encode($this->claims() + ['sub' => 'admin'])), '+/', '-_'), '=') . '.' . $signature;

        $keys = Keys::keySet(Keys::publicKeySet([self::key()]));
        $this->assertSame(LtiException::INVALID_SIGNATURE, $this->reasonOf(fn () => (new JwtVerifier)->verify($forged, $keys, $this->now())));

        $onlyOther = Keys::keySet(Keys::publicKeySet([self::otherKey()]));
        $this->assertSame(LtiException::UNKNOWN_KEY, $this->reasonOf(fn () => (new JwtVerifier)->verify($jwt, $onlyOther, $this->now())));

        $this->assertSame(LtiException::MALFORMED_TOKEN, $this->reasonOf(fn () => (new JwtVerifier)->verify('kein.jwt', $keys, $this->now())));
    }

    public function test_none_and_symmetric_algorithms_are_rejected_before_any_key_is_used(): void {
        $keys = Keys::keySet(Keys::publicKeySet([self::key()]));
        $encode = static fn (array $part): string => rtrim(strtr(base64_encode((string) json_encode($part)), '+/', '-_'), '=');

        foreach (['none', 'HS256'] as $alg) {
            $token = $encode(['alg' => $alg, 'kid' => 'schluessel-2026-09']) . '.' . $encode($this->claims()) . '.' . $encode(['x']);
            $this->assertSame(LtiException::UNSUPPORTED_ALGORITHM, $this->reasonOf(fn () => (new JwtVerifier)->verify($token, $keys, $this->now())), $alg);
        }
    }

    public function test_lifetime_is_checked_with_leeway(): void {
        $keys = Keys::keySet(Keys::publicKeySet([self::key()]));
        $verifier = new JwtVerifier(leewaySeconds: 60);

        $this->assertSame(LtiException::EXPIRED, $this->reasonOf(fn () => $verifier->verify(JwtSigner::sign($this->claims(-1000, 300), self::key()), $keys, $this->now())));
        $this->assertSame(LtiException::ISSUED_IN_FUTURE, $this->reasonOf(fn () => $verifier->verify(JwtSigner::sign($this->claims(600), self::key()), $keys, $this->now())));

        // Innerhalb der Toleranz: 30 Sekunden abgelaufen gilt noch.
        $verifier->verify(JwtSigner::sign($this->claims(-330, 300), self::key()), $keys, $this->now());
        $this->addToAssertionCount(1);
    }

    public function test_roles_match_principal_and_sub_roles(): void {
        $this->assertTrue(Roles::includesAny([Roles::LEARNER], Roles::LEARNER));
        $this->assertTrue(Roles::includesAny(['http://purl.imsglobal.org/vocab/lis/v2/membership/Instructor#TeachingAssistant'], Roles::INSTRUCTOR));
        $this->assertFalse(Roles::includesAny(['Learner'], Roles::LEARNER), 'Kurzformen sind in LTI 1.3 nicht gleichwertig.');
        $this->assertFalse(Roles::includesAny([Roles::LEARNER], Roles::INSTRUCTOR, Roles::ADMINISTRATOR));
    }
}
