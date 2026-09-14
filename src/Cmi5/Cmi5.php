<?php
/*
 * Created on   : Mon Sep 14 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : Cmi5.php
 * License      : Apache-2.0
 * License Uri  : https://www.apache.org/licenses/LICENSE-2.0
 */

declare(strict_types=1);

namespace ELearningToolkit\Cmi5;

/**
 * Feste Kennungen aus der cmi5-Spezifikation (Quartz).
 *
 * Abgeglichen am 2026-09-14 gegen `AICC/CMI-5_Spec_Current`, Zweig `quartz`.
 * Ein falscher IRI bricht die Kompatibilität still — hier nichts von Hand ändern,
 * ohne die Spezifikation daneben zu legen.
 */
final class Cmi5 {
    /** Namensraum der Kursstruktur `cmi5.xml`. */
    public const COURSE_STRUCTURE_NAMESPACE = 'https://w3id.org/xapi/profiles/cmi5/v1/CourseStructure.xsd';

    /** Kategorie, an der ein „cmi5 defined" Statement erkennbar ist. */
    public const CATEGORY_CMI5 = 'https://w3id.org/xapi/cmi5/context/categories/cmi5';

    /** Kategorie für Statements, die in die moveOn-Auswertung eingehen. */
    public const CATEGORY_MOVE_ON = 'https://w3id.org/xapi/cmi5/context/categories/moveon';

    public const EXTENSION_SESSION_ID = 'https://w3id.org/xapi/cmi5/context/extensions/sessionid';
    public const EXTENSION_MASTERY_SCORE = 'https://w3id.org/xapi/cmi5/context/extensions/masteryscore';
    public const EXTENSION_LAUNCH_MODE = 'https://w3id.org/xapi/cmi5/context/extensions/launchmode';
    public const EXTENSION_LAUNCH_URL = 'https://w3id.org/xapi/cmi5/context/extensions/launchurl';
    public const EXTENSION_MOVE_ON = 'https://w3id.org/xapi/cmi5/context/extensions/moveon';
    public const EXTENSION_LAUNCH_PARAMETERS = 'https://w3id.org/xapi/cmi5/context/extensions/launchparameters';

    public const RESULT_EXTENSION_REASON = 'https://w3id.org/xapi/cmi5/result/extensions/reason';
    public const RESULT_EXTENSION_PROGRESS = 'https://w3id.org/xapi/cmi5/result/extensions/progress';

    public const ACTIVITY_TYPE_COURSE = 'https://w3id.org/xapi/cmi5/activitytype/course';
    public const ACTIVITY_TYPE_BLOCK = 'https://w3id.org/xapi/cmi5/activitytype/block';

    /** State-ID des Startdokuments, das das LMS vor jedem Start schreibt. */
    public const STATE_LAUNCH_DATA = 'LMS.LaunchData';

    /** Agent-Profil mit den Lernpräferenzen. */
    public const PROFILE_LEARNER_PREFERENCES = 'cmi5LearnerPreferences';

    /** Namen der Startparameter in der Abfrage der AU-Adresse. */
    public const LAUNCH_PARAMETERS = ['endpoint', 'fetch', 'actor', 'registration', 'activityId'];

    private function __construct() {}
}
