<?php

namespace App\Services\ScheduleGeneration;

/**
 * Which construction/search pipeline ScheduleGenerator::generate() runs -
 * see plan.md "Size-Aware Schedule Generation" §5. Phases 1-5 are all
 * present now: the exact solver (§6, Phase 4b) and the two seam variants
 * (§5's "mirrored"/"palindrome" options, Phase 5, absorbing the superseded
 * "Flip vs Invert" TODO section) are both wired in below.
 *
 * Every case is always selectable regardless of league shape (plan.md
 * decision 2.6/2.7) - StrategyRecommender only ever supplies a DEFAULT
 * selection, never a restriction. A poor-fit choice is surfaced as a
 * warning, not disabled.
 */
enum GenerationStrategy: string
{
    /**
     * RoundRobinConstructor's break-minimal construction alone (falling
     * back to a single greedy pass if the venue ownership data makes the
     * construction ineligible - see RoundRobinConstructor::isEligible()),
     * with no further search. Best fit: the season fits inside a single
     * round-robin cycle (rounds <= teams - 1), where there are no
     * rematches, no pass-boundary seam, and breaks already sit at the
     * theoretical minimum - annealing has nothing left to improve (plan.md
     * §1c). A poor fit once the season spans more than one cycle: the
     * un-annealed construction alone leaves the pass-boundary seam and any
     * rematch-spacing/venue-repeat tradeoffs completely unaddressed.
     */
    case SeedOnly = 'seed_only';

    /**
     * Today's full pipeline, unchanged: the same construction phase as
     * SeedOnly, followed by EpsilonConstraintOptimizer's sequential
     * simulated-annealing search. Best fit: multi-cycle seasons (rounds >
     * teams - 1), where there is a pass-boundary seam and rematches for the
     * search to smooth out. A poor fit only in that it spends a search
     * budget a single-cycle season doesn't need - it never produces a worse
     * result than SeedOnly, just a possibly unnecessary one.
     */
    case SeedAndAnneal = 'seed_and_anneal';

    /**
     * RoundBuilder's single randomized greedy pass (no whole-season
     * construction), followed by the same annealing search as
     * SeedAndAnneal. Best fit: venue ownership data that makes
     * RoundRobinConstructor ineligible (a team with no home venue, three or
     * more active teams sharing one venue, or more than one shared-venue
     * pair) - the only strategy that can run at all in that situation. A
     * poor fit otherwise: unlike the seed-based strategies it does not
     * guarantee every pair of teams meets a balanced number of times (see
     * enforceBalancedOpponents()), and it has no whole-schedule visibility
     * the way the circle-method construction does.
     */
    case Greedy = 'greedy';

    /**
     * Decomposed exhaustive search (ExactSolver, plan.md §6): enumerates
     * orderings of one canonical round-robin cycle and solves the optimal
     * home/away orientation for each by dynamic programming, comparing
     * candidates lexicographically over the configured tier order - the
     * exact-arithmetic equivalent of the same objective the annealer only
     * approximates. Best fit: 6 or fewer active teams with an eligible
     * venue structure, where the search comfortably fits its time budget
     * (plan.md §1f). A poor fit above that size (it will very likely spend
     * its whole budget and report best-found rather than proven-optimal)
     * or when the venue structure is ineligible (ScheduleGenerator degrades
     * to Greedy with a warning rather than refusing - decision 2.6). EXACT
     * IS PER-CRITERION: consecutive_venue, full_cycle_spacing, and
     * home_away_break are the only criteria this strategy actually
     * optimises (see ExactSolver's own docblock, "EXACTNESS IS
     * PER-CRITERION") - every other enabled soft criterion (e.g.
     * home_venue_balance, equal_matches_played, balanced_opponents,
     * home_away_balance, the cycle-spacing pair) is neither targeted nor
     * bounded by the search, it simply isn't what the search is choosing
     * between. Per decision 2.5, Exact stays offered regardless of what
     * else is enabled.
     */
    case Exact = 'exact';

