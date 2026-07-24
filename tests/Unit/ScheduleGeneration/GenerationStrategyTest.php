<?php

namespace Tests\Unit\ScheduleGeneration;

use App\Services\ScheduleGeneration\GenerationStrategy;
use Tests\TestCase;

class GenerationStrategyTest extends TestCase
{
    public function test_every_case_has_a_non_empty_label_and_help_text()
    {
        foreach (GenerationStrategy::cases() as $strategy) {
            $this->assertNotSame('', trim($strategy->label()));
            $this->assertNotSame('', trim($strategy->helpText()));
        }
    }

    /**
     * Locks in the working names from plan.md §5 as the backing values -
     * these are what the select-screen radio inputs and the strategy
     * request parameter both key off, so a silent rename here would be a
     * breaking API change even though wording is otherwise deferred
     * (decision 2.8).
     */
    public function test_backing_values_are_the_working_names_from_the_plan()
    {
        $this->assertSame('seed_only', GenerationStrategy::SeedOnly->value);
        $this->assertSame('seed_and_anneal', GenerationStrategy::SeedAndAnneal->value);
        $this->assertSame('greedy', GenerationStrategy::Greedy->value);
        $this->assertSame('exact', GenerationStrategy::Exact->value);
        $this->assertSame('seed_mirrored_and_anneal', GenerationStrategy::SeedMirroredAndAnneal->value);
        $this->assertSame('seed_palindrome_and_anneal', GenerationStrategy::SeedPalindromeAndAnneal->value);
    }

    /**
     * See GenerationConfig::$enforceBalancedOpponents and plan.md §4: every
     * seed-based strategy (including Exact and both seam variants, which
     * are all seeded by/built on RoundRobinConstructor) satisfies the
     * constraint by construction, so it stays on; Greedy has no such
     * guarantee, so it runs with it off and relies on a review-screen
     * warning instead (decision 2.6).
     */
    public function test_only_greedy_disables_balanced_opponents_enforcement()
    {
        $this->assertTrue(GenerationStrategy::SeedOnly->enforceBalancedOpponents());
        $this->assertTrue(GenerationStrategy::SeedAndAnneal->enforceBalancedOpponents());
        $this->assertTrue(GenerationStrategy::Exact->enforceBalancedOpponents());
        $this->assertTrue(GenerationStrategy::SeedMirroredAndAnneal->enforceBalancedOpponents());
        $this->assertTrue(GenerationStrategy::SeedPalindromeAndAnneal->enforceBalancedOpponents());
        $this->assertFalse(GenerationStrategy::Greedy->enforceBalancedOpponents());
    }

    public function test_values_lists_every_case()
    {
        $this->assertEqualsCanonicalizing(
            ['seed_only', 'seed_and_anneal', 'greedy', 'exact', 'seed_mirrored_and_anneal', 'seed_palindrome_and_anneal'],
            GenerationStrategy::values(),
        );
    }

    /**
     * Phase 4b/5 (plan.md §10): the exact solver and the two seam-variant
     * strategies are now wired in, on top of the 3 cases Phase 3 shipped.
     */
    public function test_every_phase_4b_and_5_case_exists()
    {
        $this->assertCount(6, GenerationStrategy::cases());
    }

    /**
     * Only the palindrome seam variant opts into reversing the cycle-round
     * order at a pass boundary - every other strategy (including the plain
     * SeedAndAnneal/SeedOnly cases and the mirrored seam variant) keeps
     * today's mirrored behaviour, which is what RoundRobinConstructor's
     * $palindromeSeam default (false) is built to guarantee stays
     * byte-identical for every pre-existing caller.
     */
    public function test_only_the_palindrome_variant_uses_the_palindrome_seam()
    {
        foreach (GenerationStrategy::cases() as $strategy) {
            $expected = $strategy === GenerationStrategy::SeedPalindromeAndAnneal;
            $this->assertSame($expected, $strategy->usesPalindromeSeam(), "unexpected usesPalindromeSeam() for {$strategy->value}");
        }
    }
}
