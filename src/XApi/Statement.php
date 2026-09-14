<?php
/*
 * Created on   : Mon Sep 14 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : Statement.php
 * License      : Apache-2.0
 * License Uri  : https://www.apache.org/licenses/LICENSE-2.0
 */

declare(strict_types=1);

namespace ELearningToolkit\XApi;

use JsonException;

/**
 * Ein geprüftes xAPI-Statement.
 *
 * Die Felder decken ab, was für Fortschritt und Zuordnung zählt. Das ganze
 * Statement bleibt in {@see self::$raw} erhalten — ein LRS bewahrt auch auf,
 * was es nicht auswertet.
 */
final class Statement {
    /**
     * @param  array<mixed>  $raw
     */
    private function __construct(
        public readonly ?string $id,
        public readonly string $verbId,
        public readonly string $objectType,
        /** Aktivitäts-IRI oder referenzierte Statement-ID; `null` bei Agents und SubStatements. */
        public readonly ?string $objectId,
        public readonly ?bool $success,
        public readonly ?bool $completion,
        public readonly ?float $scoreScaled,
        public readonly ?string $registration,
        public readonly ?string $timestamp,
        public readonly array $raw,
    ) {}

    /**
     * @param  array<mixed>  $data
     *
     * @throws StatementException
     */
    public static function fromArray(array $data, ?StatementValidator $validator = null): self {
        ($validator ?? new StatementValidator)->assertValid($data);

        /** @var array<string, mixed> $verb */
        $verb = $data['verb'];
        /** @var array<string, mixed> $object */
        $object = $data['object'];
        $objectType = is_string($object['objectType'] ?? null) ? $object['objectType'] : 'Activity';
        $result = is_array($data['result'] ?? null) ? $data['result'] : [];
        $score = is_array($result['score'] ?? null) ? $result['score'] : [];
        $context = is_array($data['context'] ?? null) ? $data['context'] : [];

        return new self(
            is_string($data['id'] ?? null) ? strtolower($data['id']) : null,
            (string) $verb['id'],
            $objectType,
            in_array($objectType, ['Activity', 'StatementRef'], true) && is_string($object['id'] ?? null) ? $object['id'] : null,
            is_bool($result['success'] ?? null) ? $result['success'] : null,
            is_bool($result['completion'] ?? null) ? $result['completion'] : null,
            is_int($score['scaled'] ?? null) || is_float($score['scaled'] ?? null) ? (float) $score['scaled'] : null,
            is_string($context['registration'] ?? null) ? strtolower($context['registration']) : null,
            is_string($data['timestamp'] ?? null) ? $data['timestamp'] : null,
            $data,
        );
    }

    /** @throws StatementException */
    public static function fromJson(string $json, ?StatementValidator $validator = null): self {
        try {
            $data = json_decode($json, true, 64, JSON_THROW_ON_ERROR);
        } catch (JsonException $e) {
            throw new StatementException(StatementException::INVALID_JSON, '', $e);
        }

        if (!is_array($data)) {
            throw new StatementException(StatementValidator::INVALID_TYPE);
        }

        return self::fromArray($data, $validator);
    }
}
