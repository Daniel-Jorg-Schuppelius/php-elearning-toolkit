<?php
/*
 * Created on   : Mon Sep 14 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : LaunchPathTest.php
 * License      : Apache-2.0
 * License Uri  : https://www.apache.org/licenses/LICENSE-2.0
 */

declare(strict_types=1);

namespace Tests\Scorm;

use ELearningToolkit\Scorm\LaunchPath;
use PHPUnit\Framework\TestCase;

final class LaunchPathTest extends TestCase {
    public function test_encodes_each_segment_and_keeps_slashes(): void {
        $this->assertSame('scormcontent/index%20neu.html', LaunchPath::encode('/scormcontent/index neu.html'));
    }

    public function test_keeps_the_query_as_the_manifest_names_it(): void {
        $this->assertSame('start.html?lang=de&x=1', LaunchPath::encode('start.html?lang=de&x=1'));
    }
}
