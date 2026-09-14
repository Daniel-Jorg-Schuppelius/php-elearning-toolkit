<?php
/*
 * Created on   : Mon Sep 14 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : DeepLinkingSettings.php
 * License      : Apache-2.0
 * License Uri  : https://www.apache.org/licenses/LICENSE-2.0
 */

declare(strict_types=1);

namespace ELearningToolkit\Lti;

use CommonToolkit\Helper\Data\WebLinkHelper;

/** Der Claim `deep_linking_settings` einer Deep-Linking-Anfrage (Deep Linking 2.0, 4.4.1). */
final class DeepLinkingSettings {
    /**
     * @param  list<string>  $acceptTypes
     * @param  list<string>  $acceptPresentationDocumentTargets
     */
    public function __construct(
        public readonly string $returnUrl,
        public readonly array $acceptTypes,
        public readonly array $acceptPresentationDocumentTargets,
        public readonly ?string $acceptMediaTypes = null,
        /** `null`: Die Plattform hat sich nicht festgelegt. */
        public readonly ?bool $acceptMultiple = null,
        public readonly ?bool $acceptLineItem = null,
        public readonly bool $autoCreate = false,
        public readonly ?string $title = null,
        public readonly ?string $text = null,
        /** Opakes Datum, das die Antwort unverändert zurückgeben muss. */
        public readonly ?string $data = null,
    ) {}

    /** @throws LtiException */
    public static function fromClaim(mixed $claim): self {
        if (!is_array($claim)) {
            throw new LtiException(LtiException::MISSING_CLAIM, 'deep_linking_settings');
        }

        $returnUrl = $claim['deep_link_return_url'] ?? null;
        if (!is_string($returnUrl) || !WebLinkHelper::isAbsoluteIri($returnUrl)) {
            throw new LtiException(LtiException::INVALID_CLAIM, 'deep_link_return_url');
        }

        $types = self::stringList($claim['accept_types'] ?? null);
        if ($types === null || $types === []) {
            throw new LtiException(LtiException::INVALID_CLAIM, 'accept_types');
        }

        $targets = self::stringList($claim['accept_presentation_document_targets'] ?? null);
        if ($targets === null) {
            throw new LtiException(LtiException::INVALID_CLAIM, 'accept_presentation_document_targets');
        }

        foreach (['accept_multiple', 'accept_lineitem', 'auto_create'] as $flag) {
            if (array_key_exists($flag, $claim) && !is_bool($claim[$flag])) {
                throw new LtiException(LtiException::INVALID_CLAIM, $flag);
            }
        }
        foreach (['accept_media_types', 'title', 'text', 'data'] as $text) {
            if (array_key_exists($text, $claim) && !is_string($claim[$text])) {
                throw new LtiException(LtiException::INVALID_CLAIM, $text);
            }
        }

        return new self(
            $returnUrl,
            $types,
            $targets,
            $claim['accept_media_types'] ?? null,
            $claim['accept_multiple'] ?? null,
            $claim['accept_lineitem'] ?? null,
            $claim['auto_create'] ?? false,
            $claim['title'] ?? null,
            $claim['text'] ?? null,
            $claim['data'] ?? null,
        );
    }

    /** @return array<string, mixed> */
    public function toClaim(): array {
        return array_filter([
            'deep_link_return_url' => $this->returnUrl,
            'accept_types' => $this->acceptTypes,
            'accept_presentation_document_targets' => $this->acceptPresentationDocumentTargets,
            'accept_media_types' => $this->acceptMediaTypes,
            'accept_multiple' => $this->acceptMultiple,
            'accept_lineitem' => $this->acceptLineItem,
            'auto_create' => $this->autoCreate,
            'title' => $this->title,
            'text' => $this->text,
            'data' => $this->data,
        ], static fn (mixed $value): bool => $value !== null);
    }

    /**
     * Passen die Inhalte zu dem, was die Plattform angenommen hat?
     *
     * @param  list<array<string, mixed>>  $items
     *
     * @throws LtiException
     */
    public function assertAcceptable(array $items): void {
        if ($this->acceptMultiple === false && count($items) > 1) {
            throw new LtiException(LtiException::INVALID_CLAIM, 'accept_multiple');
        }

        foreach ($items as $index => $item) {
            $type = $item['type'] ?? null;

            if (!is_string($type) || !in_array($type, $this->acceptTypes, true)) {
                throw new LtiException(LtiException::INVALID_CLAIM, 'content_items[' . $index . '].type');
            }
        }
    }

    /** @return list<string>|null */
    private static function stringList(mixed $value): ?array {
        if (!is_array($value) || !array_is_list($value)) {
            return null;
        }

        foreach ($value as $entry) {
            if (!is_string($entry)) {
                return null;
            }
        }

        /** @var list<string> $value */
        return $value;
    }
}
