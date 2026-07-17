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
 * home) rather than "owned by anyone at all": when two+ active teams share
 * the same home venue, that shared slot can become genuinely uncapturable by
 * either owner in a given round (e.g. they're paired against each other that
 * round - neither can be "away" there without tripping H4, so RoundBuilder
 * routes their match elsewhere, leaving the physical slot needing SOME other
 * pair to use it) - a real, demonstrated case (see RoundBuilderTest) where an
 * absolute hard rule has no satisfying assignment at all. Shared-venue
 * scheduling keeps its existing best-effort/soft-only treatment (see
 * HomeVenueBalanceCriterion); only the unambiguous exclusive-ownership case,
 * which is always satisfiable by construction (see RoundRobinConstructor),
 * is enforced as hard.
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
