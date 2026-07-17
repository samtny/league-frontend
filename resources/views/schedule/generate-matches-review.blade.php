@extends('layouts.admin')

@section('title', 'Review Automatically Generated Matches')

@section('breadcrumb')
    {{ Breadcrumbs::render('schedule.generate-matches.review', $schedule) }}
@endsection

@section('content')
    <div class="row">
        <h1 class="col">Review Automatically Generated Matches</h1>
    </div>

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

    <div class="mb-3">
        <div class="table-responsive">
            <table class="table table-striped">
                <thead>
                    <tr>
                        <th>Round</th>
                        <th>Venue</th>
                        <th>Home</th>
                        <th>Away</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($candidate->rounds as $index => $round)
                        @php $roundLabel = 'Round '.($index + 1).' - '.$round->date->format('m-d-Y'); @endphp
                        @if (empty($round->matches))
                            <tr>
                                <th scope="row">{{ $roundLabel }}</th>
                                <td colspan="3">No matches (bye: {{ collect($round->byeTeamIds)->map(fn ($id) => $teamNames[$id] ?? "#$id")->implode(', ') }})</td>
                            </tr>
                        @else
                            @foreach ($round->matches as $matchIndex => $match)
                                <tr>
                                    <th scope="row">{{ $matchIndex === 0 ? $roundLabel : '' }}</th>
                                    <td>{{ $match->venueName }}</td>
                                    <td>{{ $teamNames[$match->homeTeamId] ?? "#{$match->homeTeamId}" }}</td>
                                    <td>{{ $teamNames[$match->awayTeamId] ?? "#{$match->awayTeamId}" }}</td>
                                </tr>
                            @endforeach
                            @if (! empty($round->byeTeamIds))
                                <tr>
                                    <th scope="row"></th>
                                    <td colspan="3">Bye: {{ collect($round->byeTeamIds)->map(fn ($id) => $teamNames[$id] ?? "#$id")->implode(', ') }}</td>
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