    /**
     * Today's pass-boundary behaviour (plan.md §5), made an explicit,
     * separately selectable choice rather than only ever available as
     * SeedAndAnneal's implicit default: at each boundary between
     * round-robin cycles, the next pass repeats the SAME cycle-round
     * pairing order and inverts every team's home/away role. Best fit:
     * a multi-cycle season (rounds > teams - 1) where full_cycle_spacing
     * (rematch spacing) is prioritised over consecutive_venue (venue
     * variety) in the criteria order. Only differs from SeedAndAnneal /
     * SeedPalindromeAndAnneal once the season spans more than one cycle -
     * with a single cycle there is no pass boundary at all and all three
     * produce byte-identical schedules (plan.md §5).
     */
    case SeedMirroredAndAnneal = 'seed_mirrored_and_anneal';

    /**
     * The alternative pass-boundary behaviour verified in plan.md's
     * superseded "Flip vs Invert" section and formalised in §5: at each
     * boundary between round-robin cycles, the next pass REVERSES the
     * cycle-round pairing order (in addition to inverting roles the same
     * way SeedMirroredAndAnneal does), so the round right after the seam
     * reuses the exact same pairing as the round right before it - an
     * immediate rematch, deliberately, but with home/away reversed so the
     * venue changes. This trades that one adjacent rematch for spreading
     * same-venue streaks evenly across every team instead of concentrating
     * them on a couple of teams (plan.md §1a proves, by exhaustive
     * enumeration at 4 teams x 6 weeks, that some such trade is
     * unavoidable). Best fit: a multi-cycle season where consecutive_venue
     * (venue variety) is prioritised over full_cycle_spacing (rematch
     * spacing) in the criteria order - the shipped default order (see
     * config/schedule_generation.php) makes this the common case. Only
     * differs from SeedAndAnneal / SeedMirroredAndAnneal once the season
     * spans more than one cycle - see that case's docblock.
     */
    case SeedPalindromeAndAnneal = 'seed_palindrome_and_anneal';

    public function label(): string
    {
        return match ($this) {
            self::SeedOnly => 'Seed only',
            self::SeedAndAnneal => 'Seed + annealing',
            self::Greedy => 'Greedy',
            self::Exact => 'Exact',
            self::SeedMirroredAndAnneal => 'Seed (mirrored seam) + annealing',
            self::SeedPalindromeAndAnneal => 'Seed (palindrome seam) + annealing',
        };
    }

