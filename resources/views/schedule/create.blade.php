@extends('layouts.bootstrap')

@section('title', 'Create Schedule')

@section('content')
    <div class="title m-b-md">
        Create Schedule
    </div>
    <div class="form">
        <form method="POST" action="/series/<?php echo $series->id; ?>/schedule/create">
            @csrf

            <input type="hidden" name="association_id" value="<?php echo $association_id; ?>" />

            <div class="form-item">
                <label for="series_id">Series</label>
                <select id="series_id" name="series_id">
                    <option value="">- No series -</option>
                    <?php foreach($available_series as $item): ?>
                        <option value="<?php echo $item->id; ?>"<?php if($item->id === $series->id) echo ' selected'; ?>><?php echo $item->name; ?></option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div class="form-item">
                <label for="division_id">Division</label>
                <select id="division_id" name="division_id">
                    <option value="">- No division -</option>
                    <?php foreach($available_divisions as $item): ?>
                        <option value="<?php echo $item->id; ?>"><?php echo $item->name; ?></option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div class="form-item">
                <label for="start_date">Start Date</label>
                <input id="start_date" type="date" name="start_date">
            </div>

            <div class="form-item">
                <label for="end_date">End Date</label>
                <input id="end_date" type="date" name="end_date">
            </div>

            <div class="form-item">
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
                <div class="form-item">
                    <input id="submit" type="submit" value="Update"/>
                </div>
            </div>

        </form>
    </div>
@endsection
