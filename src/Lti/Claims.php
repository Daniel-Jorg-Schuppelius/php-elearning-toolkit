<?php
/*
 * Created on   : Mon Sep 14 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : Claims.php
 * License      : Apache-2.0
 * License Uri  : https://www.apache.org/licenses/LICENSE-2.0
 */

declare(strict_types=1);

namespace ELearningToolkit\Lti;

/**
 * Claim-Namen aus LTI 1.3 Core und Deep Linking 2.0.
 *
 * Abgeglichen am 2026-09-14 gegen die Spezifikationen bei 1EdTech
 * (`spec/lti/v1p3`, `spec/lti-dl/v2p0`).
 */
final class Claims {
    public const LTI_VERSION = '1.3.0';

    public const MESSAGE_TYPE = 'https://purl.imsglobal.org/spec/lti/claim/message_type';
    public const VERSION = 'https://purl.imsglobal.org/spec/lti/claim/version';
    public const DEPLOYMENT_ID = 'https://purl.imsglobal.org/spec/lti/claim/deployment_id';
    public const TARGET_LINK_URI = 'https://purl.imsglobal.org/spec/lti/claim/target_link_uri';
    public const RESOURCE_LINK = 'https://purl.imsglobal.org/spec/lti/claim/resource_link';
    public const ROLES = 'https://purl.imsglobal.org/spec/lti/claim/roles';
    public const CONTEXT = 'https://purl.imsglobal.org/spec/lti/claim/context';
    public const TOOL_PLATFORM = 'https://purl.imsglobal.org/spec/lti/claim/tool_platform';
    public const LAUNCH_PRESENTATION = 'https://purl.imsglobal.org/spec/lti/claim/launch_presentation';
    public const LIS = 'https://purl.imsglobal.org/spec/lti/claim/lis';
    public const CUSTOM = 'https://purl.imsglobal.org/spec/lti/claim/custom';
    public const ROLE_SCOPE_MENTOR = 'https://purl.imsglobal.org/spec/lti/claim/role_scope_mentor';

    public const DL_SETTINGS = 'https://purl.imsglobal.org/spec/lti-dl/claim/deep_linking_settings';
    public const DL_CONTENT_ITEMS = 'https://purl.imsglobal.org/spec/lti-dl/claim/content_items';
    public const DL_DATA = 'https://purl.imsglobal.org/spec/lti-dl/claim/data';
    public const DL_MSG = 'https://purl.imsglobal.org/spec/lti-dl/claim/msg';
    public const DL_LOG = 'https://purl.imsglobal.org/spec/lti-dl/claim/log';
    public const DL_ERROR_MSG = 'https://purl.imsglobal.org/spec/lti-dl/claim/errormsg';
    public const DL_ERROR_LOG = 'https://purl.imsglobal.org/spec/lti-dl/claim/errorlog';

    private function __construct() {}
}
