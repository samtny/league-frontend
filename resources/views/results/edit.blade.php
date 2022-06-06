@extends('layouts.admin')

@section('title', 'Edit Results')

@section('breadcrumb')
    {{ Breadcrumbs::render('results.edit', $schedule) }}
@endsection

@section('content')
    <div class="row">
        <h1 class="col">Edit Results</h1>
    </div>
    <div class="form">
        <form method="POST" action="{{ route('results.update', ['schedule' => $schedule]) }}">
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

                                    <?php if (!empty($match->home_team_id)): ?>
                                        <div class="form-group form-inline">
                                            <input type="hidden" name="match_<?php echo $match->id; ?>__home_team_id" value="<?php echo $match->home_team_id; ?>" readonly>
                                            <label for="match_<?php echo $match->id; ?>__home_team_score"><?php echo $schedule->association->teams->find($match->home_team_id)->name; ?></label>
                                            <input type="text" name="match_<?php echo $match->id; ?>__home_team_score" value="<?php echo(!empty($match->result) ? $match->result->home_team_score : null); ?>">
                                        </div>
                                    <?php endif; ?>

                                    <?php if (!empty($match->away_team_id)): ?>
                                        <div class="form-group form-inline">
                                            <input type="hidden" name="match_<?php echo $match->id; ?>__away_team_id" value="<?php echo $match->away_team_id; ?>" readonly>
                                            <label for="match_<?php echo $match->id; ?>__away_team_score"><?php echo $schedule->association->teams->find($match->away_team_id)->name; ?></label>
                                            <input type="text" name="match_<?php echo $match->id; ?>__away_team_score" value="<?php echo(!empty($match->result) ? $match->result->away_team_score : null); ?>">
                                        </div>
                                    <?php endif; ?>

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
