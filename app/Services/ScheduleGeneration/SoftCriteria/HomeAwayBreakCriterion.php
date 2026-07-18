<?php

namespace App\Services\ScheduleGeneration\SoftCriteria;

use App\Services\ScheduleGeneration\GenerationConfig;
use App\Services\ScheduleGeneration\MatchCandidate;
use App\Services\ScheduleGeneration\ScoringContext;

/**
 * The classical round-robin "break": a team playing the same home/away role
 * in two consecutive rounds instead of alternating. A single break (a streak
 * of exactly 2) costs the same flat 1 raw unit it always has - but a streak
 * that reaches 3+ is a genuinely worse pattern (a team stuck on the road or
 * at home for a whole stretch, not just one alternation slip), so each round
 * that extends a streak past 2 costs SEVERE_STREAK_MULTIPLIER raw units
 * instead of 1. This only kicks in for a real CONSECUTIVE run - two isolated,
 * non-adjacent breaks for the same team cost 1 unit each, same as before. A
 * bye resets a team's "last role" (and its streak) the same way a bye resets
 * last-opponent/last-venue elsewhere, so a repeat either side of a bye round
 * doesn't count as consecutive.
 */
final class HomeAwayBreakCriterion implements SoftCriterion
{
    use RecordsRoundViolations;

    private const SEVERE_STREAK_THRESHOLD = 3;

    private const SEVERE_STREAK_MULTIPLIER = 3.0;

    /** @var array<int, bool> team id => true if home in the immediately preceding round, false if away */
    private array $lastRoleByTeam = [];

    /** @var array<int, int> team id => length of the current consecutive-same-role streak */
    private array $streakLengthByTeam = [];

    private float $rawUnits = 0.0;

    private int $matchCount = 0;

    /** @var string[] */
    private array $messages = [];

    public function __construct(
        private readonly ScoringContext $context,
    ) {
    }

    public function key(): string
    {
        return 'home_away_break';
    }

    public function label(): string
    {
        return 'Consecutive rounds in the same home/away role';
    }

    public function observeMatch(int $roundIndex, MatchCandidate $match): void
    {
        $roundNumber = $roundIndex + 1;

        $this->recordRole($match->homeTeamId, true, $roundIndex, $roundNumber);
        $this->recordRole($match->awayTeamId, false, $roundIndex, $roundNumber);

        $this->matchCount++;
    }

    private function recordRole(int $teamId, bool $isHome, int $roundIndex, int $roundNumber): void
    {
        if (($this->lastRoleByTeam[$teamId] ?? null) !== $isHome) {
            $this->streakLengthByTeam[$teamId] = 1;
            $this->lastRoleByTeam[$teamId] = $isHome;

            return;
        }

        $streak = ($this->streakLengthByTeam[$teamId] ?? 1) + 1;
        $this->streakLengthByTeam[$teamId] = $streak;
        $this->lastRoleByTeam[$teamId] = $isHome;

        $roleLabel = $isHome ? 'home' : 'away';

        if ($streak >= self::SEVERE_STREAK_THRESHOLD) {
            $this->rawUnits += self::SEVERE_STREAK_MULTIPLIER;
            $this->messages[] = "{$this->context->teamLabel($teamId)} has played {$roleLabel} in {$streak} consecutive rounds through round {$roundNumber}.";
        } else {
            $this->rawUnits += 1.0;
            $this->messages[] = "{$this->context->teamLabel($teamId)} played {$roleLabel} in consecutive rounds around round {$roundNumber}.";
        }

        $this->flagRoundViolation($roundIndex, $teamId);
    }

    public function observeBye(int $roundIndex, int $teamId): void
    {
        unset($this->lastRoleByTeam[$teamId]);
        unset($this->streakLengthByTeam[$teamId]);
    }

    public function finalize(): void
    {
    }

    public function penalty(GenerationConfig $config): float
    {
        return $this->weight($config) * $this->rawPenalty();
    }

    public function weight(GenerationConfig $config): float
    {
        return $config->tierWeight($this->key());
    }

    public function rawPenalty(): float
    {
        return $this->rawUnits / max(1, 2 * $this->matchCount);
    }

    public function epsilonUnit(): float
    {
        return 1 / max(1, 2 * $this->matchCount);
    }

    public function messages(): array
    {
        return $this->messages;
    }
}
