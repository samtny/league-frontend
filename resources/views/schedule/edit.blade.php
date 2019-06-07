@extends('layouts.bootstrap')

@section('title', 'Edit Schedule')

@section('content')
    <div class="row">
        <div class="title m-b-md h1">
            Edit Schedule
        </div>
    </div>
    <div class="form">
        <form method="POST" action="/schedule/<?php echo $schedule->id; ?>/update">
            @csrf

            <input type="hidden" name="id" value="<?php echo $schedule->id; ?>">

            <input type="hidden" name="url" value="{{ URL::previous() }}">

            <div class="row">
                <div class="col-md-3">
                    <label for="start_date">Start Date</label>
                    <input id="start_date" class="form-control" type="date" name="start_date" value="<?php echo $schedule->start_date != null ? date('Y-m-d', strtotime($schedule->start_date)) : null ?>">
                </div>
            </div>

            <div class="row">
                <div class="col-md-3">
                    <label for="end_date">End Date</label>
                    <input id="end_date" class="form-control" type="date" name="end_date" value="<?php echo $schedule->end_date != null ? date('Y-m-d', strtotime($schedule->end_date)) : null ?>">
                </div>
            </div>

            <?php if (!empty($schedule->rounds)): ?>

            <div class="row">
                <div class="table-responsive">
                    <table class="table">
                        <thead>
                            <tr>
                                <th></th>
                                <?php foreach ($schedule->rounds as $index => $round): ?>
                                <th>
                                    <?php echo ('<div class="round">' . $round->name . '</div>'); ?> — <?php echo date('Y-m-d', strtotime($round->start_date)); ?>
                                </th>
                                <?php endforeach; ?>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($schedule->series->association->venues->sortBy('name') as $venue): ?>
                            <tr>
                                <th scope="row"><?php echo $venue->name ?></th>
                                <?php foreach ($schedule->rounds as $index => $round): ?>
                                <td>
                                    <select id="team_id" name="team_id">
                                        <option value="">- No team -</option>
                                        <?php foreach($schedule->association->teams->sortBy('name') as $team): ?>
                                        <option value="<?php echo $team->id; ?>"><?php echo $team->name; ?></option>
                                        <?php endforeach; ?>
                                    </select>
                                    <select id="team_id" name="team_id">
                                        <option value="">- No team -</option>
                                        <?php foreach($schedule->association->teams->sortBy('name') as $team): ?>
                                        <option value="<?php echo $team->id; ?>"><?php echo $team->name; ?></option>
                                        <?php endforeach; ?>
                                    </select>
                                </td>
                                <?php endforeach; ?>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>

            <?php else: ?>
                <div class="message">
                    No rounds.
                </div>
            <?php endif; ?>

            <div class="form-actions">
                <div class="form-group">
                    <input id="submit" class="form-control" type="submit" value="Update"/>
                </div>
            </div>

        </form>
    </div>
@endsection
