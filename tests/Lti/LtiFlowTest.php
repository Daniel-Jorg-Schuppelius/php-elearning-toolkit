<?php
/*
 * Created on   : Mon Sep 14 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : LtiFlowTest.php
 * License      : Apache-2.0
 * License Uri  : https://www.apache.org/licenses/LICENSE-2.0
 */

declare(strict_types=1);

namespace Tests\Lti;

use DateTimeImmutable;
use ELearningToolkit\Lti\{AuthenticationRequest, Claims, ContentItem, DeepLinkingResponse, DeepLinkingSettings, IdTokenBuilder, InMemoryNonceStore, JwtSigner, Keys, LaunchValidator, LoginInitiation, LtiException, MessageType, Registration, Roles};
use Jose\Component\Core\JWK;
use PHPUnit\Framework\TestCase;

/** Der ganze Weg: Plattform → Tool beim Start, Tool → Plattform beim Deep Linking. */
final class LtiFlowTest extends TestCase {
    private const PLATFORM = 'https://plattform.example.org';
    private const CLIENT = 'tool-client-7';
    private const DEPLOYMENT = 'deployment-1';
    private const REDIRECT = 'https://tool.example.org/lti/launch';
    private const TARGET = 'https://tool.example.org/kurse/brandschutz';

    private static ?JWK $platformKey = null;

    private static ?JWK $toolKey = null;

    private static function platformKey(): JWK {
        return self::$platformKey ??= Keys::generate('plattform-1');
    }

    private static function toolKey(): JWK {
        return self::$toolKey ??= Keys::generate('tool-1');
    }

    private function now(): DateTimeImmutable {
        return new DateTimeImmutable('@1789372800');
    }

    /** Die Plattform, wie das Tool sie registriert hat. */
    private function platformAsSeenByTool(): Registration {
        return new Registration(self::PLATFORM, self::CLIENT, Keys::keySet(Keys::publicKeySet([self::platformKey()])), [self::DEPLOYMENT], self::PLATFORM . '/lti/auth');
    }

    /** Das Tool, wie die Plattform es registriert hat. */
    private function toolAsSeenByPlatform(): Registration {
        return new Registration(self::PLATFORM, self::CLIENT, Keys::keySet(Keys::publicKeySet([self::toolKey()])), [self::DEPLOYMENT], null, [self::REDIRECT]);
    }

    /** @param array<string, string> $overrides */
    private function authenticationRequest(array $overrides = []): AuthenticationRequest {
        return AuthenticationRequest::fromParameters($overrides + [
            'scope' => 'openid',
            'response_type' => 'id_token',
            'response_mode' => 'form_post',
            'prompt' => 'none',
            'client_id' => self::CLIENT,
            'redirect_uri' => self::REDIRECT,
            'login_hint' => 'person-17',
            'state' => 'zustand-1',
            'nonce' => 'nonce-1',
        ], $this->toolAsSeenByPlatform());
    }

    private function reasonOf(callable $action): string {
        try {
            $action();
        } catch (LtiException $e) {
            return $e->reason;
        }

        $this->fail('Erwartete LtiException blieb aus.');
    }

    public function test_login_initiation_leads_to_the_authentication_request(): void {
        $login = LoginInitiation::fromParameters([
            'iss' => self::PLATFORM,
            'login_hint' => 'person-17',
            'target_link_uri' => self::TARGET,
            'lti_message_hint' => 'kurs-42',
            'lti_deployment_id' => self::DEPLOYMENT,
            'client_id' => self::CLIENT,
        ]);

        $url = $login->authenticationRequestUrl($this->platformAsSeenByTool(), self::REDIRECT, 'zustand-1', 'nonce-1');
        [$endpoint, $query] = explode('?', $url, 2);
        parse_str($query, $parameters);

        $this->assertSame(self::PLATFORM . '/lti/auth', $endpoint);
        $this->assertSame('openid', $parameters['scope']);
        $this->assertSame('id_token', $parameters['response_type']);
        $this->assertSame('form_post', $parameters['response_mode']);
        $this->assertSame('none', $parameters['prompt']);
        $this->assertSame('kurs-42', $parameters['lti_message_hint'], 'Der Nachrichtenhinweis geht unverändert zurück.');
        $this->assertSame('nonce-1', $parameters['nonce']);

        $this->assertSame(LtiException::INVALID_LOGIN_REQUEST, $this->reasonOf(fn () => LoginInitiation::fromParameters(['iss' => self::PLATFORM, 'login_hint' => 'x'])));
        $this->assertSame(LtiException::ISSUER_MISMATCH, $this->reasonOf(fn () => LoginInitiation::fromParameters(['iss' => 'https://fremd.example.org', 'login_hint' => 'x', 'target_link_uri' => self::TARGET])
            ->authenticationRequestUrl($this->platformAsSeenByTool(), self::REDIRECT, 's', 'n')));
    }