    /**
     * Shown as help text next to the radio option - what it optimises and
     * when it's a poor fit. Wording is explicitly deferred (plan.md decision
     * 2.8); this is functional, not polished, copy.
     */
    public function helpText(): string
    {
        return match ($this) {
            self::SeedOnly => 'Builds one balanced round-robin schedule and stops - no further search. '
                .'Best when the season fits inside a single round-robin cycle (rounds no more than '
                .'teams minus one): there are no rematches yet, so there is nothing left to improve. '
                .'A poor fit for a longer season, where it leaves the pass-boundary seam and any '
                .'rematch/venue tradeoffs unaddressed.',
            self::SeedAndAnneal => 'Builds the same balanced round-robin schedule, then spends a search budget '
                .'improving it further. Best for a season that spans more than one round-robin cycle, where '
                .'there is a pass-boundary seam and rematches worth smoothing out. Unnecessary (though '
                .'harmless) overhead for a season that fits in a single cycle.',
            self::Greedy => 'Builds the schedule one round at a time with no whole-season construction, then '
                .'spends a search budget improving it. The only option that can run when venue ownership data '
                .'(a team with no home venue, or several teams sharing one) blocks the round-robin construction. '
                .'A poor fit otherwise - it does not guarantee every pair of teams meets a balanced number of '
                .'times the way the seed-based strategies do.',
            self::Exact => 'Exhaustively searches for the best possible combination of venue variety, rematch '
                .'spacing, and home/away breaks (consecutive_venue, full_cycle_spacing, home_away_break) rather '
                .'than a heuristic search - within a time budget, proven optimal if the search completes, '
                .'clearly labelled "best found, not proven optimal" if it runs out of time first. Does NOT '
                .'optimise any OTHER soft criterion that may be enabled (e.g. home_venue_balance, '
                .'equal_matches_played, balanced_opponents, home_away_balance, the cycle-spacing pair) - those '
                .'are left entirely to whatever the construction happens to produce. Best fit: 6 or fewer active '
                .'teams with every team owning its own home venue (or exactly one venue shared by two teams). A '
                .'poor fit above that size, where it will likely spend its whole time budget without proving '
                .'optimality, or when venue ownership data blocks the round-robin construction entirely, in '
                .'which case this falls back to Greedy with a warning rather than refusing to run.',
            self::SeedMirroredAndAnneal => 'Builds the same balanced round-robin schedule as Seed + annealing, '
                .'explicit about how the boundary between round-robin cycles is handled: each new cycle repeats '
                .'the same pairing order and inverts every team\'s home/away role (today\'s default behaviour). '
                .'Best when rematch spacing (full_cycle_spacing) is prioritised over venue variety '
                .'(consecutive_venue) in the criteria order. Only differs from the palindrome variant once the '
                .'season spans more than one round-robin cycle (rounds greater than teams minus one) - with a '
                .'single cycle both produce the identical schedule, so choosing between them is meaningless '
                .'there.',
            self::SeedPalindromeAndAnneal => 'Builds the same balanced round-robin schedule, but reverses the '
                .'pairing order (as well as inverting roles) at each boundary between round-robin cycles. This '
                .'deliberately accepts one immediate rematch at that boundary - with home/away reversed, so the '
                .'venue changes - in exchange for spreading same-venue streaks evenly across every team instead '
                .'of concentrating them on just a couple of teams. Best when venue variety (consecutive_venue) is '
                .'prioritised over rematch spacing (full_cycle_spacing) in the criteria order - the shipped '
                .'default. Only differs from the mirrored variant once the season spans more than one '
                .'round-robin cycle (rounds greater than teams minus one) - with a single cycle both produce the '
                .'identical schedule, so choosing between them is meaningless there.',
        };
    }

    /**
     * Whether ScheduleScorer should register BalancedOpponentMeetingsConstraint
     * as a hard constraint for a schedule built with this strategy - see
     * GenerationConfig::$enforceBalancedOpponents. Both seed-based strategies
     * satisfy it by construction (RoundRobinConstructor) so it stays on; the
     * greedy pass has no such guarantee (see plan.md §4), so forcing it on
     * would turn a currently-working greedy schedule into a hard-invalid
     * degenerate result with no better path available. The greedy strategy
     * instead surfaces a warning on the review screen when the result would
     * have violated it (soft failure, decision 2.6).
     */
    public function enforceBalancedOpponents(): bool
    {
        return match ($this) {
            self::SeedOnly, self::SeedAndAnneal, self::Exact, self::SeedMirroredAndAnneal, self::SeedPalindromeAndAnneal => true,
            self::Greedy => false,
        };
    }

    /**
     * Whether this strategy's seed construction should reverse the
     * cycle-round order on the pass after a seam ("palindrome") rather than
     * repeating it ("mirrored") - see RoundRobinConstructor::construct()'s
     * $palindromeSeam parameter, which this feeds directly. Only
     * SeedPalindromeAndAnneal opts in; every other seed-based strategy
     * (including the plain SeedAndAnneal/SeedOnly cases, which have no
     * explicit seam opinion) keeps today's mirrored behaviour, which is
     * what preserves byte-identical output for every caller that predates
     * the seam-variant strategies.
     */
    public function usesPalindromeSeam(): bool
    {
        return $this === self::SeedPalindromeAndAnneal;
    }

    /**
     * @return string[]
     */
    public static function values(): array
    {
        return array_map(fn (self $case) => $case->value, self::cases());
    }
}
