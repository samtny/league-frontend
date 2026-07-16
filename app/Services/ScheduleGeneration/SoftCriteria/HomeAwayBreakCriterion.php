<?php

namespace App\Services\ScheduleGeneration\SoftCriteria;

use App\Services\ScheduleGeneration\GenerationConfig;
use App\Services\ScheduleGeneration\MatchCandidate;
use App\Services\ScheduleGeneration\ScoringContext;

/**
 * The classical round-robin "break": a team playing the same home/away role
 * in two consecutive rounds instead of alternating. Flat per-occurrence
 * penalty (no repeat-offense surcharge, unlike ConsecutiveVenueCriterion) -
 * this is meant to read as a straightforward break count, symmetric between
 * home and away. A bye resets a team's "last role" the same way a bye
 * resets last-opponent/last-venue elsewhere, so a repeat either side of a
 * bye round doesn't count as consecutive.
 *
 * Weight is dynamic (1 / (2 * active team count)) rather than a fixed config
 * value - experimental, ignores $config->weightHomeAwayBreak entirely.
 */
final class HomeAwayBreakCriterion implements SoftCriterion
{
    /** @var array<int, bool> team id => true if home in the immediately preceding round, false if away */
    private array $lastRoleByTeam = [];

    private float $breakCount = 0.0;

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
        $home = $match->homeTeamId;
        $away = $match->awayTeamId;

        if (($this->lastRoleByTeam[$home] ?? null) === true) {
            $this->breakCount++;
            $this->messages[] = "{$this->context->teamLabel($home)} played home in consecutive rounds around round {$roundNumber}.";
        }

        if (($this->lastRoleByTeam[$away] ?? null) === false) {
            $this->breakCount++;
            $this->messages[] = "{$this->context->teamLabel($away)} played away in consecutive rounds around round {$roundNumber}.";
        }

        $this->lastRoleByTeam[$home] = true;
        $this->lastRoleByTeam[$away] = false;
    }

    public function observeBye(int $roundIndex, int $teamId): void
    {
        unset($this->lastRoleByTeam[$teamId]);
    }

    public function finalize(): void
    {
    }

    public function penalty(GenerationConfig $config): float
    {
        return $this->weight($config) * $this->breakCount;
    }

    public function weight(GenerationConfig $config): float
    {
        $teamCount = count($this->context->activeTeams);

        return $teamCount > 0 ? 1 / (2 * $teamCount) : 0.0;
    }

    public function messages(): array
    {
        return $this->messages;
    }
}
