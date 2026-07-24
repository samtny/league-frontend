<?php

namespace Tests\Unit\ScheduleGeneration;

use App\Services\ScheduleGeneration\GenerationConfig;
use App\Services\ScheduleGeneration\GenerationStrategy;
use App\Services\ScheduleGeneration\SeededRng;
use App\Services\ScheduleGeneration\StrategyRecommender;
use App\Services\ScheduleGeneration\TeamInput;
use App\Services\ScheduleGeneration\VenueInput;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

/**
 * Table-driven coverage of plan.md §5's pre-selection logic: eligibility
 * first (RoundRobinConstructor::isEligible()), then size (N <= 6 -> Exact,
 * taking priority over regime), then regime (R <= N-1 vs R > N-1, and for
 * the latter the seam variant matching the config's own criteria order)
 * once eligibility is satisfied and the league is too big for Exact. See
 * StrategyRecommender's own docblock for the full rule.
 */
class StrategyRecommenderTest extends TestCase
{
    private function recommender(): StrategyRecommender
    {
        return new StrategyRecommender(new SeededRng(1));
    }

    /**
     * The shipped system default (config/schedule_generation.php):
     * consecutive_venue outranks full_cycle_spacing, so the multi-cycle
     * regime should recommend the palindrome seam variant.
     */
    private function defaultConfig(): GenerationConfig
    {
        return new GenerationConfig(softCriteria: ['consecutive_venue', 'full_cycle_spacing', 'home_away_break']);
    }

    /**
     * The pre-correction ordering (plan.md §1a/§3): full_cycle_spacing
     * outranks consecutive_venue, so the multi-cycle regime should
     * recommend the mirrored seam variant (today's behaviour).
     */
    private function spacingFirstConfig(): GenerationConfig
    {
        return new GenerationConfig(softCriteria: ['full_cycle_spacing', 'consecutive_venue', 'home_away_break']);
    }

    /**
     * @param  array<int, int|null>  $homeVenueIdByTeamId
     * @return TeamInput[]
     */
    private function teamsWithHomeVenues(array $homeVenueIdByTeamId): array
    {
        return array_map(
            fn (int $id, ?int $venueId) => new TeamInput($id, "Team {$id}", $venueId),
            array_keys($homeVenueIdByTeamId),
            array_values($homeVenueIdByTeamId),
        );
    }

    /**
     * @return VenueInput[]
     */
    private function venues(int ...$ids): array
    {
        return array_map(fn (int $id) => new VenueInput($id, "Venue {$id}"), $ids);
    }

    /**
     * Every team owns its own exclusive venue - always eligible regardless
     * of team count.
     *
     * @return array{0: TeamInput[], 1: VenueInput[]}
     */
    private function eligibleLeague(int $teamCount): array
    {
        $homeVenueIdByTeamId = [];
        for ($id = 1; $id <= $teamCount; $id++) {
            $homeVenueIdByTeamId[$id] = 1000 + $id;
        }

        $teams = $this->teamsWithHomeVenues($homeVenueIdByTeamId);
        $venues = array_map(fn (int $venueId) => new VenueInput($venueId, "Venue {$venueId}"), array_values($homeVenueIdByTeamId));

        return [$teams, $venues];
    }

    /**
     * Every case here uses MORE than 6 teams, so the N <= 6 -> Exact
     * priority rule (covered separately below) never fires and the regime
     * rule alone drives the outcome. All use the shipped default criteria
     * order (consecutive_venue outranks full_cycle_spacing), so the
     * multi-cycle cases expect the palindrome seam variant.
     *
     * @return iterable<string, array{0: int, 1: int, 2: GenerationStrategy}>
     */
    public static function eligibleRegimeProvider(): iterable
    {
        // [teamCount, roundCount, expectedStrategy]
        yield '7 teams, 6 rounds (single cycle, R = N-1)' => [7, 6, GenerationStrategy::SeedOnly];
        yield '8 teams, 4 rounds (single cycle, R < N-1)' => [8, 4, GenerationStrategy::SeedOnly];
        yield '8 teams, 10 rounds (multi-cycle, R > N-1)' => [8, 10, GenerationStrategy::SeedPalindromeAndAnneal];
        yield '16 teams, 20 rounds (multi-cycle, R > N-1)' => [16, 20, GenerationStrategy::SeedPalindromeAndAnneal];
        yield '8 teams, 7 rounds (single cycle, R = N-1 exactly)' => [8, 7, GenerationStrategy::SeedOnly];
    }