    public function test_the_platform_builds_the_login_initiation_the_tool_accepts(): void {
        $url = LoginInitiation::toolLoginUrl('https://tool.example.org/lti/login?quelle=1', $this->toolAsSeenByPlatform(), 'person-17', self::TARGET, 'link-3', self::DEPLOYMENT);

        $this->assertStringStartsWith('https://tool.example.org/lti/login?quelle=1&', $url);
        parse_str((string) parse_url($url, PHP_URL_QUERY), $query);
        $login = LoginInitiation::fromParameters($query);

        $this->assertSame(self::PLATFORM, $login->issuer);
        $this->assertSame('person-17', $login->loginHint);
        $this->assertSame(self::TARGET, $login->targetLinkUri);
        $this->assertSame('link-3', $login->messageHint);
        $this->assertSame(self::DEPLOYMENT, $login->deploymentId);
        $this->assertSame(self::CLIENT, $login->clientId);

        $this->assertSame(LtiException::UNKNOWN_DEPLOYMENT, $this->reasonOf(fn () => LoginInitiation::toolLoginUrl('https://tool.example.org/lti/login', $this->toolAsSeenByPlatform(), 'person-17', self::TARGET, null, 'fremd')));
        $this->assertSame(LtiException::INVALID_LOGIN_REQUEST, $this->reasonOf(fn () => LoginInitiation::toolLoginUrl('https://tool.example.org/lti/login', $this->toolAsSeenByPlatform(), '', self::TARGET)));
        $this->assertSame(LtiException::INVALID_LOGIN_REQUEST, $this->reasonOf(fn () => LoginInitiation::toolLoginUrl('tool/login', $this->toolAsSeenByPlatform(), 'person-17', self::TARGET)));
    }

    public function test_the_platform_only_answers_registered_redirect_uris(): void {
        $this->assertSame(LtiException::INVALID_AUTHENTICATION_REQUEST, $this->reasonOf(fn () => $this->authenticationRequest(['redirect_uri' => 'https://angreifer.example.org/fang'])));
        $this->assertSame(LtiException::INVALID_AUTHENTICATION_REQUEST, $this->reasonOf(fn () => $this->authenticationRequest(['redirect_uri' => self::REDIRECT . '/unterpfad'])), 'Kein Präfixvergleich.');
        $this->assertSame(LtiException::INVALID_AUTHENTICATION_REQUEST, $this->reasonOf(fn () => $this->authenticationRequest(['prompt' => 'login'])));
        $this->assertSame(LtiException::INVALID_AUTHENTICATION_REQUEST, $this->reasonOf(fn () => $this->authenticationRequest(['client_id' => 'anderes-tool'])));
    }

