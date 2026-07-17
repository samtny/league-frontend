@extends('layouts.admin')

@section('title', 'Create Round')

@section('breadcrumb')
    {{ Breadcrumbs::render('round.create', $schedule) }}
@endsection

@section('content')
    <div class="row">
        <h1 class="col">Create Round</h1>
    </div>
    <div class="form">
        <form method="POST" action="{{ route('round.store', ['association' => $schedule->association, 'schedule' => $schedule]) }}">
            @csrf

            <input type="hidden" name="url" value="{{ URL::previous() }}">

            <div class="mb-3">
                <label for="name">Name</label>
                <input class="form-control @error('name') is-invalid @enderror" id="name" type="text" name="name" value="{{ old('name') }}" placeholder="New Round">
                @error('name')
                    <div class="alert alert-danger">{{ $message }}</div>
                @enderror
            </div>

            <div class="mb-3">
                <label for="start_date">Start Date</label>
                <input id="start_date" class="form-control @error('start_date') is-invalid @enderror" type="date" name="start_date">
                @error('start_date')
                    <div class="alert alert-danger">{{ $message }}</div>
                @enderror
            </div>

            <div class="mb-3">
                <label for="end_date">End Date</label>
                <input id="end_date" class="form-control" type="date" name="end_date">
            </div>

            <div class="mb-3">
                <div class="form-check">
                    <input class="form-check-input" name="off_week" type="checkbox" value="off_week" id="off_week" {{ old('off_week') ? ' checked' : '' }}>
                    <label class="form-check-label" for="off_week">
                        Off Week
                    </label>
                    <small class="form-text text-muted">When checked, this round is a schedule-wide break (e.g. a holiday) with no games scheduled.</small>
                </div>
            </div>

            <div class="mb-3">
                <div class="form-check">
                    <input class="form-check-input" name="playoffs_week" type="checkbox" value="playoffs_week" id="playoffs_week" {{ old('playoffs_week') ? ' checked' : '' }}>
                    <label class="form-check-label" for="playoffs_week">
                        Playoffs Week
                    </label>
                    <small class="form-text text-muted">When checked, this round is part of the playoff/knockout stage (quarterfinals, semifinals, or finals) rather than the regular round-robin season.</small>
                </div>
            </div>
            @error('off_week')
                <div class="alert alert-danger">{{ $message }}</div>
            @enderror

            <div class="form-actions">
                <div class="mb-3">
                    <input id="submit" class="btn btn-primary" type="submit" value="Create"/>
                </div>
            </div>

        </form>
    </div>
@endsection
