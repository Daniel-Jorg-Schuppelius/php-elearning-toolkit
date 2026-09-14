<?php
/*
 * Created on   : Mon Sep 14 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : CourseStructure.php
 * License      : Apache-2.0
 * License Uri  : https://www.apache.org/licenses/LICENSE-2.0
 */

declare(strict_types=1);

namespace ELearningToolkit\Cmi5;

use CommonToolkit\Helper\Data\{WebLinkHelper, XmlHelper};
use SimpleXMLElement;

/**
 * Parser für die cmi5-Kursstruktur `cmi5.xml` (cmi5 13.1).
 *
 * Namensraum-agnostisch und ohne Entitätenersetzung geladen. Kennungen von Kurs,
 * Lernzielen, Blöcken und AUs müssen innerhalb der Struktur eindeutig sein.
 *
 * Bewusst nachsichtig bei der Beschreibung: Die Spezifikation verlangt sie, viele
 * Autorenwerkzeuge lassen sie weg. Ohne sie funktioniert nichts anders — ohne
 * Kennung, Titel oder Adresse schon.
 */
final class CourseStructure {
    /**
     * @param  array<string, string>  $title
     * @param  array<string, string>  $description
     * @param  list<Objective>  $objectives
     * @param  list<Block|AssignableUnit>  $children
     */
    private function __construct(
        public readonly string $courseId,
        public readonly array $title,
        public readonly array $description,
        public readonly array $objectives,
        public readonly array $children,
    ) {}

    /** @throws Cmi5Exception */
    public static function fromXml(string $xml): self {
        $root = XmlHelper::safeLoadString($xml);

        if ($root === false) {
            throw new Cmi5Exception(Cmi5Exception::UNREADABLE, 'Die Kursstruktur ist kein gültiges XML.');
        }

        $ids = [];
        $course = null;
        $objectives = [];
        $children = [];

        foreach (self::elements($root) as $element) {
            switch ($element->getName()) {
                case 'course':
                    $course = $element;
                    break;
                case 'objectives':
                    foreach (self::elements($element, 'objective') as $objective) {
                        $id = self::requiredIri($objective, Cmi5Exception::INVALID_COURSE, 'objective');
                        self::claim($ids, $id);
                        $objectives[] = new Objective($id, self::langStrings($objective, 'title'), self::langStrings($objective, 'description'));
                    }
                    break;
                case 'block':
                    $children[] = self::block($element, $ids);
                    break;
                case 'au':
                    $children[] = self::unit($element, $ids);
                    break;
            }
        }

        if ($course === null) {
            throw new Cmi5Exception(Cmi5Exception::MISSING_COURSE, 'Die Kursstruktur enthält kein <course>.');
        }

        $courseId = self::requiredIri($course, Cmi5Exception::INVALID_COURSE, 'course');
        self::claim($ids, $courseId);
        $title = self::langStrings($course, 'title');

        if ($title === []) {
            throw new Cmi5Exception(Cmi5Exception::INVALID_COURSE, 'title');
        }

        $structure = new self($courseId, $title, self::langStrings($course, 'description'), $objectives, $children);

        if ($structure->assignableUnits() === []) {
            throw new Cmi5Exception(Cmi5Exception::INVALID_COURSE, 'Die Kursstruktur enthält keine AU.');
        }

        $known = array_map(static fn (Objective $objective): string => $objective->id, $objectives);
        foreach ([...$structure->blocks(), ...$structure->assignableUnits()] as $node) {
            foreach ($node->objectiveIds as $reference) {
                if (!in_array($reference, $known, true)) {
                    throw new Cmi5Exception(Cmi5Exception::UNKNOWN_OBJECTIVE, $reference);
                }
            }
        }

        return $structure;
    }

    /** @return list<AssignableUnit> alle AUs in Dokumentreihenfolge */
    public function assignableUnits(): array {
        $units = [];

        foreach ($this->children as $child) {
            if ($child instanceof AssignableUnit) {
                $units[] = $child;
            } else {
                array_push($units, ...$child->assignableUnits());
            }
        }

        return $units;
    }

    /** @return list<Block> alle Blöcke, auch verschachtelte, in Dokumentreihenfolge */
    public function blocks(): array {
        $blocks = [];
        $walk = static function (array $children) use (&$walk, &$blocks): void {
            foreach ($children as $child) {
                if ($child instanceof Block) {
                    $blocks[] = $child;
                    $walk($child->children);
                }
            }
        };
        $walk($this->children);

        return $blocks;
    }

    public function findUnit(string $id): ?AssignableUnit {
        foreach ($this->assignableUnits() as $unit) {
            if ($unit->id === $id) {
                return $unit;
            }
        }

        return null;
    }

    /**
     * @param  array<string, true>  $ids
     *
     * @param-out array<string, true> $ids
     */
    private static function block(SimpleXMLElement $element, array &$ids): Block {
        $id = self::requiredIri($element, Cmi5Exception::INVALID_BLOCK, 'block');
        self::claim($ids, $id);
        $title = self::langStrings($element, 'title');

        if ($title === []) {
            throw new Cmi5Exception(Cmi5Exception::INVALID_BLOCK, $id);
        }

        $children = [];
        foreach (self::elements($element) as $child) {
            if ($child->getName() === 'block') {
                $children[] = self::block($child, $ids);
            } elseif ($child->getName() === 'au') {
                $children[] = self::unit($child, $ids);
            }
        }

        if ($children === []) {
            throw new Cmi5Exception(Cmi5Exception::INVALID_BLOCK, $id);
        }

        return new Block($id, $title, self::langStrings($element, 'description'), self::objectiveIds($element), $children);
    }

