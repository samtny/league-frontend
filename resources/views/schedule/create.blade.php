@extends('layouts.app')

@section('title', 'Create Schedule')

@section('content')
    <div class="title m-b-md">
        Create Schedule
    </div>
    <div class="form">
        <form method="POST" action="/series/create">
            @csrf

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
                        <option value="<?php echo $item->id; ?>"<?php if($item->id === $division->id) echo ' selected'; ?>><?php echo $item->name; ?></option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div class="form-item">
                <legend>Weekday</legend>
                <fieldset>
                    <label for="weekday_sunday">Sunday</label>
                    <input type="checkbox" name="weekday_sunday" value="sun">

                    <label for="weekday_monday">Monday</label>
                    <input type="checkbox" name="weekday_monday" value="sun">

                    <label for="weekday_tuesday">Tuesday</label>
                    <input type="checkbox" name="weekday_tuesday" value="sun">

                    <label for="weekday_wednesday">Wednesday</label>
                    <input type="checkbox" name="weekday_wednesday" value="sun">

                    <label for="weekday_thursday">Thursday</label>
                    <input type="checkbox" name="weekday_thursday" value="sun">

                    <label for="weekday_friday">Friday</label>
                    <input type="checkbox" name="weekday_friday" value="sun">

                    <label for="weekday_saturday">Saturday</label>
                    <input type="checkbox" name="weekday_saturday" value="sun">
                </fieldset>
            </div>

            <div class="form-actions">
                <div class="form-item">
                    <input id="submit" type="submit" value="Submit"/>
                </div>
            </div>

        </form>
    </div>
@endsection
