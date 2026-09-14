<?php
/*
 * Created on   : Mon Sep 14 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : Manifest.php
 * License      : Apache-2.0
 * License Uri  : https://www.apache.org/licenses/LICENSE-2.0
 */

declare(strict_types=1);

namespace ELearningToolkit\Scorm;

use CommonToolkit\Helper\Data\XmlHelper;
use ELearningToolkit\Package\PackageException;
use SimpleXMLElement;

/**
 * Parser für `imsmanifest.xml`.
 *
 * **Namensraum-agnostisch.** Pakete unterscheiden sich in den Präfixen
 * (`adlcp` mit `_rootv1p2` bei SCORM 1.2, `_v1p3` bei 2004) und im
 * Attributnamen (`scormtype` vs. `scormType`). Wer auf feste Präfixe parst,
 * scheitert am ersten Paket eines anderen Autorenwerkzeugs.
 *
 * **Keine Entitäten.** Geladen wird über {@see XmlHelper::safeLoadString()}.
 * `LIBXML_NOENT` ersetzt Entitäten, statt sie zu unterbinden, und löste in einer
 * früheren Fassung `<!ENTITY x SYSTEM "file:///…">` auf — der Dateiinhalt landete
 * im Titel. Ein Manifest braucht keine Entitäten.
 */
final class Manifest {
    /**
     * @param  list<ManifestItem>  $items
     */
    private function __construct(
        public readonly ScormVersion $version,
        public readonly string $title,
        public readonly ?string $launchHref,
        public readonly array $items,
    ) {}

    public static function fromXml(string $xml): self {
        $element = XmlHelper::safeLoadString($xml);

        if ($element === false) {
            throw new PackageException(PackageException::UNREADABLE, 'Das Manifest ist kein gültiges XML.');
        }

        $version = self::detectVersion($element);
        $resources = self::readResources($element);
        [$title, $items] = self::readOrganization($element, $resources);

        $launch = null;
        foreach ($items as $item) {
            if ($item->href !== null) {
                $launch = $item->href;
                break;
            }
        }

        // Manche Pakete führen nur Ressourcen ohne Organisation: dann gilt die
        // erste startbare Ressource.
        if ($launch === null) {
            foreach ($resources as $resource) {
                if ($resource['href'] !== null) {
                    $launch = $resource['href'];
                    break;
                }
            }
        }

        return new self($version, $title, $launch, $items);
    }

    private static function detectVersion(SimpleXMLElement $manifest): ScormVersion {
        $schemaVersion = '';

        foreach (self::nodes($manifest, '//*[local-name()="schemaversion"]') as $node) {
            $schemaVersion = trim((string) $node);
            break;
        }

        // „2004 3rd Edition", „2004 4th Edition", „CAM 1.3" — alles 2004.
        if (str_contains($schemaVersion, '2004') || str_contains($schemaVersion, '1.3')) {
            return ScormVersion::Scorm2004;
        }

        if ($schemaVersion === '1.2') {
            return ScormVersion::Scorm12;
        }

        // Ohne verwertbare Angabe entscheidet der Namensraum.
        foreach (self::namespaces($manifest) as $uri) {
            if (str_contains($uri, 'adlcp_v1p3')) {
                return ScormVersion::Scorm2004;
            }
        }

        return ScormVersion::Scorm12;
    }

    /**
     * @return array<string, array{href: string|null, is_sco: bool}>
     */
    private static function readResources(SimpleXMLElement $manifest): array {
        $resources = [];

        foreach (self::nodes($manifest, '//*[local-name()="resource"]') as $resource) {
            $identifier = self::attribute($resource, 'identifier');

            if ($identifier === '') {
                continue;
            }

            $href = self::attribute($resource, 'href');

            // `xml:base` verschiebt den Bezugspunkt — am <resources>-Block und/oder
            // an der einzelnen <resource>. Wer es übergeht, sucht im falschen Ordner.
            $parent = self::nodes($resource, '..')[0] ?? null;
            $base = self::xmlBase($parent) . self::xmlBase($resource);

            $resources[$identifier] = [
                'href' => $href !== '' ? $base . $href : null,
                'is_sco' => self::isSco($resource),
            ];
        }

        return $resources;
    }

    /**
     * @param  array<string, array{href: string|null, is_sco: bool}>  $resources
     * @return array{0: string, 1: list<ManifestItem>}
     */
    private static function readOrganization(SimpleXMLElement $manifest, array $resources): array {
        $organizations = self::nodes($manifest, '//*[local-name()="organizations"]');
        $default = $organizations !== [] ? self::attribute($organizations[0], 'default') : '';

        $chosen = null;
        foreach (self::nodes($manifest, '//*[local-name()="organization"]') as $organization) {
            $identifier = self::attribute($organization, 'identifier');

            if ($chosen === null || ($default !== '' && $identifier === $default)) {
                $chosen = $organization;
            }

            if ($default !== '' && $identifier === $default) {
                break;
            }
        }

        if ($chosen === null) {
            return ['', []];
        }

        $title = '';
        foreach (self::nodes($chosen, './*[local-name()="title"]') as $node) {
            $title = trim((string) $node);
            break;
        }

        $items = [];
        foreach (self::nodes($chosen, './/*[local-name()="item"]') as $item) {
            $resource = $resources[self::attribute($item, 'identifierref')] ?? null;

            $itemTitle = '';
            foreach (self::nodes($item, './*[local-name()="title"]') as $node) {
                $itemTitle = trim((string) $node);
                break;
            }

            $items[] = new ManifestItem(
                self::attribute($item, 'identifier'),
                $itemTitle,
                $resource['href'] ?? null,
                $resource['is_sco'] ?? false,
            );
        }

        return [$title, $items];
    }

    /** `xml:base` eines Elements, normalisiert auf „endet mit /" oder leer. */
    private static function xmlBase(?SimpleXMLElement $element): string {
        if ($element === null) {
            return '';
        }

        $attributes = $element->attributes('http://www.w3.org/XML/1998/namespace');
        $base = $attributes !== null ? trim((string) $attributes['base']) : '';

        return $base === '' ? '' : rtrim($base, '/') . '/';
    }

    /**
     * `adlcp:scormtype` (1.2) bzw. `adlcp:scormType` (2004) — über alle
     * Namensräume und ohne Rücksicht auf Groß-/Kleinschreibung.
     */
    private static function isSco(SimpleXMLElement $resource): bool {
        foreach ([...self::namespaces($resource), ''] as $uri) {
            $attributes = $uri === '' ? $resource->attributes() : $resource->attributes($uri);

            if ($attributes === null) {
                continue;
            }

            foreach ($attributes as $name => $value) {
                if (strcasecmp((string) $name, 'scormtype') === 0) {
                    return strcasecmp(trim((string) $value), 'sco') === 0;
                }
            }
        }

        return false;
    }

    /** @return list<string> */
    private static function namespaces(SimpleXMLElement $element): array {
        $namespaces = $element->getDocNamespaces(true);

        return $namespaces === false ? [] : array_values(array_map('strval', $namespaces));
    }

    /** @return list<SimpleXMLElement> */
    private static function nodes(SimpleXMLElement $element, string $xpath): array {
        $result = $element->xpath($xpath);

        return is_array($result) ? array_values($result) : [];
    }

    private static function attribute(SimpleXMLElement $element, string $name): string {
        $attributes = $element->attributes();

        return $attributes !== null ? trim((string) $attributes[$name]) : '';
    }
}
