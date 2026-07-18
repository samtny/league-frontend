<?php

namespace App\Services\ScheduleGeneration\HardConstraints;

use App\Services\ScheduleGeneration\HomeVenueMatch;
use App\Services\ScheduleGeneration\MatchCandidate;
use App\Services\ScheduleGeneration\ScoringContext;

/**
 * One legitimate exception: when the two teams in a match co-own the same
 * shared venue (see RoundRobinConstructor's single-shared-venue-pair
 * support) and happen to play each other there, the "away" team being at
 * their own home venue isn't a scheduling mistake - it's just the two
 * co-tenants meeting on their shared home turf, which is completely normal.
 * The check below only flags it when the venue is NOT also the home team's
 * own - i.e. a genuine case of a team traveling to a match hosted at a
 * venue that happens to be their own turf, which is always a real error.
 */
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

        if (! HomeVenueMatch::isOwnVenue($this->context->homeVenueIdByTeam[$away] ?? null, $match->venueId)) {
            return;
        }

        $owners = $this->context->ownerTeamIdsByVenue[$match->venueId] ?? [];

        if (in_array($match->homeTeamId, $owners, true) && in_array($away, $owners, true)) {
            return;
        }

        $roundNumber = $roundIndex + 1;
        $this->violations[] = "Round {$roundNumber}: {$this->context->teamLabel($away)} was assigned away for a match held at their own home venue.";
    }

    public function observeBye(int $roundIndex, int $teamId): void
    {
    }

    public function violations(): array
    {
        return $this->violations;
    }
}
