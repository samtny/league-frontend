<?php

namespace App\Services\ScheduleGeneration\SoftCriteria;

use App\Services\ScheduleGeneration\GenerationConfig;
use App\Services\ScheduleGeneration\HomeVenueMatch;
use App\Services\ScheduleGeneration\MatchCandidate;
use App\Services\ScheduleGeneration\ScoringContext;

final class HomeVenueBalanceCriterion implements SoftCriterion
{
    /** @var array<int, int> */
    private array $homeVenueAppearancesByTeam = [];

    /** @var array<int, int> */
    private array $matchesPlayedByTeam = [];

    private float $totalOver = 0.0;

    /** @var string[] */
    private array $messages = [];

    public function __construct(
        private readonly ScoringContext $context,
    ) {
    }

    public function key(): string
    {
        return 'home_venue_balance';
    }

    public function label(): string
    {
        return 'Balance between home-venue and other-venue matches';
    }

    public function observeMatch(int $roundIndex, MatchCandidate $match): void
    {
        $home = $match->homeTeamId;
        $away = $match->awayTeamId;

        $this->matchesPlayedByTeam[$home] = ($this->matchesPlayedByTeam[$home] ?? 0) + 1;
        $this->matchesPlayedByTeam[$away] = ($this->matchesPlayedByTeam[$away] ?? 0) + 1;

        if (HomeVenueMatch::isOwnVenue($this->context->homeVenueIdByTeam[$home] ?? null, $match->venueId)) {
            $this->homeVenueAppearancesByTeam[$home] = ($this->homeVenueAppearancesByTeam[$home] ?? 0) + 1;
        }
    }

    public function observeBye(int $roundIndex, int $teamId): void
    {
    }

    public function finalize(): void
    {
        foreach ($this->context->activeTeams as $team) {
            if ($team->homeVenueId === null) {
                continue;
            }

            $homeAppearances = $this->homeVenueAppearancesByTeam[$team->id] ?? 0;
            $played = $this->matchesPlayedByTeam[$team->id] ?? 0;
            $otherAppearances = $played - $homeAppearances;
            $diff = abs($homeAppearances - $otherAppearances);
            $over = max(0, $diff - 1);

            if ($over > 0) {
                $this->totalOver += $over;
                $this->messages[] = "{$team->name} played at their own home venue {$homeAppearances} of {$played} matches (expected roughly half).";
            }
        }
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
        return $this->totalOver / max(1, count($this->context->activeTeamIds));
    }

    public function epsilonUnit(): float
    {
        return 1 / max(1, count($this->context->activeTeamIds));
    }

    public function messages(): array
    {
        return $this->messages;
    }

    public function roundViolations(): array
    {
        // A team's season-long home-venue-appearance ratio, not
        // attributable to any single round.
        return [];
    }
}
