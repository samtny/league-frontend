@extends('layouts.admin')

@section('title', 'Generate Matches')

@section('breadcrumb')
    {{ Breadcrumbs::render('schedule.generate-matches', $schedule) }}
@endsection

@section('content')
    <div class="row">
        <h1 class="col">Generate Matches</h1>
    </div>
    <div class="form">
        <form method="POST" action="{{ route('schedule.generate-matches.store', ['association' => $association, 'schedule' => $schedule]) }}">
            @csrf

            <div class="mb-3">
                <legend>Assignment Method</legend>
                <fieldset>
                    <div class="form-check">
                        <input class="form-check-input" type="radio" id="generate_clear" name="generate" value="clear" <?php echo old('generate', 'clear') === 'clear' ? ' checked' : ''; ?>>
                        <label class="form-check-label" for="generate_clear">Clear</label>
                        <small class="form-text text-muted d-block">Clears any Home/Away teams already assigned on this schedule's Matches, leaving the Rounds/Matches themselves in place.</small>
                    </div>

                    <div class="form-check">
                        <input class="form-check-input" type="radio" id="generate_random" name="generate" value="random" <?php echo old('generate') === 'random' ? ' checked' : ''; ?>>
                        <label class="form-check-label" for="generate_random">Automatic</label>
                        <small class="form-text text-muted d-block">Clears any Home/Away teams already assigned, then assigns new ones onto this schedule's existing Rounds/Matches so every active team plays an equal number of matches, then shows a review screen before anything is saved.</small>
                    </div>
                </fieldset>
                @error('generate')
                    <div class="alert alert-danger">{{ $message }}</div>
                @enderror
            </div>

            <div class="mb-3" id="strategy-options">
                <legend>Strategy</legend>
                <div class="alert alert-info">{{ $recommendation->reason }}</div>
                <fieldset>
                    @foreach ($strategies as $strategy)
                        @php
                            $checked = old('strategy', $recommendation->strategy->value) === $strategy->value;
                        @endphp
                        <div class="form-check">
                            <input class="form-check-input" type="radio" id="strategy_{{ $strategy->value }}" name="strategy" value="{{ $strategy->value }}" {{ $checked ? 'checked' : '' }}>
                            <label class="form-check-label" for="strategy_{{ $strategy->value }}">
                                {{ $strategy->label() }}
                                @if ($strategy === $recommendation->strategy)
                                    <span class="badge bg-secondary">Recommended</span>
                                @endif
                            </label>
                            <small class="form-text text-muted d-block">{{ $strategy->helpText() }}</small>
                        </div>
                    @endforeach
                </fieldset>
                @error('strategy')
                    <div class="alert alert-danger">{{ $message }}</div>
                @enderror
            </div>

            <div class="form-actions">
                <div class="mb-3">
                    <input class="btn btn-primary" type="submit" value="Apply"/>
                </div>
                <div class="mb-3">
                    <a class="btn btn-secondary" href="{{ route('schedule.view', ['association' => $association, 'schedule' => $schedule]) }}">Cancel</a>
                </div>
            </div>
        </form>
    </div>
@endsection
