@extends('layouts.admin')

@section('title', 'Generate Rounds - Review')

@section('breadcrumb')
    {{ Breadcrumbs::render('schedule.generate-rounds.review', $schedule) }}
@endsection

@section('content')
    <div class="row">
        <h1 class="col">Review Automatically Generated Rounds</h1>
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
                    All required constraints were satisfied (no repeated back-to-back opponents, and only active teams/venues were used).
                </div>
            </div>
        </div>
    @endif

    @if (! empty($report->softViolationsByCriterion))
        <div class="row mb-3">
            <div class="col">
                <div class="alert alert-warning">
                    <strong>Some preferences weren't fully met:</strong>
                    <ul class="mb-0">
                        @foreach ($report->softViolationsByCriterion as $messages)
                            @foreach ($messages as $message)
                                <li>{{ $message }}</li>
                            @endforeach
                        @endforeach
                    </ul>
                </div>
            </div>
        </div>
    @endif

    <div class="mb-3">
        <div class="table-responsive">
            <table class="table">
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
                <form method="POST" action="{{ route('schedule.generate-rounds.accept', ['association' => $association, 'schedule' => $schedule]) }}">
                    @csrf
                    <input class="btn btn-primary" type="submit" value="Accept"/>
                </form>
            </div>
        @endunless
        <div class="mb-3">
            <form method="POST" action="{{ route('schedule.generate-rounds.retry', ['association' => $association, 'schedule' => $schedule]) }}">
                @csrf
                <input class="btn btn-warning" type="submit" value="Discard &amp; Regenerate"/>
            </form>
        </div>
        <div class="mb-3">
            <a class="btn btn-secondary" href="{{ route('schedule.view', ['association' => $association, 'schedule' => $schedule]) }}">Cancel</a>
        </div>
    </div>
@endsection
