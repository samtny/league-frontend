@extends('layouts.bootstrap')

@section('title', 'Edit Schedule')

@section('content')
    <div class="title m-b-md">
        Edit Schedule
    </div>
    <div class="form">
        <form method="POST" action="/schedule/<?php echo $schedule->id; ?>/update">
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

            <?php if (!empty($schedule->rounds)): ?>
                <?php foreach ($schedule->rounds as $index => $round): ?>
                    <a href="/round/<?php echo($round->id); ?>">
                        <?php echo ('<div class="round">' . $round->name . '</div>'); ?> — <?php echo date('Y-m-d', strtotime($round->start_date)); ?>
                    </a>
                    <a href="/round/<?php echo($round->id); ?>/edit">
                        Edit
                    </a>
                <?php endforeach; ?>
            <?php else: ?>
                <div class="message">
                    No rounds.
                </div>
            <?php endif; ?>

            <div class="form-actions">
                <div class="form-item">
                    <input id="submit" type="submit" value="Create"/>
                </div>
            </div>

        </form>
    </div>
@endsection