    #[DataProvider('eligibleRegimeProvider')]
    public function test_regime_drives_the_recommendation_once_eligible_and_too_big_for_exact(int $teamCount, int $roundCount, GenerationStrategy $expected)
    {
        [$teams, $venues] = $this->eligibleLeague($teamCount);

        $recommendation = $this->recommender()->recommend($teams, $venues, $roundCount, $this->defaultConfig());

        $this->assertSame($expected, $recommendation->strategy);
        $this->assertNotSame('', trim($recommendation->reason));
    }

    /**
     * plan.md §5's size-first rule: N <= 6 and eligible recommends Exact
     * regardless of regime, even a single-cycle shape that would otherwise
     * recommend SeedOnly (Exact is seeded with that same construction and
     * can only match or improve on it).
     *
     * @return iterable<string, array{0: int, 1: int}>
     */
    public static function exactEligibleProvider(): iterable
    {
        // [teamCount, roundCount]
        yield '4 teams, 3 rounds (single cycle)' => [4, 3];
        yield '4 teams, 6 rounds (multi-cycle)' => [4, 6];
        yield '5 teams, 10 rounds (multi-cycle)' => [5, 10];
        yield '6 teams, 5 rounds (single cycle, R = N-1)' => [6, 5];
        yield '6 teams, 6 rounds (multi-cycle)' => [6, 6];
    }

    #[DataProvider('exactEligibleProvider')]
    public function test_exact_is_recommended_at_or_below_six_teams_regardless_of_regime(int $teamCount, int $roundCount)
    {
        [$teams, $venues] = $this->eligibleLeague($teamCount);

        $recommendation = $this->recommender()->recommend($teams, $venues, $roundCount, $this->defaultConfig());

        $this->assertSame(GenerationStrategy::Exact, $recommendation->strategy);
        $this->assertNotSame('', trim($recommendation->reason));
    }

    public function test_seven_teams_is_too_big_for_exact_and_falls_through_to_regime()
    {
        [$teams, $venues] = $this->eligibleLeague(7);

        $recommendation = $this->recommender()->recommend($teams, $venues, 6, $this->defaultConfig());

        $this->assertSame(GenerationStrategy::SeedOnly, $recommendation->strategy);
    }

    public function test_ineligible_venue_structure_recommends_greedy_regardless_of_regime()
    {
        // A team with no home venue at all - the classic ineligible shape.
        $teams = $this->teamsWithHomeVenues([1 => 100, 2 => 200, 3 => 300, 4 => null]);
        $venues = $this->venues(100, 200, 300);

        // Single-cycle regime (3 rounds, 4 teams) would otherwise recommend
        // SeedOnly (or, post-Phase-4b, Exact) - eligibility must be checked
        // FIRST and override that.
        $recommendation = $this->recommender()->recommend($teams, $venues, 3, $this->defaultConfig());

        $this->assertSame(GenerationStrategy::Greedy, $recommendation->strategy);
        $this->assertStringContainsString('venue', strtolower($recommendation->reason));
    }

    public function test_three_teams_sharing_one_venue_is_ineligible_and_recommends_greedy()
    {
        $teams = $this->teamsWithHomeVenues([1 => 100, 2 => 100, 3 => 100, 4 => 200]);
        $venues = $this->venues(100, 200);

        $recommendation = $this->recommender()->recommend($teams, $venues, 10, $this->defaultConfig());

        $this->assertSame(GenerationStrategy::Greedy, $recommendation->strategy);
    }

