<?php
/*
 * Created on   : Mon Sep 14 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : MessageType.php
 * License      : Apache-2.0
 * License Uri  : https://www.apache.org/licenses/LICENSE-2.0
 */

declare(strict_types=1);

namespace ELearningToolkit\Lti;

enum MessageType: string {
    case ResourceLinkRequest = 'LtiResourceLinkRequest';
    case DeepLinkingRequest = 'LtiDeepLinkingRequest';
    case DeepLinkingResponse = 'LtiDeepLinkingResponse';
}
