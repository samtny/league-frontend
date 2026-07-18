# Automatic Schedule Generation — Implementation Plan

## Status

Phases 1-2 shipped (core engine + controller/routes/review screen). Also
shipped, as a post-launch addition: **S5 — Home venue balance** (see below),
covering the real-world fact that each `Team` already has a `venue_id`
("home venue" via `Team::homeVenue()`) and matches should preferentially be
played at one of the two competing teams' own venue, with that team always
labeled `home_team_id`.

### S5 — Home venue balance (added after initial ship)

- **Structural rule** (enforced in `RoundBuilder::assignVenuesAndSides`, not
  just scored): whenever a match is placed at a venue that is one of the two
  paired teams' `homeVenueId`, that team is always `home_team_id`. When
  *both* paired teams have their own eligible home venue free that round
  (including the shared-venue case, e.g. two real teams both pointing at the
  same `venue_id`), whichever team has fewer home-venue appearances so far
  hosts (overdue tie-break). Only when *neither* team has an eligible home
  venue available does venue/side assignment fall back to the original
  generic pool logic (streak-avoidance + home/away label balance).
- **Soft criterion** (`ScheduleScorer`, weight `weightHomeVenueBalance`,
  default 6.0): for each team with a `homeVenueId`, penalize
  `max(0, |homeVenueAppearances - otherAppearances| - 1)` — same shape as
  the existing home/away label-balance penalty, applied to "played at own
  venue" vs. "did not," expressing the "roughly every other week at home"
  goal.
- **Defensive hard check** (`ScheduleScorer`, "H4"): a team assigned away for
  a match at their own home venue is flagged as a hard violation. This can't
  actually happen from generator output (construction prevents it by
  design) but guards hand-built candidates and any future refactor.
- **Explicit product decision**: the existing "avoid consecutive rounds at
  the same venue" soft constraint (S1) was *not* changed to exempt a team's
  own home venue — it still penalizes any consecutive-venue repeat,
  including repeated home games. This means S1 and S5 can pull in opposite
  directions for a given team/round; that tension is intentional and is
  resolved by the weighted-sum scoring, not by special-casing either rule.
- `TeamInput` gained `homeVenueId: ?int` (from `Team.venue_id`), threaded
  through unchanged for teams with none (defaults `null`, exercises the
  original fallback path exactly as before — fully backward compatible).

### Violation messages use team names, not ids

`ScheduleScorer` now builds a `$teamLabel` closure (id -> name, falling back
to `"#id"` only for the H2 "unknown/inactive team" case where no name is
available in the active roster) and every hard/soft violation message uses
it. Locked in by `ScheduleScorerTest::test_violation_messages_use_team_names_not_generic_ids`.

### Home-venue tie-break: recency fix (found via real data, not a config knob)

Investigated a report of "more soft violations than expected for such a
small pool" using the actual association 2 / schedule 6 data (4 active
teams, 4 active venues, each team's own distinct home venue, 7 rounds).
**Empirically ruled out iteration count as the cause first**: ran the
identical input at 500 / 2,000 / 10,000 / 50,000 attempts — score was
*exactly* 15.0 (3 violations) at every budget. A plateau across two orders
of magnitude of attempts means it's not a search-depth problem; a
configurable "number of iterations" field (as originally requested, e.g. on
`/admin/association/{id}/edit`) would not have helped and was deliberately
not built.

Root cause: the original overdue tie-break (`RoundBuilder::assignVenuesAndSides`,
"both teams have an eligible home venue" branch) picked purely on
lowest-cumulative-home-appearance-count, with no memory of whether a team
had *just* hosted last round. With only 4 teams and no byes (every team
plays every round), this let the same team host 3 rounds running (e.g. team
13 "Skylark" hosted rounds 4, 5, and 6 back to back in the real data).