    public function test_resource_link_launch_round_trip(): void {
        $request = $this->authenticationRequest();
        $token = IdTokenBuilder::resourceLink(self::PLATFORM, $this->toolAsSeenByPlatform(), self::DEPLOYMENT, $request, 'person-17', [Roles::LEARNER], self::TARGET, 'link-1', self::platformKey(), $this->now(), [
            'name' => 'Erika Muster',
            Claims::CONTEXT => ['id' => 'kurs-42', 'title' => 'Arbeitsschutz'],
            'iss' => 'https://versuch-zu-überschreiben.example.org',
        ], 'Brandschutz');

        $this->assertSame(['id_token' => $token, 'state' => 'zustand-1'], IdTokenBuilder::responseFields($token, $request));

        $validator = new LaunchValidator(new InMemoryNonceStore);
        $launch = $validator->validate($token, $this->platformAsSeenByTool(), $this->now(), 'nonce-1', self::TARGET);

        $this->assertSame(MessageType::ResourceLinkRequest, $launch->type);
        $this->assertSame(self::PLATFORM, $launch->issuer, 'Pflicht-Claims lassen sich nicht überschreiben.');
        $this->assertSame(self::DEPLOYMENT, $launch->deploymentId);
        $this->assertSame('person-17', $launch->subject);
        $this->assertSame([Roles::LEARNER], $launch->roles);
        $this->assertSame('link-1', $launch->resourceLinkId);
        $this->assertSame('kurs-42', $launch->context()['id']);
        $this->assertSame('Erika Muster', $launch->claims['name']);

        $this->assertSame(LtiException::NONCE_REUSED, $this->reasonOf(fn () => $validator->validate($token, $this->platformAsSeenByTool(), $this->now(), 'nonce-1', self::TARGET)), 'Ein Token gilt nur einmal.');
    }

    public function test_launch_validation_rejects_what_the_spec_rejects(): void {
        $platform = $this->platformAsSeenByTool();
        $iat = $this->now()->getTimestamp();
        $claims = [
            'iss' => self::PLATFORM, 'aud' => self::CLIENT, 'iat' => $iat, 'exp' => $iat + 300, 'nonce' => 'n', 'sub' => 'p',
            Claims::MESSAGE_TYPE => 'LtiResourceLinkRequest', Claims::VERSION => '1.3.0', Claims::DEPLOYMENT_ID => self::DEPLOYMENT,
            Claims::TARGET_LINK_URI => self::TARGET, Claims::RESOURCE_LINK => ['id' => 'link-1'], Claims::ROLES => [],
        ];
        $reason = function (array $changes, ?string $expectedTarget = null) use ($claims, $platform): string {
            $token = JwtSigner::sign(array_merge($claims, $changes), self::platformKey());

            return $this->reasonOf(fn () => (new LaunchValidator(new InMemoryNonceStore))->validate($token, $platform, $this->now(), null, $expectedTarget));
        };

        $this->assertSame(LtiException::ISSUER_MISMATCH, $reason(['iss' => 'https://fremd.example.org']));
        $this->assertSame(LtiException::AUDIENCE_MISMATCH, $reason(['aud' => [self::CLIENT, 'fremdes-tool']]), 'Nicht vertrauenswürdige Zusatzempfänger werden abgelehnt.');
        $this->assertSame(LtiException::AUDIENCE_MISMATCH, $reason(['azp' => 'fremdes-tool']));
        $this->assertSame(LtiException::UNSUPPORTED_VERSION, $reason([Claims::VERSION => '1.1']));
        $this->assertSame(LtiException::UNKNOWN_DEPLOYMENT, $reason([Claims::DEPLOYMENT_ID => 'unbekannt']));
        $this->assertSame(LtiException::UNSUPPORTED_MESSAGE_TYPE, $reason([Claims::MESSAGE_TYPE => 'LtiDeepLinkingResponse']));
        $this->assertSame(LtiException::MISSING_CLAIM, $reason([Claims::RESOURCE_LINK => []]));
        $this->assertSame(LtiException::INVALID_CLAIM, $reason(['sub' => str_repeat('x', 256)]));
        $this->assertSame(LtiException::INVALID_CLAIM, $reason([], 'https://tool.example.org/anderes-ziel'), 'target_link_uri muss dem Login-Start entsprechen.');

        $anonymous = $claims;
        unset($anonymous['sub']);
        $launch = (new LaunchValidator(new InMemoryNonceStore))->validate(JwtSigner::sign($anonymous, self::platformKey()), $platform, $this->now());
        $this->assertTrue($launch->isAnonymous());
    }

