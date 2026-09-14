<?php
/*
 * Created on   : Mon Sep 14 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : LearnerPreferences.php
 * License      : Apache-2.0
 * License Uri  : https://www.apache.org/licenses/LICENSE-2.0
 */

declare(strict_types=1);

namespace ELearningToolkit\Cmi5;

/** Das Agent-Profil {@see Cmi5::PROFILE_LEARNER_PREFERENCES} (cmi5 11). */
final class LearnerPreferences {
    /**
     * @param  list<string>  $languages  Sprachen nach RFC 5646, in Reihenfolge der Vorliebe
     * @return array{languagePreference: string, audioPreference: string}
     *
     * @throws Cmi5Exception bei einer ungültigen Sprachkennung
     */
    public static function document(array $languages, ?bool $audioOn): array {
        foreach ($languages as $language) {
            if (preg_match('/^[a-z]{2,8}(-[a-z0-9]{1,8})*$/iD', $language) !== 1) {
                throw new Cmi5Exception(Cmi5Exception::INVALID_LANGUAGE, $language);
            }
        }

        return [
            'languagePreference' => implode(',', $languages),
            'audioPreference' => $audioOn === null ? '' : ($audioOn ? 'on' : 'off'),
        ];
    }

    private function __construct() {}
}
