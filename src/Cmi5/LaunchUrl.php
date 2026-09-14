<?php
/*
 * Created on   : Mon Sep 14 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : LaunchUrl.php
 * License      : Apache-2.0
 * License Uri  : https://www.apache.org/licenses/LICENSE-2.0
 */

declare(strict_types=1);

namespace ELearningToolkit\Cmi5;

use JsonException;

/**
 * Baut die Startadresse einer AU (cmi5 8.1).
 *
 * Die fünf Parameter werden URL-kodiert an die Adresse aus der Kursstruktur
 * gehängt. Eine vorhandene Abfrage und ein Fragment bleiben erhalten. Nutzt die
 * Adresse selbst schon einen der reservierten Namen, ist die AU fehlerhaft
 * gebaut — die Spezifikation verbietet die Kollision, also wird abgelehnt statt
 * überschrieben.
 */
final class LaunchUrl {
    /**
     * @param  array<string, mixed>  $actor  xAPI-Agent, siehe {@see Actor::forAccount()}
     *
     * @throws Cmi5Exception
     */
    public static function build(string $auUrl, string $endpoint, string $fetchUrl, array $actor, string $registration, string $activityId): string {
        $fragment = '';
        $hash = strpos($auUrl, '#');
        if ($hash !== false) {
            $fragment = substr($auUrl, $hash);
            $auUrl = substr($auUrl, 0, $hash);
        }

        [$base, $query] = array_pad(explode('?', $auUrl, 2), 2, '');

        foreach (explode('&', $query) as $pair) {
            $name = rawurldecode(explode('=', $pair, 2)[0]);
            if ($name !== '' && in_array($name, Cmi5::LAUNCH_PARAMETERS, true)) {
                throw new Cmi5Exception(Cmi5Exception::PARAMETER_COLLISION, $name);
            }
        }

        try {
            $actorJson = json_encode($actor, JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
        } catch (JsonException $e) {
            throw new Cmi5Exception(Cmi5Exception::INVALID_URL, 'actor', $e);
        }

        $parameters = http_build_query([
            'endpoint' => $endpoint,
            'fetch' => $fetchUrl,
            'actor' => $actorJson,
            'registration' => $registration,
            'activityId' => $activityId,
        ], '', '&', PHP_QUERY_RFC3986);

        return $base . '?' . ($query !== '' ? $query . '&' : '') . $parameters . $fragment;
    }

    private function __construct() {}
}
