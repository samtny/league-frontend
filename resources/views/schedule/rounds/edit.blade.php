@extends('layouts.admin')

@section('title', 'Edit Rounds')

@section('breadcrumb')
    {{ Breadcrumbs::render('rounds.edit', $schedule) }}
@endsection

@section('content')
    <div class="row">
        <h1 class="col">Edit Rounds</h1>
    </div>
    <div class="form">
        <form method="POST" action="{{ route('schedule.update', ['schedule' => $schedule]) }}">
            @csrf

            <input type="hidden" name="id" value="<?php echo $schedule->id; ?>">

            <input type="hidden" name="url" value="{{ URL::previous() }}">

            <?php if (!empty($schedule->rounds)): ?>

            <div class="row">
                <div class="table-responsive">
                    <table class="table">
                        <thead>
                            <tr>
                                <th>Venue</th>
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
                                    <?php $match = \App\PLMatch::where([
                                        'schedule_id' => $schedule->id,
                                        'round_id' => $round->id,
                                        'venue_id' => $venue->id,
                                        'sequence' => 1,
                                        ])->first();?>
                                    <select id="match_<?php echo $match->id; ?>__home_team_id" name="match_<?php echo $match->id; ?>__home_team_id">
                                        <option value="">- No team -</option>
                                        <?php foreach($schedule->association->teams->sortBy('name') as $team): ?>
                                        <option value="<?php echo $team->id; ?>"<?php if($match->home_team_id == $team->id) echo ' selected'; ?>><?php echo $team->name; ?></option>
                                        <?php endforeach; ?>
                                    </select>
                                    <select id="match_<?php echo $match->id; ?>__away_team_id" name="match_<?php echo $match->id; ?>__away_team_id">
                                        <option value="">- No team -</option>
                                        <?php foreach($schedule->association->teams->sortBy('name') as $team): ?>
                                        <option value="<?php echo $team->id; ?>"<?php if($match->away_team_id == $team->id) echo ' selected'; ?>><?php echo $team->name; ?></option>
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