    public function test_two_separate_shared_venue_pairs_is_ineligible_and_recommends_greedy()
    {
        $teams = $this->teamsWithHomeVenues([1 => 100, 2 => 100, 3 => 200, 4 => 200]);
        $venues = $this->venues(100, 200);

        $recommendation = $this->recommender()->recommend($teams, $venues, 10, $this->defaultConfig());

        $this->assertSame(GenerationStrategy::Greedy, $recommendation->strategy);
    }

    public function test_a_single_shared_venue_pair_is_eligible_and_still_regime_driven_above_six_teams()
    {
        $teams = $this->teamsWithHomeVenues([1 => 500, 2 => 500, 3 => 600, 4 => 700, 5 => 800, 6 => 900, 7 => 1000, 8 => 1100]);
        $venues = $this->venues(500, 600, 700, 800, 900, 1000, 1100);

        // 8 teams -> single cycle is R <= 7.
        $singleCycle = $this->recommender()->recommend($teams, $venues, 7, $this->defaultConfig());
        $this->assertSame(GenerationStrategy::SeedOnly, $singleCycle->strategy);

        $multiCycle = $this->recommender()->recommend($teams, $venues, 8, $this->defaultConfig());
        $this->assertSame(GenerationStrategy::SeedPalindromeAndAnneal, $multiCycle->strategy);
    }

    public function test_a_single_shared_venue_pair_at_six_teams_is_eligible_and_recommends_exact()
    {
        $teams = $this->teamsWithHomeVenues([1 => 500, 2 => 500, 3 => 600, 4 => 700, 5 => 800, 6 => 900]);
        $venues = $this->venues(500, 600, 700, 800, 900);

        $recommendation = $this->recommender()->recommend($teams, $venues, 6, $this->defaultConfig());
        $this->assertSame(GenerationStrategy::Exact, $recommendation->strategy);
    }

    /**
     * plan.md §5's seam-variant rule: the multi-cycle regime recommends
     * whichever seam variant matches $config's own criteria order, not a
     * hardcoded choice - palindrome when consecutive_venue outranks
     * full_cycle_spacing (the shipped default, covered by
     * eligibleRegimeProvider above), mirrored otherwise.
     */
    public function test_multi_cycle_regime_recommends_mirrored_when_full_cycle_spacing_outranks_consecutive_venue()
    {
        [$teams, $venues] = $this->eligibleLeague(8);

        $recommendation = $this->recommender()->recommend($teams, $venues, 10, $this->spacingFirstConfig());

        $this->assertSame(GenerationStrategy::SeedMirroredAndAnneal, $recommendation->strategy);
    }

    /**
     * consecutive_venue not enabled at all is treated the same as it
     * losing the tiebreak - mirrored is the safe default rather than a
     * guess (see StrategyRecommender::seamVariantFor()'s docblock).
     */
    public function test_multi_cycle_regime_recommends_mirrored_when_consecutive_venue_is_not_enabled()
    {
        [$teams, $venues] = $this->eligibleLeague(8);
        $config = new GenerationConfig(softCriteria: ['full_cycle_spacing', 'home_away_break']);

        $recommendation = $this->recommender()->recommend($teams, $venues, 10, $config);

        $this->assertSame(GenerationStrategy::SeedMirroredAndAnneal, $recommendation->strategy);
    }

    /**
     * A tie (both in the same tie-group) is also treated as mirrored, not
     * palindrome - "outranks" means strictly ahead, not merely present.
     */
    public function test_multi_cycle_regime_recommends_mirrored_when_tied()
    {
        [$teams, $venues] = $this->eligibleLeague(8);
        $config = new GenerationConfig(softCriteria: [['consecutive_venue', 'full_cycle_spacing']]);

        $recommendation = $this->recommender()->recommend($teams, $venues, 10, $config);

        $this->assertSame(GenerationStrategy::SeedMirroredAndAnneal, $recommendation->strategy);
    }
}
