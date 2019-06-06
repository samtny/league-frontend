@extends('layouts.bootstrap')

@section('title', 'Edit Schedule')

@section('content')
    <div class="title m-b-md">
        Edit Schedule
    </div>
    <div class="form">
        <form method="POST" action="/schedule/<?php echo $schedule->id; ?>/update">
            @csrf

            <?php echo $schedule->start_date; ?>

            <?php echo strtotime($schedule->start_date); ?>

            <?php echo date('Y-m-d', strtotime($schedule->start_date)); ?>

            <div class="form-item">
                <label for="end_date">Start Date</label>
                <input id="start_date" type="date" name="start_date" value="<?php echo $schedule->start_date != null ? date('Y-m-d', strtotime($schedule->start_date)) : null ?>">
            </div>

            <div class="form-item">
                <label for="end_date">End Date</label>
                <input id="end_date" type="date" name="end_date" value="<?php echo $schedule->end_date != null ? date('Y-m-d', strtotime($schedule->end_date)) : null ?>">
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
