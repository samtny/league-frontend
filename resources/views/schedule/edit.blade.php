@extends('layouts.bootstrap')

@section('title', 'Edit Schedule')

@section('content')
    <div class="title m-b-md">
        Edit Schedule
    </div>
    <div class="form">
        <form method="POST" action="/schedule/<?php echo $schedule->id; ?>/update">
            @csrf

            <input type="hidden" name="id" value="<?php echo $schedule->id; ?>">

            <input type="hidden" name="url" value="{{ URL::previous() }}">

            <div class="form-item">
                <label for="end_date">Start Date</label>
                <input id="start_date" type="date" name="start_date" value="<?php echo $schedule->start_date != null ? date('Y-m-d', strtotime($schedule->start_date)) : null ?>">
            </div>

            <div class="form-item">
                <label for="end_date">End Date</label>
                <input id="end_date" type="date" name="end_date" value="<?php echo $schedule->end_date != null ? date('Y-m-d', strtotime($schedule->end_date)) : null ?>">
            </div>

            <?php if (!empty($schedule->rounds)): ?>

                <table class="table">
                    <thead>
                        <th></th>
                        <?php foreach ($schedule->rounds as $index => $round): ?>
                        <th>
                            <?php echo ('<div class="round">' . $round->name . '</div>'); ?> — <?php echo date('Y-m-d', strtotime($round->start_date)); ?>
                        </th>
                        <?php endforeach; ?>
                    </thead>
                    <tbody>
                        <th scope="row">Venue X</th>
                        <?php foreach ($schedule->rounds as $index => $round): ?>
                        <td>
                            <?php echo ('<div class="round">' . $round->name . '</div>'); ?> — <?php echo date('Y-m-d', strtotime($round->start_date)); ?>
                        </td>
                        <?php endforeach; ?>
                    </tbody>
                </table>

            <?php else: ?>
                <div class="message">
                    No rounds.
                </div>
            <?php endif; ?>

            <div class="form-actions">
                <div class="form-item">
                    <input id="submit" type="submit" value="Update"/>
                </div>
            </div>

        </form>
    </div>
@endsection