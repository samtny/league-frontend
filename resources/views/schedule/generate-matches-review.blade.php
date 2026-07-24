@extends('layouts.admin')

@section('title', 'Review Automatically Generated Matches')

@section('breadcrumb')
    {{ Breadcrumbs::render('schedule.generate-matches.review', $schedule) }}
@endsection

@section('content')
    <div class="row">
        <h1 class="col">Review Automatically Generated Matches</h1>
    </div>

    @php
        $strategyRan = $report->strategy ? \App\Services\ScheduleGeneration\GenerationStrategy::tryFrom($report->strategy) : null;
    @endphp

    @if ($strategyRan)
        <div class="row mb-3">
            <div class="col">
                <div class="alert alert-secondary">
                    <strong>Strategy used:</strong> {{ $strategyRan->label() }}
                    @if ($report->provenOptimal !== null)
                        <br>
                        @if ($report->provenOptimal)
                            <strong>Proven optimal:</strong> this is the best possible result for venue variety,
                            rematch spacing, and home/away breaks together - no other schedule using this
                            construction could score better on those three criteria.
                        @else
                            <strong>Best found, not proven optimal:</strong> the exhaustive search ran out of its
                            time budget before it could rule out every alternative, so a better result may exist.
                            This is never worse than the plain round-robin construction, though.
                        @endif
                    @endif
                </div>
            </div>
        </div>
    @endif

    @if (! empty($report->strategyWarning))
        <div class="row mb-3">
            <div class="col">
                <div class="alert alert-warning">
                    {{ $report->strategyWarning }}
                </div>
            </div>
        </div>
    @endif

    @if ($report->degenerate)
        <div class="row mb-3">
            <div class="col">
                <div class="alert alert-danger">
                    {{ $report->degenerateReason }}
                </div>
            </div>
        </div>
    @else
        <div class="row mb-3">
            <div class="col">
                <div class="alert alert-success">
                    All required constraints were satisfied (only active teams and venues were used, with no scheduling conflicts).
                </div>
            </div>
        </div>
    @endif

    @if (! empty($report->balancedOpponentsViolations))
        <div class="row mb-3">
            <div class="col">
                <div class="alert alert-warning">
                    <strong>Some pairs of teams don't meet a balanced number of times:</strong> the Greedy strategy
                    does not guarantee this the way the seed-based strategies do.
                    <ul class="mb-0">
                        @foreach ($report->balancedOpponentsViolations as $message)
                            <li>{{ $message }}</li>
                        @endforeach
                    </ul>
                </div>
            </div>
        </div>
    @endif

    @if (! empty($report->softCriteriaScores))
        @php
            // Criteria tied into the same priority tier (see
            // GenerationConfig::tierWeight()/ChebyshevTieBreak) share the
            // IDENTICAL weight value and are always emitted adjacently (tier
            // order) - grouping consecutive same-weight entries is a
            // reliable, purely report-driven way to detect a tie-group
            // without needing GenerationConfig's own tier structure here.
            $criteriaGroups = [];
            foreach ($report->softCriteriaScores as $criterion) {
                $lastGroup = count($criteriaGroups) - 1;

                if ($lastGroup >= 0 && $criteriaGroups[$lastGroup][0]['weight'] === $criterion['weight']) {
                    $criteriaGroups[$lastGroup][] = $criterion;
                } else {
                    $criteriaGroups[] = [$criterion];
                }
            }
        @endphp
        <div class="row mb-3">
            <div class="col">
                <div class="alert alert-info">
                    <strong>Soft criteria scores:</strong>
                    <ul class="mb-0">
                        @foreach ($criteriaGroups as $group)
                            <li>
                                @foreach ($group as $criterion)
                                    {{ $criterion['label'] }}: {{ $criterion['score'] }}@if (! $loop->last), @endif
                                @endforeach
                                (instance score {{ $group[0]['weight'] }})
                            </li>
                        @endforeach
                    </ul>
                </div>
            </div>
        </div>
    @endif

    @if (! empty($report->softViolationsByCriterion))
        @php
            // Every criterion's message starts with the team name(s) it's
            // about (except equal_matches_played's, which is a global
            // statement) - sorting the flattened list alphabetically is
            // effectively sorting by team name without needing structured
            // per-team data from the scorer.
            $sortedMessages = collect($report->softViolationsByCriterion)->flatten()->sort(SORT_STRING | SORT_FLAG_CASE)->values();
        @endphp
        <div class="row mb-3">
            <div class="col">
                <div class="alert alert-warning">
                    <strong>Some preferences weren't fully met:</strong>
                    <ul class="mb-0">
                        @foreach ($sortedMessages as $message)
                            <li>{{ $message }}</li>
                        @endforeach
                    </ul>
                </div>
            </div>
        </div>
    @endif

    @php
        // Filter dropdown options are scoped to venues/teams that actually
        // appear in this candidate, not every venue/team on the association -
        // picking an option always yields at least one visible row.
        $filterVenueNames = collect();
        $filterTeamIds = collect();
        foreach ($candidate->rounds as $round) {
            foreach ($round->matches as $match) {
                $filterVenueNames->push($match->venueName);
                $filterTeamIds->push($match->homeTeamId);
                $filterTeamIds->push($match->awayTeamId);
            }
            foreach ($round->byeTeamIds as $byeTeamId) {
                $filterTeamIds->push($byeTeamId);
            }
        }
        $filterVenueNames = $filterVenueNames->unique()->sort(SORT_STRING | SORT_FLAG_CASE)->values();
        $filterTeamOptions = $filterTeamIds->unique()
            ->mapWithKeys(fn ($id) => [$id => $teamNames[$id] ?? "#$id"])
            ->sortBy(fn ($name) => preg_replace('/^the\s+/i', '', $name));
    @endphp

    <div class="row mb-3">
        <div class="col-md-4">
            <label for="filter-venue" class="form-label">Filter by Venue</label>
            <select id="filter-venue" class="form-control">
                <option value="">All Venues</option>
                @foreach ($filterVenueNames as $venueName)
                    <option value="{{ $venueName }}">{{ $venueName }}</option>
                @endforeach
            </select>
        </div>
        <div class="col-md-4">
            <label for="filter-team" class="form-label">Filter by Team</label>
            <select id="filter-team" class="form-control">
                <option value="">All Teams</option>
                @foreach ($filterTeamOptions as $teamId => $teamName)
                    <option value="{{ $teamId }}">{{ $teamName }}</option>
                @endforeach
            </select>
        </div>
    </div>

    <div class="mb-3">
        <div class="table-responsive">
            <table class="table table-striped" id="generated-matches-table">
                <thead>
                    <tr>
                        <th>Round</th>
                        <th>Venue</th>
                        <th>Home</th>
                        <th>Away</th>
                        <th>Rematch</th>
                    </tr>
                </thead>
                <tbody>
                    @php
                        // Meeting count is scoped to this candidate alone (rounds
                        // as previewed here, in order), not any previously
                        // committed matches for this schedule/series/association.
                        $pairMeetingCounts = [];
                    @endphp
                    @foreach ($candidate->rounds as $index => $round)
                        @php $roundLabel = 'Round '.($index + 1).' - '.$round->date->format('m-d-Y'); @endphp
                        @if (empty($round->matches))
                            <tr data-round="{{ $index }}" data-round-label="{{ $roundLabel }}" data-teams="{{ implode(',', $round->byeTeamIds) }}">
                                <th scope="row">{{ $roundLabel }}</th>
                                <td colspan="4">No matches (bye: {{ collect($round->byeTeamIds)->map(fn ($id) => $teamNames[$id] ?? "#$id")->implode(', ') }})</td>
                            </tr>
                        @else
                            @foreach ($round->matches as $matchIndex => $match)
                                @php
                                    $pairKey = min($match->homeTeamId, $match->awayTeamId).'-'.max($match->homeTeamId, $match->awayTeamId);
                                    $pairMeetingCounts[$pairKey] = ($pairMeetingCounts[$pairKey] ?? 0) + 1;
                                    $meetingNumber = $pairMeetingCounts[$pairKey];
                                    $homeViolations = $report->softTeamViolationsByRound[$index][$match->homeTeamId] ?? [];
                                    $awayViolations = $report->softTeamViolationsByRound[$index][$match->awayTeamId] ?? [];
                                @endphp
                                <tr data-round="{{ $index }}" data-round-label="{{ $roundLabel }}" data-venue="{{ $match->venueName }}" data-teams="{{ $match->homeTeamId }},{{ $match->awayTeamId }}">
                                    <th scope="row">{{ $matchIndex === 0 ? $roundLabel : '' }}</th>
                                    <td>{{ $match->venueName }}</td>
                                    <td data-team="{{ $match->homeTeamId }}">
                                        {{ $teamNames[$match->homeTeamId] ?? "#{$match->homeTeamId}" }}
                                        @if (! empty($homeViolations))
                                            <span class="badge bg-warning text-dark rounded-pill" title="{{ implode(', ', $homeViolations) }}">{{ count($homeViolations) }}</span>
                                        @endif
                                    </td>
                                    <td data-team="{{ $match->awayTeamId }}">
                                        {{ $teamNames[$match->awayTeamId] ?? "#{$match->awayTeamId}" }}
                                        @if (! empty($awayViolations))
                                            <span class="badge bg-warning text-dark rounded-pill" title="{{ implode(', ', $awayViolations) }}">{{ count($awayViolations) }}</span>
                                        @endif
                                    </td>
                                    <td>
                                        @if ($meetingNumber > 1)
                                            <span class="badge bg-primary rounded-pill">{{ $meetingNumber }}</span>
                                        @endif
                                    </td>
                                </tr>
                            @endforeach
                            @if (! empty($round->byeTeamIds))
                                <tr data-round="{{ $index }}" data-round-label="{{ $roundLabel }}" data-teams="{{ implode(',', $round->byeTeamIds) }}">
                                    <th scope="row"></th>
                                    <td colspan="4">Bye: {{ collect($round->byeTeamIds)->map(fn ($id) => $teamNames[$id] ?? "#$id")->implode(', ') }}</td>
                                </tr>
                            @endif
                        @endif
                    @endforeach
                </tbody>
            </table>
        </div>
    </div>

    <div class="form-actions">
        @unless ($report->degenerate)
            <div class="mb-3">
                <form method="POST" action="{{ route('schedule.generate-matches.accept', ['association' => $association, 'schedule' => $schedule]) }}">
                    @csrf
                    <input class="btn btn-primary" type="submit" value="Accept"/>
                </form>
            </div>
        @endunless
        <div class="mb-3">
            <form method="POST" action="{{ route('schedule.generate-matches.retry', ['association' => $association, 'schedule' => $schedule]) }}">
                @csrf
                <input class="btn btn-warning" type="submit" value="Discard &amp; Regenerate"/>
            </form>
        </div>
        <div class="mb-3">
            <a class="btn btn-secondary" href="{{ route('schedule.view', ['association' => $association, 'schedule' => $schedule]) }}">Cancel</a>
        </div>
    </div>
