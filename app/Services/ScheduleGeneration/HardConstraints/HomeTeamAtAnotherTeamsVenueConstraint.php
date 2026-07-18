<?php

namespace App\Services\ScheduleGeneration\HardConstraints;

use App\Services\ScheduleGeneration\MatchCandidate;
use App\Services\ScheduleGeneration\ScoringContext;

/**
 * The home-side mirror of AwayTeamAtOwnVenueConstraint (H4: a team can never
 * be away at its own home venue) - this rejects the opposite direction: a
 * team can never be marked HOME at a match played at a DIFFERENT team's
 * EXCLUSIVELY-owned home venue. A genuinely neutral venue (owned by no
 * active team, or by the home team itself) is unaffected.
 *
 * Scoped to exclusive ownership (exactly one active team calling a venue
 * home) rather than "owned by anyone at all": a venue with 2+ owners is
 * never flagged here regardless of who's marked home, since ANY of its
 * owners (or, on the greedy path, any other team routed there as a neutral
 * choice) hosting there is fine. The two-owner case specifically - the co-
 * owners playing each other at their shared venue - is a genuinely normal
 * match, not an edge case to route around; see the matching exception in
 * AwayTeamAtOwnVenueConstraint and RoundRobinConstructor's single-shared-
 * venue-pair support. RoundBuilder's greedy path still conservatively avoids
 * assigning a co-owned pair's own shared venue to their own match (see
 * assignVenuesAndSides()) even though it's no longer required to - that's a
 * missed-optimization, not a correctness issue, and wasn't in scope here.
 */
final class HomeTeamAtAnotherTeamsVenueConstraint implements HardConstraint
{
    /** @var string[] */
    private array $violations = [];

    public function __construct(
        private readonly ScoringContext $context,
    ) {
    }

    public function key(): string
    {
        return 'home_team_at_another_teams_venue';
    }

    public function label(): string
    {
        return "Home team assigned to a match at a different team's exclusively-owned home venue";
    }

    public function startRound(int $roundIndex): void
    {
    }

    public function observeMatch(int $roundIndex, MatchCandidate $match): void
    {
        $owners = $this->context->ownerTeamIdsByVenue[$match->venueId] ?? [];

        if (count($owners) === 1 && $owners[0] !== $match->homeTeamId) {
            $roundNumber = $roundIndex + 1;
            $ownerLabel = $this->context->teamLabel($owners[0]);
            $this->violations[] = "Round {$roundNumber}: {$this->context->teamLabel($match->homeTeamId)} was assigned home for a match at {$match->venueName}, which is {$ownerLabel}'s home venue.";
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
