@extends('layouts.admin')

@section('title', 'Create Schedule')

@section('breadcrumb')
    {{ Breadcrumbs::render('schedule.create', $series) }}
@endsection

@section('content')
    <h1>
        Create Schedule
    </h1>
    <div class="form">
        <form method="POST" action="{{ route('schedule.create', ['association' => $association, 'series' => $series ]) }}">
            @csrf

            <input type="hidden" name="url" value="{{ URL::previous() }}">

            <div class="mb-3">
                <label for="name">Name</label>
                <input id="name" type="text" class="form-control @error('name') is-invalid @enderror" name="name" value="{{ old('name') }}" required>
                @error('name')
                    <div class="alert alert-danger">{{ $message }}</div>
                @enderror
                <small class="form-text text-muted">Enter a name for this Schedule, like <em>"A Division"</em></small>
            </div>

            <div class="mb-3">
                <label for="division_id">Division</label>
                <select class="form-control" id="division_id" name="division_id">
                    <option value="">- No division -</option>
                    <?php foreach($available_divisions as $item): ?>
                        <option value="<?php echo $item->id; ?>"><?php echo $item->name; ?></option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div class="mb-3">
                <label for="start_date">Start Date</label>
                <input class="form-control" id="start_date" type="date" name="start_date">
            </div>

            <div class="mb-3">
                <label for="end_date">End Date</label>
                <input class="form-control" id="end_date" type="date" name="end_date">
            </div>

            <div class="mb-3">
                <legend>Match Weekday</legend>
                <fieldset>
                    <div class="form-check">
                        <input class="form-check-input" type="radio" id="weekday_sunday" name="weekday" value="sun">
                        <label class="form-check-label" for="weekday_sunday">Sunday</label>
                    </div>

                    <div class="form-check">
                        <input class="form-check-input" type="radio" id="weekday_monday" name="weekday" value="mon">
                        <label class="form-check-label" for="weekday_monday">Monday</label>
                    </div>

                    <div class="form-check">
                        <input class="form-check-input" type="radio" id="weekday_tuesday" name="weekday" value="tue">
                        <label class="form-check-label" for="weekday_tuesday">Tuesday</label>
                    </div>

                    <div class="form-check">
                        <input class="form-check-input" type="radio" id="weekday_wednesday" name="weekday" value="wed">
                        <label class="form-check-label" for="weekday_wednesday">Wednesday</label>
                    </div>

                    <div class="form-check">
                        <input class="form-check-input" type="radio" id="weekday_thursday" name="weekday" value="thu">
                        <label class="form-check-label" for="weekday_thursday">Thursday</label>
                    </div>

                    <div class="form-check">
                        <input class="form-check-input" type="radio" id="weekday_friday" name="weekday" value="fri">
                        <label class="form-check-label" for="weekday_friday">Friday</label>
                    </div>

                    <div class="form-check">
                        <input class="form-check-input" type="radio" id="weekday_saturday" name="weekday" value="sat">
                        <label class="form-check-label" for="weekday_saturday">Saturday</label>
                    </div>
                </fieldset>
            </div>

            <div class="mb-3">
                <label for="generate">Generate Schedule</label>
                <select class="form-control" id="generate" name="generate">
                    <option value="" <?php echo old('generate', '') === '' ? ' selected' : ''; ?>>-- No Selection --</option>
                    <option value="manual" <?php echo old('generate') === 'manual' ? ' selected' : ''; ?>>Manual Assignment (Empty Rounds)</option>
                    <option value="random" <?php echo old('generate') === 'random' ? ' selected' : ''; ?>>Automatic Random Assignment</option>
                </select>
                <small class="form-text text-muted">This will generate a full schedule of rounds based on the selected assignment method.</small>
            </div>

            <div class="form-actions">
                <div class="mb-3">
                    <input class="btn btn-primary" id="submit" type="submit" value="Create"/>
                </div>
            </div>

        </form>
    </div>
@endsection