    /**
     * @param  array<string, true>  $ids
     *
     * @param-out array<string, true> $ids
     */
    private static function unit(SimpleXMLElement $element, array &$ids): AssignableUnit {
        $id = self::requiredIri($element, Cmi5Exception::INVALID_AU, 'au');
        self::claim($ids, $id);

        $title = self::langStrings($element, 'title');
        $url = self::childText($element, 'url');

        if ($title === [] || $url === null) {
            throw new Cmi5Exception(Cmi5Exception::INVALID_AU, $id);
        }

        $moveOnValue = self::attribute($element, 'moveOn');
        $moveOn = $moveOnValue === '' ? MoveOn::NotApplicable : MoveOn::tryFrom($moveOnValue);
        if ($moveOn === null) {
            throw new Cmi5Exception(Cmi5Exception::INVALID_MOVE_ON, $moveOnValue);
        }

        $methodValue = self::attribute($element, 'launchMethod');
        $method = $methodValue === '' ? LaunchMethod::AnyWindow : LaunchMethod::tryFrom($methodValue);
        if ($method === null) {
            throw new Cmi5Exception(Cmi5Exception::INVALID_LAUNCH_METHOD, $methodValue);
        }

        $activityType = self::attribute($element, 'activityType');
        if ($activityType !== '' && !WebLinkHelper::isAbsoluteIri($activityType)) {
            throw new Cmi5Exception(Cmi5Exception::INVALID_AU, $id);
        }

        return new AssignableUnit(
            $id,
            $title,
            self::langStrings($element, 'description'),
            $url,
            $moveOn,
            self::masteryScore(self::attribute($element, 'masteryScore')),
            $method,
            self::childText($element, 'launchParameters'),
            self::childText($element, 'entitlementKey'),
            $activityType !== '' ? $activityType : null,
            self::objectiveIds($element),
        );
    }

    /** Skaliert 0 bis 1, höchstens vier Nachkommastellen (cmi5 13.1.4). */
    private static function masteryScore(string $value): ?float {
        if ($value === '') {
            return null;
        }

        if (preg_match('/^(0(\.\d{1,4})?|1(\.0{1,4})?|\.\d{1,4})$/D', $value) !== 1) {
            throw new Cmi5Exception(Cmi5Exception::INVALID_MASTERY_SCORE, $value);
        }

        return (float) $value;
    }

    /** @return array<string, string> */
    private static function langStrings(SimpleXMLElement $parent, string $name): array {
        $map = [];

        foreach (self::elements($parent, $name) as $container) {
            foreach (self::elements($container, 'langstring') as $langString) {
                $text = trim((string) $langString);
                if ($text === '') {
                    continue;
                }

                $language = self::attribute($langString, 'lang');
                if ($language === '') {
                    $xml = $langString->attributes('http://www.w3.org/XML/1998/namespace');
                    $language = $xml !== null ? trim((string) $xml['lang']) : '';
                }

                $map[$language !== '' ? $language : 'und'] = $text;
            }
        }

        return $map;
    }

    /** @return list<string> */
    private static function objectiveIds(SimpleXMLElement $parent): array {
        $ids = [];

        foreach (self::elements($parent, 'objectives') as $objectives) {
            foreach (self::elements($objectives, 'objective') as $reference) {
                $idref = self::attribute($reference, 'idref');
                if ($idref !== '') {
                    $ids[] = $idref;
                }
            }
        }

        return $ids;
    }

    private static function childText(SimpleXMLElement $parent, string $name): ?string {
        foreach (self::elements($parent, $name) as $child) {
            $text = trim((string) $child);

            return $text !== '' ? $text : null;
        }

        return null;
    }

    private static function requiredIri(SimpleXMLElement $element, string $reason, string $what): string {
        $id = self::attribute($element, 'id');

        if (!WebLinkHelper::isAbsoluteIri($id)) {
            throw new Cmi5Exception($reason, $what . ($id !== '' ? ' ' . $id : ''));
        }

        return $id;
    }

    /**
     * @param  array<string, true>  $ids
     *
     * @param-out array<string, true> $ids
     */
    private static function claim(array &$ids, string $id): void {
        if (isset($ids[$id])) {
            throw new Cmi5Exception(Cmi5Exception::DUPLICATE_ID, $id);
        }

        $ids[$id] = true;
    }

    /**
     * Kindelemente ohne Rücksicht auf den Namensraum.
     *
     * @return list<SimpleXMLElement>
     */
    private static function elements(SimpleXMLElement $parent, ?string $name = null): array {
        $result = $parent->xpath('./*');
        $elements = [];

        foreach (is_array($result) ? $result : [] as $element) {
            if ($name === null || $element->getName() === $name) {
                $elements[] = $element;
            }
        }

        return $elements;
    }

    private static function attribute(SimpleXMLElement $element, string $name): string {
        $attributes = $element->attributes();

        return $attributes !== null ? trim((string) $attributes[$name]) : '';
    }
}