@endsection

@section('page-js')
<script>
    document.addEventListener('DOMContentLoaded', function () {
        var venueSelect = document.getElementById('filter-venue');
        var teamSelect = document.getElementById('filter-team');
        var rows = document.querySelectorAll('#generated-matches-table tbody tr');

        function applyFilters() {
            var venue = venueSelect.value;
            var team = teamSelect.value;
            var labeledRounds = {};

            rows.forEach(function (row) {
                var rowVenue = row.dataset.venue || '';
                var rowTeams = (row.dataset.teams || '').split(',').filter(Boolean);

                var venueMatch = !venue || rowVenue === venue;
                var teamMatch = !team || rowTeams.indexOf(team) !== -1;
                var visible = venueMatch && teamMatch;

                row.style.display = visible ? '' : 'none';

                if (visible) {
                    var round = row.dataset.round;
                    var labelCell = row.querySelector('th[scope="row"]');

                    labelCell.textContent = labeledRounds[round] ? '' : row.dataset.roundLabel;
                    labeledRounds[round] = true;
                }

                row.querySelectorAll('td[data-team]').forEach(function (cell) {
                    cell.classList.toggle('table-info', !!team && cell.dataset.team === team);
                });
            });
        }

        venueSelect.addEventListener('change', applyFilters);
        teamSelect.addEventListener('change', applyFilters);
    });
</script>
@stop