Fix applied: the tie-break now checks recency first (a team that was at
their own home venue last round loses the tie-break to a team that wasn't),
only falling back to the cumulative-count comparison when recency doesn't
distinguish them, and only coin-flipping (via `Rng`, not silently favoring
whichever team is "A" in the pairing tuple) when both recency and count are
genuinely tied. Covered by
`RoundBuilderTest::test_a_team_that_just_hosted_loses_the_home_venue_tie_break_even_with_fewer_cumulative_appearances`.

**Known remaining limit, disclosed rather than chased further**: for this
specific 4-team/4-venue/7-round shape, the score stayed at 15.0 even after
the fix, across 40 seeds x 3,000 attempts each. Root cause: with exactly 4
teams and no byes, two teams that *both* hosted in the same immediately
preceding round can still be paired against each other, and whichever one
wins that round's tie-break necessarily extends their own streak - recency
alone can't disambiguate two teams tied on recency. Breaking this
fully would require a proper round-robin home/away pattern construction
(e.g. a canonical circle-method schedule with break-minimization, a
well-studied sports-scheduling technique), not just a smarter per-round
greedy tie-break - a materially bigger undertaking than this fix. For
context, the theoretical minimum "breaks" for a 4-team round robin is 2
per single round-robin pass (doubling for a double round-robin), so our
observed 3 is already in the neighborhood of optimal for this input, not
obviously broken. Flagged as a possible future enhancement, not pursued now.

## Context & current-state findings (verified in code)

- `ScheduleController::generateRoundsStore()` (app/Http/Controllers/ScheduleController.php:171) validates `generate in:manual,random`, calls `truncateRounds()`, then branches to `createRoundsManual()` or `createRoundsRandom()`. `createRoundsRandom()` (line 250) is an empty TODO stub.
- `createRoundsManual()` (line 216) steps day-by-day from `start_date` to `end_date`, and for each date whose `strtolower(format('D'))` matches `strtolower($weekday)` it creates a `Round`, then calls `$round->createMatches()`.
- `Round::createMatches()` (app/Round.php:47) loops **all** `$association->venues` (no `active` filter — the pre-existing bug) and creates one `PLMatch` per venue with `sequence = 1`, `home_team_id`/`away_team_id` null.
- The matches uniqueness key used by round/edit is `(schedule_id, round_id, venue_id, sequence)`; automatic generation always uses `sequence = 1`.
- `Association` already exposes `activeTeams()`, `inactiveTeams()`, `activeVenues()`, `inactiveVenues()` relations — reuse these, do not re-filter by hand.
- `Team.active` is not cast (truthy checks used elsewhere); `Venue.active` is cast to boolean.
- The wizard flow already guarantees rounds are empty before the select step: `generateRounds()` (line 135) shows `generate-rounds-confirm` when rounds exist, which posts to `generateRoundsDelete()` → `truncateRounds()`. `generateRoundsStore()` also defensively calls `truncateRounds()`.
- `Schedule` soft-deletes and its `booted()` hook hard-deletes child rounds so FK cascades fire; there is **no** draft/state column on `rounds` or `matches`.
- Existing tests: tests/Feature/GenerateRoundsWizardTest.php uses `RefreshDatabase` + Bouncer superadmin; `test_selecting_random_assignment_is_a_noop_...` will need to be rewritten once random is implemented.
- Verified: `SESSION_DRIVER=file` in `.env` — confirms the session-based candidate handoff in §5 is safe (no 4KB cookie-session limit to worry about).

---

## 1. Architecture

Introduce a self-contained service namespace **`App\Services\ScheduleGeneration`** (mirrors the existing `app/Services/PinballMapClient.php` convention). All classes are plain PHP (no Eloquent inside the generator) so they are trivially unit-testable and RNG-deterministic; Eloquent is only touched at the persistence boundary.

New classes:

- **`RoundDatePlanner`** — extracts the date-stepping logic currently inline in `createRoundsManual()`. Single public method `datesFor(string $startDate, string $endDate, string $weekday): array` returning an ordered list of `DateTimeImmutable` round dates. `createRoundsManual()` is refactored to consume this too, so manual and automatic share one source of truth (requirement 1).
- **`Rng`** (interface) with `int(int $maxInclusive): int` and `shuffle(array $items): array`. Two implementations:
  - `MtRng` — production, wraps `random_int`/`fisher-yates`.
  - `SeededRng` — deterministic, seeded `mt_srand($seed)` + `mt_rand`, for tests.
  The generator depends on the interface (constructor-injected), bound to `MtRng` in a service provider.
- **`TeamInput` / `VenueInput`** — lightweight immutable value objects (`id`, `name`) built from active teams/venues, so the generator never holds Eloquent models.
- **`ScheduleGenerator`** — the orchestrator. `generate(array $roundDates, array $activeTeams, array $activeVenues, GenerationConfig $config): GenerationResult`. Runs the randomized-restart loop, delegates per-round construction and scoring.
- **`RoundBuilder`** (internal helper of the generator) — builds a single round's byes + pairings + venue assignments given prior-round state, honoring the hard constraints during construction (fail-fast rejection).
- **`ScheduleScorer`** — pure function `score(ScheduleCandidate $c): GenerationReport`. Computes hard-violation list and soft-penalty breakdown.
- **Value objects**: `MatchCandidate` (`venueId, venueName, homeTeamId, awayTeamId`), `RoundCandidate` (`date, matches[], byeTeamIds[]`), `ScheduleCandidate` (`rounds[]`), `GenerationReport` (hard-pass bool, `hardViolations[]`, `softViolations[]` grouped by criterion, `score`, `degenerate` bool + reason), `GenerationResult` (`ScheduleCandidate $best`, `GenerationReport $report`, `attemptsUsed`, `elapsedMs`).
- **`GenerationConfig`** — DTO built from a new `config/schedule_generation.php` (max_attempts, time_budget_ms, soft weights). Keeps magic numbers out of code.

Invocation from `ScheduleController`: the controller resolves `ScheduleGenerator` from the container, builds inputs from `$schedule->association->activeTeams` / `activeVenues` and `RoundDatePlanner`, calls `generate()`, and stashes the result (see §5). The generator has **zero** knowledge of HTTP, sessions, or Eloquent.

---

## 2. Scoring model

**Hard constraints** (a candidate with any hard violation is rejected as invalid):

- **H1 — no back-to-back opponent repeat.** For every team, its opponent in round *r* must differ from its opponent in round *r-1*. Enforced *during construction* by `RoundBuilder` (reject a candidate pairing edge if the two teams met in the immediately preceding round), and re-verified by `ScheduleScorer` as a safety net.
- **H2 — inactive teams/venues never appear.** Structurally guaranteed, not scored: the generator's only inputs are `activeTeams`/`activeVenues`, so an inactive id is not in the universe it draws from. `ScheduleScorer` still asserts every assigned id is in the active set (defensive; also catches the `createMatches()` bug regression).
- **H3 — a team plays at most one match per round.** Enforced by construction (each team is placed into exactly one of {a pairing, the bye list} per round).

**Soft penalties** (lower total = better; starting weights, tunable via config):

- **S1 — consecutive same-venue** (weight `W_VENUE = 5`): for each team, +`W_VENUE` for each pair of consecutive rounds it plays at the same venue. (Byes break the streak.)
- **S2 — unequal matches played** (weight `W_EQUALITY = 8`): compute each active team's total matches played across the schedule; penalty = `W_EQUALITY * (max − min)`. Near-zero by construction thanks to bye rotation, but scored so pathological candidates lose.
- **S3 — opponent-repeat recency** (weight `W_REPEAT = 3`): when a pairing repeats with a gap of `g` rounds since their last meeting, penalty = `W_REPEAT * max(0, IDEAL_GAP − g)` where `IDEAL_GAP = ceil(activeTeams / 2)`. Repeats spread far apart cost ~0; immediate-ish repeats cost the most.
- **S4 — home/away imbalance** (weight `W_HOMEAWAY = 2`): for each active team, penalty = `W_HOMEAWAY * max(0, |homeCount − awayCount| − 1)` (allows the unavoidable ±1 imbalance when a team plays an odd number of matches). Confirmed with product: home venue should be balanced per team, not a pure coin-flip.

`total_score = W_VENUE·S1 + W_EQUALITY·S2 + W_REPEAT·S3 + W_HOMEAWAY·S4`. Weights ordered `W_EQUALITY > W_VENUE > W_HOMEAWAY > W_REPEAT` to reflect the product priority (equality is the core promise; consecutive-venue is priority *a*; home/away balance and recency are secondary refinements). H1/H2/H3 are absolute and sit above all weights.

**Stopping condition** (requirement 3.e). The generator loops:
1. If a hard-valid candidate with `total_score == 0` is found → return immediately (a "perfect" schedule: all hard + all soft satisfied).
2. Otherwise keep the lowest-scoring hard-valid candidate seen so far.
3. Stop when either `attemptsUsed >= config.max_attempts` (default **500**) **or** `elapsedMs >= config.time_budget_ms` (default **1500ms**), whichever first — the concrete safety bound protecting the web worker.
4. If **no** hard-valid candidate was found within budget → return the least-bad candidate with `report.degenerate = true` and a human reason (e.g. "Could not satisfy the no-back-to-back-opponent rule within N attempts — this usually means too few active teams/venues."). The review screen surfaces this honestly rather than silently committing garbage.

---

## 3. Bye / capacity & pairing algorithm (per round)

State carried across rounds: `lastVenueByTeam[]`, `lastOpponentByTeam[]`, `lastMeetingRoundByPair[]`, `byeCountByTeam[]`, `matchesPlayedByTeam[]`, `homeCountByTeam[]`, `awayCountByTeam[]`.

Per round, `RoundBuilder`:

1. **Capacity.** `capacity = min( floor(|activeTeams| / 2), |activeVenues| )` matches this round.
2. **Choose byes.** Total teams that must sit out = `|activeTeams| − 2·capacity` (covers both the odd-team-out case and the too-many-teams-for-venues case with one mechanism). Pick that many teams by **fair rotation**: sort candidates by `byeCountByTeam` ascending, RNG-shuffle within equal-count ties, take from the front. Increment their `byeCount`. This guarantees no team is byed disproportionately across the schedule (requirement + odd-count decision).
3. **Pair the remaining `2·capacity` teams.** RNG-shuffle them, then greedily match, preferring the partner with the **largest gap since last meeting** (favors S3) and **rejecting** any partner who was this team's opponent last round (enforces H1). If greedy paints itself into a corner (last two teams are a forbidden pair), the whole round build fails → the outer restart loop retries with a fresh shuffle. This is why randomized restarts (not backtracking search) is the right tool at this scale.
4. **Assign pairings to venues.** RNG-shuffle the `capacity` venues; assign greedily preferring a venue that is **not** either team's `lastVenueByTeam` (favors S1). Unused active venues (`|activeVenues| > capacity`) get **no match row** this round — consistent with round/edit rendering "No Match", and per the resolved decision to skip rather than create empty rows.
5. **Home/away.** For each pairing, assign home to whichever of the two teams has the lower `homeCount − awayCount` differential so far (RNG tie-break if equal) — favors S4. Update `homeCountByTeam`/`awayCountByTeam` for both teams.
6. Update all carried state; return the `RoundCandidate`.

Because equality (S2), consecutive-venue (S1), and no-consecutive-opponent (H1) are handled respectively by (a) bye-count rotation, (b) venue-assignment preference, and (c) pairing rejection — they are satisfied *simultaneously per round*, and the outer scorer + restarts pick the globally best assembly.

---

## 4. Controller / route / view changes (file by file)

**routes/web.php** (inside the existing admin group, alongside lines 100–102) — add three routes:
- `GET  {association}/schedule/{schedule}/generate-rounds/review` → `ScheduleController@generateRoundsReview`, name `schedule.generate-rounds.review`.
- `POST {association}/schedule/{schedule}/generate-rounds/accept` → `ScheduleController@generateRoundsAccept`, name `schedule.generate-rounds.accept`.
- `POST {association}/schedule/{schedule}/generate-rounds/retry` → `ScheduleController@generateRoundsRetry`, name `schedule.generate-rounds.retry`.

**app/Http/Controllers/ScheduleController.php**:
- `generateRoundsStore()`: keep the `manual` branch persisting immediately + redirecting to `schedule.view` (unchanged behavior/tests). Change the `random` branch to: build inputs, call `ScheduleGenerator::generate()`, store the `ScheduleCandidate` + `GenerationReport` in the session (§5), redirect to `schedule.generate-rounds.review`. Do **not** persist rounds here.
- New `generateRoundsReview()`: read candidate+report from session (redirect back to `schedule.generate-rounds` if absent, e.g. after refresh/expiry), render the review view.
- New `generateRoundsAccept()`: re-read candidate from session, run `truncateRounds()` (idempotent), persist via new private `persistCandidate(Schedule, ScheduleCandidate)` inside a `DB::transaction`, clear the session keys, redirect to `schedule.view` with a flash message.
- New `generateRoundsRetry()`: re-run `ScheduleGenerator::generate()` (fresh RNG), overwrite session candidate, redirect back to review.
- Refactor `createRoundsManual()` to consume `RoundDatePlanner::datesFor()` (behavior identical; shares date logic per requirement 1).
- New `persistCandidate()`: for each `RoundCandidate` create a `Round` (same field-setting as `createRoundsManual`), then for each `MatchCandidate` create a `PLMatch` directly (name mirrors `createMatches()` format, `sequence = 1`, with `home_team_id`/`away_team_id` set). Byes produce no row. This bypasses `Round::createMatches()` because automatic already knows teams and which venues are used.

**app/Round.php**: fix `createMatches()` to iterate `$association->activeVenues` (or `$association->venues->where('active', true)`) instead of all venues — fixes the pre-existing bug for the manual path and hard-constraint H2 (requirement 3.d).

**resources/views/schedule/generate-rounds-select.blade.php**: relabel the `value="random"` option from "Automatic Random Assignment" to **"Automatic"** (keep the form value `random` so the existing route validation `in:manual,random` and posted param are unchanged — smaller blast radius than renaming the value). Update the label text only.

**resources/views/schedule/generate-rounds-review.blade.php** (new): renders the best candidate as a read-only round-by-round table (Round / Date / Venue / Home / Away, plus a "Byes" line per round). Shows a green "All required constraints satisfied" panel or, if `report.degenerate`, a clear red panel with the reason. Lists unmet soft criteria grouped by type ("Team X plays 2 consecutive rounds at Venue Y", "Teams A and B rematch after only 1 round", "Team Z plays 1 more match than Team W", "Team Q plays 3 more home matches than away"). Two actions only: **Accept** (POST to `schedule.generate-rounds.accept`) and **Discard & Regenerate** (POST to `schedule.generate-rounds.retry`). No inline editing — admins use round/edit.blade.php afterward. Degenerate state should disable/hide Accept (nothing valid to commit) and offer only Regenerate + Cancel.

**config/schedule_generation.php** (new): `max_attempts`, `time_budget_ms`, and the `weights` map (`venue`, `equality`, `repeat`). Bound into `GenerationConfig`.

**app/Providers/AppServiceProvider.php**: bind `Rng::class` → `MtRng::class`.

---

## 5. Persistence decision (candidate held between requests)

**Recommendation: Option (a) — serialize the candidate into the session; write to `rounds`/`matches` only on Accept.**

Rationale vs. the alternatives:

- **(b) draft rows + state column** requires a migration on both `rounds` and `matches` and, worse, forces a "not-a-draft" filter into *every* existing read path (schedule.view, round/edit, standings, score submission, `Association::activeRounds()`, etc.). A single missed filter would leak an unaccepted schedule into scoring. High blast radius for a two-request handoff — rejected.
- **(c) seed + params, reconstruct on Accept** is elegant but fragile: it demands the generator be perfectly deterministic *and* that the input universe (active teams/venues, dates) be byte-identical between the two requests. If an admin toggles a team active in another tab between generate and accept, Accept silently commits a *different* schedule than the one reviewed — violating the "commit exactly what you saw" contract. Rejected.
- **(a)** commits exactly the reviewed bytes, needs **no migration**, and keeps unaccepted data entirely out of the DB so it can never leak into any query. At this scale the payload is tiny (~30 teams × ~15 rounds of small VOs → a few KB, well within session limits). Verified `SESSION_DRIVER=file`, so there's no cookie-size ceiling to worry about.

Concretely: store under schedule-scoped session keys to avoid cross-schedule collisions:
- `schedule_generation.{$schedule->id}.candidate` — the `ScheduleCandidate` (plain arrays; VOs expose `toArray()`/`fromArray()` so nothing relies on PHP object serialization compatibility).
- `schedule_generation.{$schedule->id}.report` — the `GenerationReport`.

`generateRoundsAccept()` reads the candidate, calls `truncateRounds()` then `persistCandidate()` inside `DB::transaction`, and `session()->forget()` both keys. `generateRoundsRetry()` overwrites them. `generateRoundsReview()` redirects to `schedule.generate-rounds` if keys are missing (stale/expired).

**Interaction with existing truncate/delete-confirm flow:** unchanged and safe. Reaching the select step already requires the confirm-delete step to have emptied the rounds, and `truncateRounds()` is idempotent — so calling it again at Accept is a no-op if nothing changed and a correct cleanup otherwise. Because nothing is persisted until Accept, discarding/abandoning the review (closing the tab, session expiry) leaves the schedule in exactly the state it was in at the select step (empty rounds), never a half-written schedule.

---

## 6. Testing strategy

- **Determinism harness:** inject `SeededRng` into `ScheduleGenerator` in tests so runs are reproducible; assert identical output for identical seed+input.
- **Unit tests on `ScheduleScorer`** (no randomness): hand-build `ScheduleCandidate`s and assert exact hard-violation detection (a planted back-to-back opponent flags H1; an inactive id flags H2) and exact soft-penalty numbers (a known consecutive-venue case yields the expected S1 total, etc.).
- **Unit tests on `RoundBuilder`/bye rotation:** feed odd team counts and teams>venues and assert byes are evenly distributed (max−min bye count ≤ 1 across the schedule) and that no team is double-booked in a round.
- **Structural-impossibility tests (H2):** build a generator whose input pools exclude inactive teams/venues and assert no inactive id can ever appear — assert on `activeTeams`/`activeVenues` being the sole input, and add a scorer assertion that fails loudly if an inactive id is ever present.
- **`RoundDatePlanner` test:** matches the July-2026/4-Mondays expectation already encoded in `GenerateRoundsWizardTest`.
- **End-to-end feature test** (new, `AutomaticScheduleGenerationTest`, `RefreshDatabase` + Bouncer superadmin like the existing wizard test): 6 active teams / 2 active venues / ~10 rounds (with a couple of inactive teams and one inactive venue present to prove they're excluded). POST `generate=random` → assert redirect to review, candidate in session, DB still has 0 rounds. POST accept → assert rounds+matches persisted, and assert the persisted schedule is **fully valid**: no inactive team/venue used, no back-to-back opponents, matches-played spread ≤ 1, and no match rows for the inactive venue.
- **Degenerate-input test:** 1 active team (or 1 venue / 30 teams with a tight budget) → assert the generator returns within the time/attempt bound, `report.degenerate = true`, review screen shows the honest message, and Accept is unavailable / commits nothing.
- **Update the existing `test_selecting_random_assignment_is_a_noop_...`** in GenerateRoundsWizardTest.php — it must be rewritten to expect the review redirect instead of a no-op (flag this as an intentional test change in the PR).

---

## 7. Phased rollout (independently shippable PRs)

- **Phase 1 — core engine, no UI.** `RoundDatePlanner`, `Rng`/`MtRng`/`SeededRng`, VOs, `RoundBuilder`, `ScheduleGenerator`, `ScheduleScorer`, `GenerationConfig`, `config/schedule_generation.php`, provider binding. Refactor `createRoundsManual()` onto `RoundDatePlanner` (behavior-preserving). Fix `Round::createMatches()` active-venue bug. Full unit-test suite (scorer, bye rotation, determinism, date planner). Ships value immediately (bug fix + tested engine) with no route/UI change.
- **Phase 2 — controller + persistence + minimal review.** Add the three routes, controller methods, session handoff, `persistCandidate()`, relabel the select option to "Automatic", and a functional (unstyled) review view with Accept/Regenerate. Update/replace the affected wizard test; add the end-to-end accept test. After this phase the feature works end to end.
- **Phase 3 — review-screen UX polish.** Grouped/readable soft-violation reporting, the green/red constraint-summary panels, degenerate-state handling (hide Accept), byes display, styling consistent with round/edit. Add the degenerate-input feature test.

---

## 8. Open questions / assumptions (flag before building)

- **Home/away balance** — confirmed with product: balance each team's home/away split (S4 above), not a pure coin-flip.
- **"Equal matches" scope with byes** — confirmed with product: means *matches actually played* (byes excluded), spread minimized via rotation.
- **Default budget values** (500 attempts / 1500ms) are picked for the observed scale (≤30 teams). If real leagues get larger, these belong in config (already are) but may need tuning; flag for revisit.
- **Session driver:** confirmed `file` in `.env` — comfortably holds a few-KB candidate, no cookie-size concern.
- **Concurrent generation on the same schedule** (two admin tabs) is resolved last-write-wins by the schedule-scoped session key; assumed acceptable for a small-admin app. Accept always truncates first, so no duplication results.
- **Multiple divisions/series sharing venues in the same round** is out of scope — generation is per-`Schedule` as today; cross-schedule venue contention is not modeled. Confirm that matches expectations.

### Critical Files for Implementation
- /Users/sthompson/Documents/league-frontend/app/Http/Controllers/ScheduleController.php
- /Users/sthompson/Documents/league-frontend/app/Round.php
- /Users/sthompson/Documents/league-frontend/routes/web.php
- /Users/sthompson/Documents/league-frontend/resources/views/schedule/generate-rounds-select.blade.php
- /Users/sthompson/Documents/league-frontend/app/Services/ScheduleGeneration/ScheduleGenerator.php (new)

---

# Optimal Round-Robin Construction for the Exclusive-Home-Venue Case (post-plateau enhancement)

## Status

**Implemented (Phases 1-2).** Builds directly on the "Known remaining limit" disclosed at the end of the S5 / recency-fix section above: the greedy, per-round-independent `RoundBuilder::assignVenuesAndSides()` plateaus at score 15.0 on association 2 / schedule 6 (4 teams, 4 distinct owned venues, 7 rounds) regardless of attempt budget, because it never sees the whole-schedule home/away *pattern* that classical round-robin theory proves is achievable. This phase adds a deterministic **classical round-robin + Home-Away-Pattern (HAP) construction** that produces a strong *seed* candidate for the one case where the textbook theory strictly applies (every active team owns a distinct venue), then hands that seed to the **existing** `ScheduleScorer` and **existing** randomized-restart loop as a "seed + polish" floor. It changes nothing about the greedy path for any other input.

`RoundRobinConstructor::isEligible()`/`construct()` and the `ScheduleGenerator::generate()` seed step (§4 below) are built and fully unit-tested (`RoundRobinConstructorTest`, plus 3 new `ScheduleGeneratorTest` cases). §8's primary risk - the exact HA orientation formula - was **not** taken from a primary source; it was derived and verified computationally (Python DP brute force, then reproduced in the shipped PHP) against every even N from 4 to 100, confirmed to hit the N-2 minimum-breaks bound exactly every time, with ≤1 home/away imbalance per team and O(N) rounds × O(N) per-round work (no exponential blowup - "trivial at league scale" holds). The rule that works: round 0 alternates by slot parity; every later round predicts "flip everyone's previous role"; wherever that's structurally infeasible for a match (forced by the pairing, not a choice - happens at roughly every other round), one of the two teams repeats its previous role instead, chosen by "whichever team hasn't already spent its one break, else the higher slot." See `RoundRobinConstructor.php` for the fully-commented implementation.

**Phase 3 (real-data verification) result, replacing the "≈10 expected" estimate honestly rather than leaving it uncorrected:** re-ran the actual association 2 / schedule 6 input (4 teams, 4 distinct owned venues, 7 rounds) through `ScheduleGenerator` directly. The seed itself is hard-valid but scores **20.0** (4 consecutive-same-venue soft violations, S1 = 4 × 5.0), *worse* than the 15.0 greedy plateau - so seed+polish correctly discards it and the final generated schedule still scores exactly **15.0**, identical to before this enhancement. No regression (the guarantee held), but no improvement either, for this specific shape.

Root cause, found via the same computational verification used to derive the HA formula (see `/private/tmp/.../scratchpad/full_sim.py`-style experiments, not hand-derivation): a **single** round-robin cycle (7 rounds ÷ (N-1=3) = 2 full cycles + a 1-round leftover) genuinely does hit the N-2=2 minimum for *each* cycle in isolation. But stitching cycles together (mirroring alternate passes for long-run home/away balance, per §1c) forces an *additional*, seemingly unavoidable break at every pass-to-pass seam: tested both possible flip choices at the boundary for N=4, and *both* produce exactly one new consecutive-same-venue violation (the choice is symmetric - there's no lever here for N=4 specifically). A "continuous" construction that never resets to a fresh per-pass starting pattern (letting the flip-and-resolve algorithm run uninterrupted across all rounds, pass boundaries included) was also tried and tested computationally: it produces the *same* total break count as mirroring, but with materially worse long-run home/away balance (imbalance grows with round count instead of staying ≤1) - so it's strictly worse, not an escape hatch. For this specific 4-team/7-round shape that's 4 pass-like seam events (2 internal-cycle + 2 boundary) × 1 forced break each = 4 consecutive-venue violations, landing at 20.0, just above the 15.0 floor.

This is exactly the class of problem plan.md's own §8 flagged as out of scope to chase ("the exact double-RR break optimum is a known but larger figure... low risk because seed+polish guarantees no regression regardless") and is being disclosed here rather than pursued further, consistent with how the S5/recency-fix section above handled its own remaining limit. **Where the construction does deliver real, verified improvement:** any exclusive-home-venue schedule whose round count fits within a single cycle (`R <= N-1`) reaches the true N-2 minimum-breaks bound exactly (`RoundRobinConstructorTest::test_even_team_count_single_cycle_achieves_the_theoretical_minimum_breaks`), and even multi-cycle schedules keep the unconditional "never worse than greedy-only" guarantee that was the feature's core, non-negotiable correctness promise. Revisit the multi-pass seam cost only if a concrete future case shows it mattering in practice - de Werra's double-RR-specific constructions (distinct from the single-RR result already implemented here) would be the next thing to look up if so.

**Follow-up finding, single-cycle schedules at realistic league sizes (not just N=4):** the 4-team plateau case turns out to be a poor advertisement for this feature precisely *because* it's small - greedy's randomized-restart search has so little to search that it stumbles onto a near-optimal answer within budget by luck alone. Measured greedy-only (500 attempts) vs. the construction seed on single-cycle schedules (`R = N-1`) at increasing team counts, same budget both ways:

| teams | greedy-only score | construction seed score |
|---|---|---|
| 6 | 5.0 | 10.0 (greedy wins here) |
| 8 | 16.0 | 15.0 |
| 10 | 35.0 | 20.0 |
| 12 | 60.0 | 25.0 |

Greedy's per-round-independent search degrades sharply as the search space grows combinatorially with team count; the construction's score grows only linearly (~`5*(N-2)`, i.e. purely from the unavoidable minimum-breaks bound) because it isn't searching at all - it's a closed-form answer. By 8 teams the construction has already overtaken greedy, and the gap widens fast: more than double the penalty left on the table by greedy at 12 teams. The full `ScheduleGenerator` (seed+polish) captures this end to end - confirmed by running it directly, not just scoring the raw seed - landing on 15.0/20.0/25.0 respectively for n=8/10/12 (matching the seed, since polish couldn't beat it). **So the real, honest value proposition is realistically-sized leagues with exclusive home venues (roughly 8+ teams), not the small 4-team example that originally motivated the investigation** - that example just happens to be small enough for greedy's luck to hold up. Worth calling out explicitly to whoever reviews this so the plateau case isn't mistaken for the feature's ceiling.

### S1 reweighted to penalize repeat offenders, not just incident count

Product observation: the flat, linear S1 penalty (`weightVenue` per consecutive-same-venue incident, summed) treats "team A hit twice" identically to "team A hit once, team B hit once" - same total, same score. That's a worse proxy for what actually looks bad to a real admin: the *same team* being stuck at one venue repeatedly reads as a scheduling failure in a way that two different teams each having one isolated repeat doesn't.

This went through three iterations before landing on the current, correct behavior - documented here in full because two of the three were real bugs caught after the fact, not just refinements, and the failure modes are worth remembering:

1. **First attempt (wrong):** aggregate incidents per team, then charge only `max(0, count - 1) * weightVenue` - i.e. tolerate one incident per team entirely, both for scoring *and* for whether a message was generated (a team's first incident produced no message at all, since the summary-message loop only ran when `over > 0`).
2. **Bug found on real production data ("S1 tolerance regression"):** a real generated-and-accepted schedule (association 2 / schedule 6) had Rullo's Team playing at their own venue in both of the last two rounds (2026-08-18 and 2026-08-25) - a real, visible repeat, plainly readable in the round-by-round table - yet the review screen showed `score: 0` and an *empty* violations panel, so nothing on the page indicated it. Root cause: step 1 conflated *scoring severity* (should this cost points) with *message visibility* (should this be reported at all) - tolerating something for score is not the same as hiding it from the admin. Fixed by restoring one message per occurrence, generated unconditionally at the point each incident is detected, independent of whatever the score formula does with it. `array_filter($softViolationsByCriterion)` is what drives whether the review screen's warning panel renders at all, so an empty-by-tolerance list reads as "nothing wrong" - any future scoring change here must not let "tolerated for score" also mean "absent from the message list." (S4/S5 have this identical message-only-past-threshold shape and weren't touched, since nobody has hit a problem there yet, but the same regression could exist - worth a look if it ever comes up.)
3. **Second product correction (this one):** even after fixing visibility, the *score* was still fully forgiving a team's first incident (charging nothing for `count == 1`). Confirmed this doesn't match the actual ask: a single incident is still a real break and should still cost something on its own; the repeat-offense pattern should cost *additional* points on top of the per-occurrence cost, not replace it. Final formula, per team: `weightVenue * count + weightVenue * max(0, count - 1)` - every occurrence costs `weightVenue` (unchanged from the original design), and a team hit more than once pays a further `weightVenue` per occurrence beyond the first. Two different teams each hit once: `2 * weightVenue` (same as always). One team hit twice: `3 * weightVenue` (worse than the distributed case, which was the entire point). Also confirmed the per-team `count` already treated "twice in a row" (a 3-round streak) and "twice, far apart" (e.g. rounds 1-2 and separately rounds 6-7) identically before this correction was needed - the counting logic increments once per detected repeat regardless of when in the season it happens, so no change was needed there.

**Verified findings, rerun end to end against the final formula:**

- **Single-cycle schedules (`R = N-1`), greedy-only (500 attempts) vs. the construction, same budget both ways:**

  | teams | greedy-only | construction (seed alone) | full generator |
  |---|---|---|---|
  | 4 | 5.0 | 5.0 | 5.0 |
  | 5 | 0.0 | 0.0 | 0.0 |
  | 6 | 5.0 | 10.0 | 5.0 (polish rescues the worse seed) |
  | 7 | 5.0 | 0.0 | 0.0 |
  | 8 | 20.0 | 15.0 | 15.0 |
  | 10 | 42.0 | 20.0 | 20.0 |
  | 12 | 74.0 | 25.0 | 25.0 |
  | 16 | 166.0 | 35.0 | 35.0 |

  The construction's score grows only linearly with team count (every occurrence still costs its base `weightVenue`, but the construction never pays a repeat surcharge in a single cycle - true regardless of formula, since no team there ever has more than one incident). Greedy's degrades much faster since its search has no whole-schedule visibility. The n=6 row is worth calling out: this is exactly why "seed + polish" is the shipped design and not "seed alone" - the seed can lose to greedy at small N (structural luck, not a flaw), and the unchanged restart loop transparently picks whichever is actually better.
- **The flagship association-2/schedule-6 case (multi-cycle-plus-leftover) is back to "no regression, no improvement" for this specific shape** - the same honest conclusion from the very first Phase-3 verification, before any S1 change. Re-run against the live database rows: the seed alone now scores **30.0** (worse than greedy's observed 15.0 plateau, since every one of its unavoidable seam incidents now carries its own real cost again, not just the excess), so the full generator correctly falls back to greedy's 15.0 - identical to the pre-enhancement baseline. The earlier claim in this file that this case "flipped to a perfect tie" was true only under the intermediate (step-1/step-2) threshold-only formula and is no longer accurate now that occurrence-based base costs are restored; corrected here rather than left standing.

Net effect of the full S1 rework: the scoring now matches the actual product intent (every break counts, repeat offenders cost more on top), message visibility is unconditional (nothing is ever hidden by tolerance), and the construction's real value proposition is exactly what it was before this detour - single-cycle schedules at realistic team counts (roughly 8+), with an unconditional no-regression guarantee everywhere else, including the flagship shape that doesn't happen to benefit.

## Design decisions carried in from product (do not re-litigate)

- **Seed + polish, not replacement.** The construction yields a starting `ScheduleCandidate`; it is validated by the unchanged `ScheduleScorer`, and the unchanged restart loop runs afterward and may only *improve* on it. Because the loop keeps the lowest-scoring hard-valid candidate seen, seeding it guarantees the shipped result is **never worse than today's** and expected to be better. This is the strongest honest correctness claim and it falls out of the existing loop structure for free.
- **v1 scope boundary — exclusive ownership only.** The construction runs only when **every active team has a non-null `homeVenueId`, all of those ids are distinct (no sharing), and each resolves to an active venue.** Any team with `homeVenueId === null`, any two teams sharing a venue, or a team pointing at a missing/inactive venue → the constructor declines and generation is byte-for-byte today's greedy behavior. The shared-venue and missing-venue cases have **no textbook answer** and are explicitly out of scope; revisit later if real demand appears.

---

## 1. The construction algorithm (hand this to the implementer verbatim, then verify against a primary source)

### 1a. Pairing schedule — the circle (polygon) method

Let `n = count($activeTeams)`. Define the *construction size* `N`:
- `n` even → `N = n`.
- `n` odd → `N = n + 1`, with a synthetic **phantom** team occupying slot `N-1`; whoever the phantom is paired with in a round takes that round's **bye**. This is exactly the bye mechanism the codebase already models via `RoundCandidate::$byeTeamIds`.

Assign the real teams to slots `0 … n-1` (slot order is a free choice; use input order for determinism). `N` is even; a single round-robin is `N-1` rounds.

Circle method, verified for `N=4` below. Number slots `0 … N-1`; slot `N-1` is the **fixed** slot. Let `m = N-1`. For round `k = 0 … N-2`:
- **Fixed-slot game:** slot `N-1` plays the slot `t` with `2·t ≡ k (mod m)` (unique because `m` is odd, so 2 is invertible mod m).
- **Rotating games:** slots `i, j ∈ {0 … N-2}` with `i + j ≡ k (mod m)` and `i ≠ j` are paired.

Worked example, `N=4` (`m=3`):

| Round k | fixed-slot game | rotating game |
|---|---|---|
| 0 | 3 vs 0 (2·0≡0) | 1 vs 2 (1+2≡0) |
| 1 | 3 vs 2 (2·2≡1) | 0 vs 1 (0+1≡1) |
| 2 | 3 vs 1 (2·1≡2) | 0 vs 2 (0+2≡2) |

Every slot meets every other exactly once across the 3 rounds — a valid single round-robin. (An equivalent, more implementer-friendly formulation is the standard "fix one team, rotate the rest around a 2×(N/2) grid, pair top-to-bottom by column." Either is fine; whichever is chosen must be gated by the break-count test in §6.)

### 1b. Canonical home/away pattern achieving the `N-2` minimum-breaks bound

A **break** = a team playing two consecutive rounds with the same home/away status. de Werra (1980/1981, *Scheduling in sports*) proves the minimum total breaks over a single round-robin is `N-2`, achieved by a canonical orientation of the circle-method schedule in which:
- the **fixed** team strictly alternates H,A,H,A,… (0 breaks), and
- exactly two teams are break-free and the remaining `N-2` teams carry one break each.

Orientation for `N=4` (rounds 0,1,2), reusing the pairing above, **computationally verified** (brute-forced both ways of resolving the one free choice below — see verification note): achieves exactly `N-2 = 2` breaks:

| Team | R0 | R1 | R2 | breaks |
|---|---|---|---|---|
| 3 (fixed) | H | A | H | 0 |
| 0 | A | H | A | 0 |
| 1 | H | A | A | 1 (A,A in R1-R2) |
| 2 | A | H | H | 1 (H,H in R1-R2) |

Derivation and a real gotcha for the implementer: fix team 3's pattern to alternate `H,A,H` (0 breaks). Its opponent each round is then forced to the opposite role (R0: 3 vs 0 → 0=A; R1: 3 vs 2 → 2=H; R2: 3 vs 1 → 1=A). Then fix team 0 to also alternate, starting from its forced R0=A → `A,H,A` (0 breaks), which forces *its* opponents too (R1: 0 vs 1 → 1=A; R2: 0 vs 2 → 2=H). At this point teams 1 and 2 already have two of their three roles forced (team 1: R1=A, R2=A; team 2: R1=H, R2=H) — both already sitting on a repeated pair, so both will end up with exactly 1 break regardless. The **only remaining free choice** is R0 for teams 1 and 2 (constrained only to be opposite each other, since they play one another that round) — and this choice is **not arbitrary**: setting team 1=H/team 2=A (completing team 1 as `H,A,A` and team 2 as `A,H,H`) gives the optimal 2 total breaks; the other way round (team 1=A/team 2=H, giving `A,A,A` and `H,H,H`) gives **4** breaks — double the minimum, verified by direct computation. The rule the implementer should follow generally: once a team's role is forced in two consecutive rounds by *other* teams' alternating patterns, complete its own free earlier round(s) to keep its own sequence as alternating as possible, not to satisfy the pairwise-opposite constraint alone (which both choices satisfy equally — only one of them is break-minimal).

**Critical distinction for this codebase — home-breaks vs away-breaks.** In the in-scope case a team's "home" game is always at its own owned venue and every "away" game is at a *different* opponent's venue. Therefore:
- A **home-break** (two consecutive home games) = two consecutive rounds at the **same** venue = a scorer **S1** event.
- An **away-break** (two consecutive away games) = two consecutive rounds at **two different** venues = **not** an S1 event.

So S1 penalizes only the home-breaks. The construction drives *total* breaks to `N-2` and distributes them so roughly half are home-breaks; this is why it beats greedy on S1 specifically, and it means the implementer should report/verify **home-break count** as the S1-relevant figure (in addition to total breaks for theoretical correctness). This distinction should be stated in the code comments.

**Implementer instruction / residual uncertainty (see §8):** the *pairing* construction above is standard and hand-verified. The exact closed-form HA orientation rule for general `N` is the one piece to pin down against a primary source (de Werra 1981; or Miyashiro/Iwasaki/Matsui 2003, *Characterizing feasible pattern sets with a minimum number of breaks*) before coding. The `N-2` break-count unit test in §6 is the ground-truth gate: if the coded orientation does not produce exactly `N-2` breaks for even `N`, the formula is wrong, independent of what this plan says.

### 1c. Double round-robin and multiple cycles

To cover more than one cycle, generate successive **passes**, each a full `N-1`-round canonical single round-robin, and **alternate the orientation each pass** (pass 0 = canonical; pass 1 = all home/away flipped; pass 2 = canonical; …). Flipping H/A on odd passes is the standard mirrored double round-robin and it (a) balances each team's cumulative home/away (helps S4/S5), and (b) moves the home-break from one team to its partner so home-breaks don't concentrate on the same team across passes. The mirrored double round-robin yields on the order of `2(N-2)` breaks; the exact double-RR optimum is a known but larger figure — do not assert a precise constant in code, let the §6 tests measure it.

### 1d. Mapping the abstract schedule onto codebase types

For each constructed round → build a `RoundCandidate($date, $matches, $byeTeamIds)`:
- **Bye:** for odd `n`, the real team paired with the phantom this round → append its id to `$byeTeamIds`, emit no match.
- **Match:** the home team hosts at its own venue. Build `new MatchCandidate(venueId: $homeTeam->homeVenueId, venueName: $venueLookup[$homeTeam->homeVenueId], homeTeamId: $homeTeam->id, awayTeamId: $awayTeam->id)`. Because venues are distinct in-scope, the away team's venue never equals the match venue → **H4 is structurally satisfied**, matching how `RoundBuilder` already guarantees it.
- Collect rounds into `new ScheduleCandidate($rounds)`.

`$venueLookup` is `homeVenueId → VenueInput` built once from `$activeVenues`.

---

## 2. Handling arbitrary round counts

Let `R = count($roundDates)` and `C = N-1` (one cycle). Generate passes lazily and concatenate their rounds until there are at least `R`, then **truncate to exactly `R`**. Two boundary cases:

- **Fewer rounds than one cycle (`R < C`, partial single RR):** take the **first `R` rounds of pass 0**. The canonical schedule's breaks are spread across the full `C` rounds, so a length-`R` prefix has *no more* breaks than the full cycle and remains a valid partial round-robin (all opponents distinct, no repeats). No special truncation logic needed beyond "take the prefix."
- **Leftover beyond whole cycles (`R = q·C + s`, `0 < s < C`):** emit `q` full alternated passes, then the **first `s` rounds of the next (alternated) pass**. Our real target is exactly this: `n=4 → C=3`, `R=7 = 2·3 + 1` → two full passes + one leftover round.

**Why construct the leftover rather than greedy-fill it:** the alternative (fall back to `RoundBuilder` for just the trailing round) reintroduces the exact greedy weakness at the most visible boundary and can pick a venue that manufactures an extra home-break at the seam. Taking the canonical prefix keeps the leftover break-optimal *and* preserves the structural H4/S5 guarantees. The seam between the last full pass and the leftover prefix is the only place H1 (no back-to-back opponent) or an extra break could sneak in; this is caught by running the whole thing through `ScheduleScorer` (§4) — if the seam produces any hard violation, the constructor's output is simply not adopted and generation falls back to the greedy loop.

---

## 3. Eligibility predicate

`RoundRobinConstructor::isEligible(array $activeTeams, array $activeVenues): bool` returns true iff **all** of:
1. `count($activeTeams) >= 3` (2 teams always face each other every round → violates H1; let the existing degenerate path handle it).
2. Every `TeamInput::$homeVenueId !== null`.
3. `homeVenueId` values are pairwise distinct, **with one exception**: at most one venue may be shared by exactly two teams (no venue may have 3+ owners, and no more than one venue may be shared at all) - see "Single Shared-Venue-Pair Extension" below for the mechanics and rationale. This was originally "no sharing at all"; the single-pair case was solved later.
4. Every `homeVenueId` is the id of a venue present in `$activeVenues`.

Notes:
- No upper bound on `n` is needed; the construction is `O(n²)` in rounds×matches, trivial at league scale.
- The venue-capacity deviation (#3 in the problem statement) **cannot bite in-scope**: distinct owned venues imply `count($activeVenues) >= n`, while a round needs only `n/2` venues (one per home team). So byes only ever arise from the odd-`n` phantom, never from venue starvation.
- Because of seed+polish, a permissive edge here is self-correcting (a non-hard-valid seed is discarded), but keep the predicate strict for clarity.

---

## 4. Wiring into the existing architecture

New class **`App\Services\ScheduleGeneration\RoundRobinConstructor`** — plain PHP, **no `Rng`** (fully deterministic; reproducibility for free). Public surface:

```
public function isEligible(array $activeTeams, array $activeVenues): bool
public function construct(array $roundDates, array $activeTeams, array $activeVenues): ?ScheduleCandidate  // null if ineligible
```

**`ScheduleGenerator::generate()` changes** (after the three existing degenerate guards, before the `while` loop):

1. `$seed = (new RoundRobinConstructor())->construct($roundDates, $activeTeams, $activeVenues);`
   - Instantiate internally (like `new RoundBuilder($this->rng)`), so the existing 2-arg constructor and every current call site / test (`new ScheduleGenerator($rng, $scorer)`, controller `app(ScheduleGenerator::class)`) are untouched.
2. If `$seed !== null`, score it with the **unchanged** `$this->scorer->score(...)`:
   - If hard-valid and `score <= 0.0` → return it immediately (identical short-circuit to today's perfect-attempt path).
   - If hard-valid and `score > 0.0` → set `$best = $seed`, `$bestReport = $report`, then **fall through into the existing loop**, which can only replace `$best` with a strictly lower-scoring candidate. This is the "polish."
   - If not hard-valid (unexpected seam issue) → discard it; behavior is exactly today's.
3. The existing loop, `$best === null` fallback, and all degenerate handling are otherwise **unchanged**.

The constructor has zero knowledge of HTTP/Eloquent/session; the controller (`generateAutomaticCandidate`) and persistence path are untouched — the seed flows out as an ordinary `ScheduleCandidate`.

---

## 5. Interaction with the other constraints (in-scope)

- **H1 (no back-to-back opponent):** within a single RR each pair meets once → impossible to repeat inside a pass. The only exposure is a pass/leftover seam; the scorer re-check catches it and the generator falls back if it ever fires. Confirmed safe by construction + safety net.
- **H4 (never away at own venue):** structurally guaranteed — home team always hosts at its own distinct venue (§1d).
- **S1 (consecutive same venue):** the whole point — minimized via the `N-2` break bound; only *home*-breaks cost S1 (§1b), and mirroring keeps them from stacking on one team.
- **S2 (equal matches played):** a full pass gives every team exactly `N-2` real games (for odd `n`, the phantom-paired team byes once per pass; byes rotate evenly by the circle rotation). Across `q` full passes everyone is equal; a length-`s` leftover adds at most one game to some teams → spread `≤ 1`, same as today. Confirmed near-zero by construction.
- **S3 (opponent-repeat recency):** a pair meets at most once per pass, so the gap between rematches is `≥ N-1 = n-1 ≥ ceil(n/2) = idealGap` for `n ≥ 2` → S3 penalty is 0. Confirmed by construction.
- **S4 (home/away label balance) & S5 (home-venue balance):** in-scope, "home label" ⟺ "at own venue," so S4 and S5 measure the same thing. The canonical pattern gives near-balance and alternating-pass mirroring flips it, driving both toward 0 (±1 residual on odd totals). This is the metric that was elevated in the 15.0 case.
- **Byes (odd `n`):** phantom pairings map straight onto `byeTeamIds`; circle rotation rotates the phantom's partner, giving the even bye distribution the existing tests already assert.

---

## 6. Testing strategy

Unit tests on `RoundRobinConstructor` (deterministic, no RNG needed):
- **Even single RR, `n ∈ {4,6,8}`, `R = N-1`:** assert (a) valid RR — every pair meets exactly once, no back-to-back repeat; (b) **total breaks == `N-2`** (the theoretical-minimum gate — this is what catches a wrong HA formula); (c) every match is at the home team's owned venue (H4); (d) home/away counts balanced to ±1.
- **Odd single RR, `n = 5`:** phantom path — assert byes rotate evenly (`max−min ≤ 1`), schedule hard-valid, balanced, low score.
- **Double RR, `n = 4`, `R = 6`:** assert mirrored orientation, home/away balanced per team, and record the total break count (assert it matches the mirrored figure the primary source predicts, whatever the §1c number resolves to).
- **Arbitrary R, partial cycle (`n=4, R=2`)** and **leftover (`n=4, R=7`)**: hard-valid, opponents distinct across the seam.
- **Ineligibility → `construct()` returns null:** shared venue (two teams same `homeVenueId`), a null `homeVenueId`, a `homeVenueId` not in `$activeVenues`, and `n=2`.

Integration / benchmark:
- **Association-2/schedule-6 shape via `ScheduleGenerator` (4 teams, 4 distinct owned venues, 7 rounds, `SeededRng`):** assert `hardConstraintsSatisfied`, `!degenerate`, and **`score < 15.0`** (strictly better than the recorded greedy plateau). *Expected value with reasoning:* two mirrored passes over 6 rounds contribute ≈2 home-breaks (S1 ≈ 10) with S3≈0 and S4/S5≈0, plus at most one seam/leftover home-break, so I expect the result in the ≈10 range rather than 15; pin the exact number once observed and assert `<= 15.0` as the guaranteed floor (seed+polish makes "no worse than today" a hard guarantee, so this assertion can never regress even if the estimate is off).
- **Non-regression:** an existing greedy-path input (a team with `homeVenueId === null`, or two sharing a venue — the existing `test_two_teams_sharing_a_home_venue_still_produce_a_valid_schedule`) must produce **identical** output to today (constructor declines, greedy path unchanged).

---

## 7. Phased rollout (independently reviewable)

- **Phase 1 — pure construction + math proof, no wiring.** `RoundRobinConstructor` (`isEligible` + `construct`), circle-method pairing, canonical HA, phantom byes, mirrored passes, arbitrary-`R` truncation, type mapping. Full unit suite asserting the `N-2` bound, valid RR, H4, balance, and eligibility declines. Ships nothing user-visible; provable in isolation.
- **Phase 2 — seed into `ScheduleGenerator`.** The `generate()` changes in §4 (seed → score → short-circuit-or-polish-or-discard). Add the association-2/schedule-6 benchmark test and the non-regression test. After this phase the feature is live end-to-end (no controller/route/view changes — the seed flows through the existing candidate/session/review/accept machinery untouched).
- **Phase 3 — verification against real data.** Re-run the actual association 2 / schedule 6 input through the HTTP flow, confirm the review screen shows the improved score and the constructed pattern, and record the observed score in `plan.md` to replace the "≈10 expected" estimate with the real number.

---

## 8. Open questions / risks

- **Exact HA orientation formula (primary risk, partially de-risked).** The `N=4` case in §1b was computationally brute-forced (not just hand-derived) after an initial hand-derivation turned out to be self-contradictory (two teams in the same head-to-head match were both marked with the same H/A status, which is impossible) — the corrected table above is verified consistent and break-minimal. That brute-force also surfaced a real trap: the "free" role choice for the two non-fixed, non-alternating teams is constrained only to be mutually opposite, and *both* ways of resolving it satisfy that constraint, but one gives 2 breaks and the other gives 4 — so "satisfies the pairing constraint" is not sufficient, the implementer must pick the alternating-completion option. This trap likely recurs at every level of the construction for larger `N` (more teams whose roles get forced late), so **the general closed-form rule for arbitrary even `N` is still unconfirmed** and must be verified against a primary source (de Werra 1981; or Miyashiro/Iwasaki/Matsui 2003) or brute-forced the same way for at least `N=6` before coding — do not assume the `N=4` pattern generalizes by simple extrapolation. The Phase-1 "total breaks == `N-2`" test remains the ground-truth gate regardless.
- **Exact double-RR break optimum.** `2(N-2)` is the task's stated approximation; the true mirrored double-RR figure is larger and known. Don't hardcode a constant — measure in tests. Low risk because seed+polish guarantees no regression regardless.
- **Benchmark expectation is an estimate.** The ≈10 predicted score for schedule 6 is reasoning, not measurement; Phase 3 replaces it with the observed value. The hard guarantee is only `<= 15.0`.
- **Seam between passes/leftover.** Sole in-scope place H1 or an extra break can appear; mitigated entirely by the existing scorer re-check + fallback, but worth an explicit seam-focused assertion in Phase 1.
- **Determinism vs. the polish loop.** The constructor is deterministic (no `Rng`); the polish loop remains seeded. Confirm the seeded end-to-end test stays reproducible with the seed injected as `best` before the loop runs.
- **Out of scope, stated plainly:** missing-venue teams have no textbook construction and are deliberately left on the greedy path; `n=2`; and any cross-schedule venue contention (already out of scope for the whole feature). The single-shared-venue-pair case originally listed here as out of scope was later solved - see "Single Shared-Venue-Pair Extension" below.

### Critical Files for Implementation (this section)
- /Users/sthompson/Documents/league-frontend/app/Services/ScheduleGeneration/RoundRobinConstructor.php (new)
- /Users/sthompson/Documents/league-frontend/app/Services/ScheduleGeneration/ScheduleGenerator.php
- /Users/sthompson/Documents/league-frontend/app/Services/ScheduleGeneration/ScheduleScorer.php
- /Users/sthompson/Documents/league-frontend/app/Services/ScheduleGeneration/MatchCandidate.php
- /Users/sthompson/Documents/league-frontend/tests/Unit/ScheduleGeneration/RoundRobinConstructorTest.php (new)

---

# Single Shared-Venue-Pair Extension to RoundRobinConstructor (post-launch enhancement)

## Status

**Implemented.** Motivated by a real production case: association 2 / schedule 11, 14 active teams, 10 rounds, `home_away_break` the only enabled soft criterion - Team 20 and Team 27 both list venue 16 as their home venue. That single collision made `isEligible()` decline unconditionally (the "no sharing" rule from §3 above), so every generation fell back to `RoundBuilder`'s greedy pass. Measured directly against the real data: greedy averaged ~30-36 total breaks (0-2 teams break-free out of 14) whether or not the collision was even present - the collision's real cost wasn't degrading greedy further, it was permanently blocking access to the deterministic seed, which reaches the true 10-break/4-perfect-team optimum for this shape (verified below) with zero polish needed.

**Two candidate approaches were floated and evaluated before landing on the shipped design:**

1. *"Decide once, let alternation self-solve."* Checked directly against the real teams' actual input-order slots (20 and 27 landed on slots 5 and 12): their roles do NOT stay complementary for the whole cycle - both-home collisions occurred at rounds 4, 6, and 8 of a 13-round cycle, both-away at rounds 3/5/7/9, complementary elsewhere. The per-round "global flip" mechanism's occasional per-slot "hold" (see the class docblock) can desync any arbitrary pair of slots multiple times per cycle, so this doesn't hold for an arbitrary slot pair - the pair needs to land on a specifically SAFE slot pair, not just any adjacent one (see the "Fairness correction" subsection below - the first shipped version of this got that distinction wrong).
2. *"Give one co-owner a synthetic distinct venue, solve normally, swap back, retry until valid."* Rejected: at the time, `RoundRobinConstructor` had no `Rng` dependency at all (fully deterministic by design) - there was nothing to vary between "retries" of the same input, so this would either always succeed or always fail, never probabilistically improve. (This reasoning was later overtaken by events - see "Fairness correction," which added an `Rng` dependency for a different reason - but the retry idea itself remained unnecessary even after that, since safe slot pairs are now found deterministically, not searched for by chance.)

## Design

- **`RoundRobinConstructor::isEligible()`**: relaxed from "every `homeVenueId` pairwise distinct" to "every venue has at most 2 owners, and at most one venue may have exactly 2." Three or more teams sharing a venue, or two separate shared-venue pairs, both still decline (no textbook/verified construction for either - out of scope, same reasoning as the original "no sharing" rule).
- **`findSafeSlotPairs()` / `assignTeamsToSlots()`** (new, private - see "Fairness correction" below for why this isn't simply "any adjacent pair"): when exactly one shared pair exists, computes which adjacent slot pairs are safe directly from the built cycle, then places the pair on one of them.
- **`AwayTeamAtOwnVenueConstraint`**: gained one exception - an away team being at "their own venue" is not a violation when the home team ALSO co-owns that same venue (i.e. the two co-tenants playing each other at their shared venue - a completely normal match, not an error). Without this, the pair's own head-to-head round would be unsatisfiable under the old absolute rule, since literally no genuinely neutral venue exists in a fully-packed venue roster (every venue owned by someone) - confirmed this is exactly the real association-2 shape (14 teams, 13 venues, all 13 claimed). `HomeTeamAtAnotherTeamsVenueConstraint` already permitted any team at a 2+-owner venue (its `count($owners) === 1` check), so it needed no change.
- `RoundBuilder`'s own greedy-path collision-avoidance (routing a shared pair's head-to-head match away from their venue) is now unnecessarily conservative given the relaxed constraint, but was left as-is - a missed optimization on the path this feature doesn't target, not a correctness issue.

**Verified end to end against the real association-2/schedule-11 data:** `isEligible()` now returns `true`; the seed alone (no polish) reaches exactly 10 total breaks / 4 perfect teams (score 0.0714) versus the previously-accepted schedule's 24 breaks / 4 perfect teams (score 0.2) and the greedy-only baseline's 30-36 breaks / 0-2 perfect teams - matching the theoretical optimum computed for the collision-free hypothetical case exactly.

### Fairness correction (found via real usage, not theoretical - same day as initial ship)

The first shipped version placed the shared pair on *whichever* adjacent slots they happened to land on after a plain `input order` team-to-slot mapping - i.e. team-to-slot assignment was fully deterministic (no `Rng` at all), and the docblock claimed (from an incomplete check that only sampled a handful of the 91 possible slot-pairs, not all of them) that *any* adjacent pair was collision-free. Both halves of this were wrong, reported directly by the user after real use: (1) the same ~4 teams got the break-free slots on every single generation, since slot assignment never varied at all; (2) once slot assignment WAS randomized to fix that, a proper exhaustive check (all 13 possible adjacent pairs for `N=14`, using the raw per-slot role data rather than inferring from one incidental run) showed only about half of them are actually collision-free - e.g. slots (9,10) has a real both-home collision at round 4. A 500-seed stress test of the "randomize freely + pull adjacent" approach found real double-booking failures in 219/500 seeds (44%).

The corrected design: `RoundRobinConstructor` now takes an `Rng` (previously none) and randomizes team-to-slot assignment on every `construct()` call, for fairness - this alone fixes issue (1) for the no-shared-venue case. For a shared pair specifically, `findSafeSlotPairs()` computes, directly from the built cycle, exactly which adjacent slot pairs never leave the pair's roles simultaneously equal - checked in BOTH orientations (unflipped AND flipped, since a later multi-cycle pass flips every role, and a pair safe only in one direction, e.g. (11,12), would still collide once a flipped pass is reached). The safe set turned out to have a clean, consistent shape once checked properly - `(0,1), (2,3), (4,5), ...` (every other adjacent pair starting at slot 0) - verified for `N` from 3 to 21 (odd and even), always at least one safe pair. This is computed at runtime rather than hardcoded, matching how the rest of this class treats its break-minimal pattern (verified computationally, not assumed) - cheap (`O(n)`) and stays correct if `buildSingleCycle()`'s tie-break logic ever changes. `assignTeamsToSlots()` then picks one safe pair at random, randomly assigns the two co-owners between its two slots, and shuffles everyone else into the remaining slots. Re-ran the 500-seed stress test against the corrected implementation: 0 failures, and which teams end up "perfect" is now reasonably even across all 14 teams (12-17% each per seed, no team ever excluded or dominant).

## Testing

- `RoundRobinConstructorTest`: `test_accepts_exactly_one_shared_venue_pair`, `test_declines_when_three_teams_share_a_home_venue`, `test_declines_when_two_separate_venues_are_each_shared_by_a_pair`, `test_shared_venue_pair_never_both_marked_home_the_same_round_across_many_seeds_and_a_double_cycle` (30 seeds x a 26-round double cycle, specifically to catch the flip-safety bug), `test_team_to_slot_placement_varies_across_generations`, and `test_shared_venue_pair_placement_varies_across_generations` (both assert placement isn't pinned to one outcome across 30 seeds - the actual fairness bug report).
- `ScheduleGeneratorTest`: `test_single_shared_venue_pair_is_eligible_and_reaches_the_construction_seed` (end-to-end short-circuit at 0 attempts/0 score for a short enough schedule); the pre-existing `test_shared_venue_input_is_ineligible_for_the_round_robin_seed_so_behavior_is_unchanged` was renamed to `test_partial_null_venue_input_is_ineligible_...` and its comment corrected - that fixture's ineligibility was always caused by teams 3-6 having no venue at all, not by teams 1/2 sharing one, so its assertion didn't need to change, only its stale name/rationale.
- All `RoundRobinConstructor` call sites (production and tests) updated to inject an `Rng` - `SeededRng` in tests, for reproducibility.

## Open questions / risks

- **Odd `N` with a shared pair was checked for safe-pair existence (`N=3` through `N=21`, always found at least one) but not stress-tested at production scale the way `N=14` was** (500-seed sweep). Low risk in practice (seed+polish's non-regression guarantee still holds even if some odd-`N` shared-pair shape turned out imperfect), but worth a similar stress test if a real odd-`N` shared-venue case shows up.
- **Two separate shared pairs, or a venue shared by 3+, remain unsolved** - same "no textbook answer, out of scope" reasoning as the original single-pair case, revisit if real demand appears.
- **The "safe pairs are every-other-adjacent-pair-starting-at-0" shape was observed, not proven** - it held for every `N` checked (3-21) but wasn't derived from first principles the way the `N-2` break-minimum bound was. `findSafeSlotPairs()` doesn't rely on this shape (it re-derives the actual safe set at runtime for whatever `N` it's given), so this is a curiosity, not a correctness risk.
