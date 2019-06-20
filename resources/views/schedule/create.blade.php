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
        <form method="POST" action="{{ route('schedule.create', ['series' => $series ]) }}">
            @csrf

            <input type="hidden" name="association_id" value="<?php echo $association_id; ?>" />

            <input type="hidden" name="series_id" value="{{ $series->id }}">

            <input type="hidden" name="url" value="{{ URL::previous() }}">

            <div class="form-group">
                <label for="name">Name</label>
                <input id="name" type="text" class="form-control @error('name') is-invalid @enderror" name="name" value="{{ old('name') }}">
                @error('name')
                    <div class="alert alert-danger">{{ $message }}</div>
                @enderror
                <small class="form-text text-muted">Enter a name for this Schedule, like <em>"A Division"</em></small>
            </div>

            <div class="form-group">
                <label for="division_id">Division</label>
                <select class="form-control" id="division_id" name="division_id">
                    <option value="">- No division -</option>
                    <?php foreach($available_divisions as $item): ?>
                        <option value="<?php echo $item->id; ?>"><?php echo $item->name; ?></option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div class="form-group">
                <label for="start_date">Start Date</label>
                <input class="form-control" id="start_date" type="date" name="start_date">
            </div>

            <div class="form-group">
                <label for="end_date">End Date</label>
                <input class="form-control" id="end_date" type="date" name="end_date">
            </div>

            <div class="form-group">
                <legend>Match Weekday</legend>
                <fieldset>
                    <label for="weekday_sunday">Sunday</label>
                    <input type="radio" id="weekday_sunday" name="weekday" value="sun">

                    <label for="weekday_monday">Monday</label>
                    <input type="radio" id="weekday_monday" name="weekday" value="mon">

                    <label for="weekday_tuesday">Tuesday</label>
                    <input type="radio" id="weekday_tuesday" name="weekday" value="tue">

                    <label for="weekday_wednesday">Wednesday</label>
                    <input type="radio" id="weekday_wednesday" name="weekday" value="wed">

                    <label for="weekday_thursday">Thursday</label>
                    <input type="radio" id="weekday_thursday" name="weekday" value="thu">

                    <label for="weekday_friday">Friday</label>
                    <input type="radio" id="weekday_friday" name="weekday" value="fri">

                    <label for="weekday_saturday">Saturday</label>
                    <input type="radio" id="weekday_saturday" name="weekday" value="sat">
                </fieldset>
            </div>

            <div class="form-actions">
                <div class="form-group">
                    <input class="btn btn-primary" id="submit" type="submit" value="Create"/>
                </div>
            </div>

        </form>
    </div>
@endsection
