<?php

namespace App\Services\ScheduleGeneration\HardConstraints;

use App\Services\ScheduleGeneration\HomeVenueMatch;
use App\Services\ScheduleGeneration\MatchCandidate;
use App\Services\ScheduleGeneration\ScoringContext;

final class AwayTeamAtOwnVenueConstraint implements HardConstraint
{
    /** @var string[] */
    private array $violations = [];

    public function __construct(
        private readonly ScoringContext $context,
    ) {
    }

    public function key(): string
    {
        return 'away_team_at_own_venue';
    }

    public function label(): string
    {
        return 'Away team assigned to a match at their own home venue';
    }

    public function startRound(int $roundIndex): void
    {
    }

    public function observeMatch(int $roundIndex, MatchCandidate $match): void
    {
        $away = $match->awayTeamId;

        if (HomeVenueMatch::isOwnVenue($this->context->homeVenueIdByTeam[$away] ?? null, $match->venueId)) {
            $roundNumber = $roundIndex + 1;
            $this->violations[] = "Round {$roundNumber}: {$this->context->teamLabel($away)} was assigned away for a match held at their own home venue.";
        }
    }

    public function observeBye(int $roundIndex, int $teamId): void
    {
    }

    public function violations(): array
    {
        return $this->violations;
    }
}
