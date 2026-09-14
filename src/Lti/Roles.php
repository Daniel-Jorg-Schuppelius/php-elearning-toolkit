<?php
/*
 * Created on   : Mon Sep 14 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : Roles.php
 * License      : Apache-2.0
 * License Uri  : https://www.apache.org/licenses/LICENSE-2.0
 */

declare(strict_types=1);

namespace ELearningToolkit\Lti;

/**
 * Kontextrollen aus dem LIS-Vokabular (LTI 1.3 Core, Anhang A.2.3).
 *
 * LTI 1.3 verlangt die vollständigen URIs; Kurzformen wie `Learner` sind
 * veraltet und werden hier bewusst nicht als gleichwertig behandelt.
 */
final class Roles {
    private const MEMBERSHIP = 'http://purl.imsglobal.org/vocab/lis/v2/membership#';

    public const ADMINISTRATOR = self::MEMBERSHIP . 'Administrator';
    public const CONTENT_DEVELOPER = self::MEMBERSHIP . 'ContentDeveloper';
    public const INSTRUCTOR = self::MEMBERSHIP . 'Instructor';
    public const LEARNER = self::MEMBERSHIP . 'Learner';
    public const MENTOR = self::MEMBERSHIP . 'Mentor';
    public const MANAGER = self::MEMBERSHIP . 'Manager';
    public const MEMBER = self::MEMBERSHIP . 'Member';
    public const OFFICER = self::MEMBERSHIP . 'Officer';

    /**
     * Hat die Person eine der Rollen — auch als Unterrolle (`…#Instructor` deckt
     * `…/Instructor#TeachingAssistant` ab)?
     *
     * @param  list<string>  $roles
     */
    public static function includesAny(array $roles, string ...$wanted): bool {
        foreach ($roles as $role) {
            foreach ($wanted as $candidate) {
                if ($role === $candidate) {
                    return true;
                }

                // Unterrolle: …/membership/Instructor#TeachingAssistant
                $principal = str_replace('#', '/', $candidate) . '#';
                if (str_starts_with($role, $principal)) {
                    return true;
                }
            }
        }

        return false;
    }

    private function __construct() {}
}
