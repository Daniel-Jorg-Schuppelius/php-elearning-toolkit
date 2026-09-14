<?php
/*
 * Created on   : Mon Sep 14 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : FetchResponseTest.php
 * License      : Apache-2.0
 * License Uri  : https://www.apache.org/licenses/LICENSE-2.0
 */

declare(strict_types=1);

namespace Tests\Cmi5;

use ELearningToolkit\Cmi5\{FetchError, FetchResponse};
use PHPUnit\Framework\TestCase;

final class FetchResponseTest extends TestCase {
    public function test_token_and_error_bodies_follow_the_spec(): void {
        $this->assertSame(['auth-token' => 'QWxhZGRpbjpvcGVuIHNlc2FtZQ=='], FetchResponse::token('QWxhZGRpbjpvcGVuIHNlc2FtZQ=='));
        $this->assertSame(
            ['error-code' => '1', 'error-text' => 'Der Token wurde bereits ausgegeben.'],
            FetchResponse::error(FetchError::AlreadyInUseOrExpired, 'Der Token wurde bereits ausgegeben.'),
        );
        $this->assertSame('2', FetchError::SecurityError->value);
        $this->assertSame('3', FetchError::ApplicationError->value);
    }
}
