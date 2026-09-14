<?php
/*
 * Created on   : Mon Sep 14 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : ContentItem.php
 * License      : Apache-2.0
 * License Uri  : https://www.apache.org/licenses/LICENSE-2.0
 */

declare(strict_types=1);

namespace ELearningToolkit\Lti;

/**
 * Inhaltselemente einer Deep-Linking-Antwort (Deep Linking 2.0, 3.x).
 * Leere Angaben werden weggelassen statt als `null` gesendet.
 */
final class ContentItem {
    /** @return array<string, mixed> */
    public static function link(string $url, ?string $title = null, ?string $text = null): array {
        return self::compact(['type' => 'link', 'url' => $url, 'title' => $title, 'text' => $text]);
    }

    /**
     * @param  array<string, string>  $custom
     * @return array<string, mixed>
     */
    public static function ltiResourceLink(?string $url = null, ?string $title = null, ?string $text = null, array $custom = []): array {
        return self::compact(['type' => 'ltiResourceLink', 'url' => $url, 'title' => $title, 'text' => $text, 'custom' => $custom === [] ? null : $custom]);
    }

    /** @return array<string, mixed> */
    public static function file(string $url, ?string $title = null, ?string $mediaType = null): array {
        return self::compact(['type' => 'file', 'url' => $url, 'title' => $title, 'mediaType' => $mediaType]);
    }

    /** @return array<string, mixed> */
    public static function html(string $html, ?string $title = null): array {
        return self::compact(['type' => 'html', 'html' => $html, 'title' => $title]);
    }

    /** @return array<string, mixed> */
    public static function image(string $url, ?string $title = null, ?int $width = null, ?int $height = null): array {
        return self::compact(['type' => 'image', 'url' => $url, 'title' => $title, 'width' => $width, 'height' => $height]);
    }

    /**
     * @param  array<string, mixed>  $values
     * @return array<string, mixed>
     */
    private static function compact(array $values): array {
        return array_filter($values, static fn (mixed $value): bool => $value !== null);
    }

    private function __construct() {}
}
