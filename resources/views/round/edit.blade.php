@extends('layouts.admin')

@section('title', 'Edit Round')

@section('breadcrumb')
    {{ Breadcrumbs::render('round.edit', $schedule, $round) }}
@endsection

@section('content')
    <div class="row">
        <h1 class="col">Edit Round</h1>
    </div>
    <div class="form">
        <form method="POST" action="{{ route('round.update', ['schedule' => $schedule, 'round' => $round]) }}">
            @csrf

            <input type="hidden" name="id" value="<?php echo $schedule->id; ?>">

            <input type="hidden" name="url" value="{{ URL::previous() }}">

            <div class="row">
                <div class="table-responsive">
                    <table class="table">
                        <thead>
                            <tr>
                                <th>Venue</th>
                                <th>Home</th>
                                <th>Away</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($schedule->series->association->venues->sortBy('name') as $venue): ?>
                            <tr>
                                <th scope="row"><?php echo $venue->name ?></th>
                                <?php $match = \App\Match::where([
                                        'schedule_id' => $schedule->id,
                                        'round_id' => $round->id,
                                        'venue_id' => $venue->id,
                                        'sequence' => 1,
                                        ])->first();?>
                                <td>
                                    <select id="match_<?php echo $match->id; ?>__home_team_id" name="match_<?php echo $match->id; ?>__home_team_id">
                                        <option value="">- No team -</option>
                                        <?php foreach($schedule->association->teams->sortBy('name') as $team): ?>
                                        <option value="<?php echo $team->id; ?>"<?php if($match->home_team_id == $team->id) echo ' selected'; ?>><?php echo $team->name; ?></option>
                                        <?php endforeach; ?>
                                    </select>
                                </td>
                                <td>
                                    <select id="match_<?php echo $match->id; ?>__away_team_id" name="match_<?php echo $match->id; ?>__away_team_id">
                                        <option value="">- No team -</option>
                                        <?php foreach($schedule->association->teams->sortBy('name') as $team): ?>
                                        <option value="<?php echo $team->id; ?>"<?php if($match->away_team_id == $team->id) echo ' selected'; ?>><?php echo $team->name; ?></option>
                                        <?php endforeach; ?>
                                    </select>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>

            <div class="form-actions">
                <div class="form-group">
                    <input id="submit" class="form-control" type="submit" value="Update"/>
                </div>
            </div>

        </form>
    </div>
@endsection
