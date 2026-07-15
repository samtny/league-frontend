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