    public function test_deep_linking_round_trip(): void {
        $settings = new DeepLinkingSettings('https://plattform.example.org/deep-links/return', ['ltiResourceLink'], ['iframe', 'window'], acceptMultiple: false, data: 'auswahl-99');
        $request = $this->authenticationRequest(['nonce' => 'nonce-dl']);
        $token = IdTokenBuilder::deepLinking(self::PLATFORM, $this->toolAsSeenByPlatform(), self::DEPLOYMENT, $request, 'person-17', [Roles::INSTRUCTOR], $settings, 'https://tool.example.org/deep-link', self::platformKey(), $this->now());

        $launch = (new LaunchValidator(new InMemoryNonceStore))->validate($token, $this->platformAsSeenByTool(), $this->now(), 'nonce-dl');
        $this->assertSame(MessageType::DeepLinkingRequest, $launch->type);
        $this->assertNotNull($launch->deepLinkingSettings);
        $this->assertSame('auswahl-99', $launch->deepLinkingSettings->data);

        $response = DeepLinkingResponse::build($this->platformAsSeenByTool(), self::DEPLOYMENT, $launch->deepLinkingSettings, [
            ContentItem::ltiResourceLink(self::TARGET, 'Brandschutz', null, ['kurs' => '42']),
        ], self::toolKey(), 'antwort-nonce', $this->now(), message: 'Kurs verknüpft');

        $this->assertSame('https://plattform.example.org/deep-links/return', $response['returnUrl']);
        $this->assertSame('JWT', DeepLinkingResponse::FORM_PARAMETER);

        $result = DeepLinkingResponse::validate($response['jwt'], $this->toolAsSeenByPlatform(), self::PLATFORM, self::DEPLOYMENT, $settings, new InMemoryNonceStore, $this->now());

        $this->assertSame([['type' => 'ltiResourceLink', 'url' => self::TARGET, 'title' => 'Brandschutz', 'custom' => ['kurs' => '42']]], $result['items']);
        $this->assertSame('Kurs verknüpft', $result['message']);
    }

    public function test_deep_linking_enforces_types_multiplicity_and_data(): void {
        $settings = new DeepLinkingSettings('https://plattform.example.org/deep-links/return', ['ltiResourceLink'], ['iframe'], acceptMultiple: false, data: 'auswahl-99');
        $platform = $this->platformAsSeenByTool();

        $this->assertSame(LtiException::INVALID_CLAIM, $this->reasonOf(fn () => DeepLinkingResponse::build($platform, self::DEPLOYMENT, $settings, [ContentItem::html('<p>x</p>')], self::toolKey(), 'n', $this->now())));
        $this->assertSame(LtiException::INVALID_CLAIM, $this->reasonOf(fn () => DeepLinkingResponse::build($platform, self::DEPLOYMENT, $settings, [ContentItem::ltiResourceLink(), ContentItem::ltiResourceLink()], self::toolKey(), 'n', $this->now())));

        // Ein Tool, das data verändert, fliegt auf der Plattformseite auf.
        $iat = $this->now()->getTimestamp();
        $forged = JwtSigner::sign([
            'iss' => self::CLIENT, 'aud' => self::PLATFORM, 'iat' => $iat, 'exp' => $iat + 300, 'nonce' => 'n2',
            Claims::MESSAGE_TYPE => 'LtiDeepLinkingResponse', Claims::VERSION => '1.3.0', Claims::DEPLOYMENT_ID => self::DEPLOYMENT,
            Claims::DL_DATA => 'manipuliert', Claims::DL_CONTENT_ITEMS => [],
        ], self::toolKey());
        $this->assertSame(LtiException::INVALID_CLAIM, $this->reasonOf(fn () => DeepLinkingResponse::validate($forged, $this->toolAsSeenByPlatform(), self::PLATFORM, self::DEPLOYMENT, $settings, new InMemoryNonceStore, $this->now())));

        // Mit dem Plattformschlüssel signiert statt mit dem des Tools: unbekannter Schlüssel.
        $wrongKey = JwtSigner::sign(['iss' => self::CLIENT, 'aud' => self::PLATFORM, 'iat' => $iat, 'exp' => $iat + 300], self::platformKey());
        $this->assertSame(LtiException::UNKNOWN_KEY, $this->reasonOf(fn () => DeepLinkingResponse::validate($wrongKey, $this->toolAsSeenByPlatform(), self::PLATFORM, self::DEPLOYMENT, $settings, new InMemoryNonceStore, $this->now())));
    }
}
