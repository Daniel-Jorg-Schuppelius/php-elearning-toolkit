<?php
/*
 * Created on   : Mon Sep 14 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : Verbs.php
 * License      : Apache-2.0
 * License Uri  : https://www.apache.org/licenses/LICENSE-2.0
 */

declare(strict_types=1);

namespace ELearningToolkit\XApi;

/** Verb-IRIs, die Fortschritt oder den cmi5-Ablauf betreffen. */
final class Verbs {
    public const COMPLETED = 'http://adlnet.gov/expapi/verbs/completed';
    public const PASSED = 'http://adlnet.gov/expapi/verbs/passed';
    public const FAILED = 'http://adlnet.gov/expapi/verbs/failed';
    public const ATTEMPTED = 'http://adlnet.gov/expapi/verbs/attempted';
    public const EXPERIENCED = 'http://adlnet.gov/expapi/verbs/experienced';
    public const VOIDED = 'http://adlnet.gov/expapi/verbs/voided';

    // Von cmi5 definierte Verben.
    public const LAUNCHED = 'http://adlnet.gov/expapi/verbs/launched';
    public const INITIALIZED = 'http://adlnet.gov/expapi/verbs/initialized';
    public const TERMINATED = 'http://adlnet.gov/expapi/verbs/terminated';
    public const ABANDONED = 'https://w3id.org/xapi/adl/verbs/abandoned';
    public const WAIVED = 'https://w3id.org/xapi/adl/verbs/waived';
    public const SATISFIED = 'https://w3id.org/xapi/adl/verbs/satisfied';

    /** Abschlussverb aus dem DoD-ISD-Profil, das einige Autorenwerkzeuge senden. */
    public const DOD_ISD_COMPLETED = 'https://w3id.org/xapi/dod-isd/verbs/completed';

    private function __construct() {}
}
