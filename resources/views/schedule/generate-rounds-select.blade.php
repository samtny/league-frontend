@extends('layouts.admin')

@section('title', 'Generate Rounds')

@section('breadcrumb')
    {{ Breadcrumbs::render('schedule.generate-rounds', $schedule) }}
@endsection

@section('content')
    <div class="row">
        <h1 class="col">Generate Rounds</h1>
    </div>
    <div class="form">
        <form method="POST" action="{{ route('schedule.generate-rounds.store', ['association' => $association, 'schedule' => $schedule]) }}">
            @csrf

            <div class="mb-3">
                <legend>Assignment Method</legend>
                <fieldset>
                    <div class="form-check">
                        <input class="form-check-input" type="radio" id="generate_manual" name="generate" value="manual" <?php echo old('generate', 'manual') === 'manual' ? ' checked' : ''; ?>>
                        <label class="form-check-label" for="generate_manual">Manual Assignment (Empty Rounds)</label>
                    </div>

                    <div class="form-check">
                        <input class="form-check-input" type="radio" id="generate_random" name="generate" value="random" <?php echo old('generate') === 'random' ? ' checked' : ''; ?>>
                        <label class="form-check-label" for="generate_random">Automatic Random Assignment</label>
                    </div>
                </fieldset>
                @error('generate')
                    <div class="alert alert-danger">{{ $message }}</div>
                @enderror
            </div>

            <div class="form-actions">
                <div class="mb-3">
                    <input class="btn btn-primary" type="submit" value="Generate Rounds"/>
                </div>
                <div class="mb-3">
                    <a class="btn btn-secondary" href="{{ route('schedule.view', ['association' => $association, 'schedule' => $schedule]) }}">Cancel</a>
                </div>
            </div>
        </form>
    </div>
@endsection
