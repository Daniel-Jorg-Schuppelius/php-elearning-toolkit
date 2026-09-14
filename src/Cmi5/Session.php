<?php
/*
 * Created on   : Mon Sep 14 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : Session.php
 * License      : Apache-2.0
 * License Uri  : https://www.apache.org/licenses/LICENSE-2.0
 */

declare(strict_types=1);

namespace ELearningToolkit\Cmi5;

/**
 * Zustand einer AU-Sitzung aus Sicht des LMS.
 *
 * Zwei Arten von Wissen: `completed`, `passed` und `waived` gelten für die ganze
 * Registrierung und kommen vom LMS mit in die Sitzung. `initialized`,
 * `terminated` und `abandoned` gelten nur für diese Sitzung.
 *
 * Unveränderlich — {@see StatementRules::apply()} liefert den Folgezustand.
 */
final class Session {
    public function __construct(
        public readonly string $registration,
        /** Vom LMS vergebene Aktivitäts-ID der AU, wie in der Start-URL. */
        public readonly string $activityId,
        public readonly string $sessionId,
        /** Kennung der AU in der Kursstruktur, geführt in `grouping`. */
        public readonly string $publisherId,
        public readonly LaunchMode $launchMode,
        public readonly MoveOn $moveOn,
        public readonly ?float $masteryScore = null,
        public readonly bool $completed = false,
        public readonly bool $passed = false,
        public readonly bool $waived = false,
        public readonly bool $initialized = false,
        public readonly bool $terminated = false,
        public readonly bool $abandoned = false,
    ) {}

    /**
     * Sitzung für eine AU der Kursstruktur. moveOn und masteryScore darf das LMS
     * nach eigenen Regeln abweichend setzen (cmi5 10.2.4 und 10.2.5).
     */
    public static function forUnit(
        AssignableUnit $unit,
        string $registration,
        string $activityId,
        string $sessionId,
        LaunchMode $launchMode = LaunchMode::Normal,
        bool $completed = false,
        bool $passed = false,
        bool $waived = false,
        ?MoveOn $moveOn = null,
        ?float $masteryScore = null,
    ): self {
        return new self(
            strtolower($registration),
            $activityId,
            $sessionId,
            $unit->id,
            $launchMode,
            $moveOn ?? $unit->moveOn,
            $masteryScore ?? $unit->masteryScore,
            $completed,
            $passed,
            $waived,
        );
    }

    /** Ist die AU für die Registrierung erfüllt? Ein Erlass zählt wie erfüllt (cmi5 9.3.9). */
    public function isSatisfied(): bool {
        return $this->waived || $this->moveOn->isSatisfiedBy($this->completed, $this->passed);
    }

    /** Nach `terminated` oder `abandoned` nimmt die Sitzung nichts mehr an. */
    public function hasEnded(): bool {
        return $this->terminated || $this->abandoned;
    }

    public function with(
        ?bool $completed = null,
        ?bool $passed = null,
        ?bool $waived = null,
        ?bool $initialized = null,
        ?bool $terminated = null,
        ?bool $abandoned = null,
    ): self {
        return new self(
            $this->registration,
            $this->activityId,
            $this->sessionId,
            $this->publisherId,
            $this->launchMode,
            $this->moveOn,
            $this->masteryScore,
            $completed ?? $this->completed,
            $passed ?? $this->passed,
            $waived ?? $this->waived,
            $initialized ?? $this->initialized,
            $terminated ?? $this->terminated,
            $abandoned ?? $this->abandoned,
        );
    }
}
