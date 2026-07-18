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
        <form method="POST" action="{{ route('round.update', ['association' => $schedule->association, 'schedule' => $schedule, 'round' => $round]) }}">
            @csrf

            <input type="hidden" name="url" value="{{ URL::previous() }}">

            <div class="mb-3">
                <label for="name">Name</label>
                <input class="form-control @error('name') is-invalid @enderror" id="name" type="text" name="name" value="{{ old('name', $round->name) }}">
                @error('name')
                    <div class="alert alert-danger">{{ $message }}</div>
                @enderror
            </div>

            <div class="mb-3">
                <label for="start_date">Start Date</label>
                <?php $start_date_value = $round->start_date != null ? date('Y-m-d', strtotime($round->start_date)) : null ?>
                <input id="start_date" class="form-control @error('start_date') is-invalid @enderror" type="date" name="start_date" value="{{ old('start_date', $start_date_value) }}">
                @error('start_date')
                    <div class="alert alert-danger">{{ $message }}</div>
                @enderror
            </div>

            <div class="mb-3">
                <label for="end_date">End Date</label>
                <?php $end_date_value = $round->end_date != null ? date('Y-m-d', strtotime($round->end_date)) : null ?>
                <input id="end_date" class="form-control" type="date" name="end_date" value="{{ old('end_date', $end_date_value) }}">
                @error('end_date')
                    <div class="alert alert-danger">{{ $message }}</div>
                @enderror
            </div>

            <div class="mb-3">
                <div class="form-check">
                    <input class="form-check-input" name="scores_closed" type="checkbox" value="scores_closed" id="scores_closed" <?php echo old('scores_closed', $round->scores_closed) ? ' checked' : ''; ?>>
                    <label class="form-check-label" for="scores_closed">
                        Close Score Submissions
                    </label>
                    <small class="form-text text-muted">When checked, score submission will not be available for this round.</small>
                </div>
            </div>

            <div class="mb-3">
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
                            <?php $divisionTeams = $schedule->association->teams->filter(function ($team) use ($schedule) { return $team->division_id == $schedule->division_id; }); ?>
                            <?php $teamOptionsFor = function ($selectedTeamId) use ($divisionTeams) {
                                return $divisionTeams->filter(function ($team) use ($selectedTeamId) {
                                    return $team->active || $team->id == $selectedTeamId;
                                })->sortBy([['active', 'desc'], ['sortName', 'asc']]);
                            }; ?>
                            <?php foreach ($schedule->series->association->venues->sortBy('name') as $venue): ?>
                                <?php $match = \App\PLMatch::where([
                                        'schedule_id' => $schedule->id,
                                        'round_id' => $round->id,
                                        'venue_id' => $venue->id,
                                        'sequence' => 1,
                                        ])->first();?>

                                <?php if (!$venue->active && empty($match)): ?>
                                    <?php continue; ?>
                                <?php endif; ?>
                                <?php
                                    $homeInvalid = !empty($match) && !empty($match->home_team_id) && optional($match->homeTeam)->division_id != $schedule->division_id;
                                    $awayInvalid = !empty($match) && !empty($match->away_team_id) && optional($match->awayTeam)->division_id != $schedule->division_id;
                                ?>
                            <tr<?php if ($homeInvalid || $awayInvalid): ?> class="table-warning"<?php endif; ?>>
                                <th scope="row"<?php if (!$venue->active): ?> class="text-muted"<?php endif; ?>><?php echo $venue->name ?></th>

                                <?php if (!empty($match)): ?>
                                    <td<?php if ($homeInvalid): ?> title="This team's division does not match the schedule's division."<?php endif; ?>>
                                        <select id="match_<?php echo $match->id; ?>__home_team_id" name="match_<?php echo $match->id; ?>__home_team_id">
                                            <option value="">- No team -</option>
                                            <?php foreach($teamOptionsFor($match->home_team_id) as $team): ?>
                                            <option value="<?php echo $team->id; ?>"<?php if($match->home_team_id == $team->id) echo ' selected'; ?>><?php echo $team->name; ?><?php if (!$team->active) echo ' (inactive)'; ?></option>
                                            <?php endforeach; ?>
                                            <?php if ($homeInvalid): ?>
                                            <option value="<?php echo $match->home_team_id; ?>" selected><?php echo $match->homeTeam->name; ?> (wrong division)</option>
                                            <?php endif; ?>
                                        </select>
                                    </td>
                                    <td<?php if ($awayInvalid): ?> title="This team's division does not match the schedule's division."<?php endif; ?>>
                                        <select id="match_<?php echo $match->id; ?>__away_team_id" name="match_<?php echo $match->id; ?>__away_team_id">
                                            <option value="">- No team -</option>
                                            <?php foreach($teamOptionsFor($match->away_team_id) as $team): ?>
                                            <option value="<?php echo $team->id; ?>"<?php if($match->away_team_id == $team->id) echo ' selected'; ?>><?php echo $team->name; ?><?php if (!$team->active) echo ' (inactive)'; ?></option>
                                            <?php endforeach; ?>
                                            <?php if ($awayInvalid): ?>
                                            <option value="<?php echo $match->away_team_id; ?>" selected><?php echo $match->awayTeam->name; ?> (wrong division)</option>
                                            <?php endif; ?>
                                        </select>
                                    </td>
                                <?php else: ?>
                                    <td>
                                        No Match
                                    </td>
                                    <td>
                                        No Match
                                    </td>
                                <?php endif; ?>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>

            <div class="mb-3">
                <div class="form-check">
                    <input class="form-check-input" name="off_week" type="checkbox" value="off_week" id="off_week" <?php echo old('off_week', $round->off_week) ? ' checked' : ''; ?>>
                    <label class="form-check-label" for="off_week">
                        Off Week
                    </label>
                    <small class="form-text text-muted">When checked, this round is a schedule-wide break (e.g. a holiday) with no games scheduled.</small>
                </div>
            </div>

            <div class="mb-3">
                <div class="form-check">
                    <input class="form-check-input" name="playoffs_week" type="checkbox" value="playoffs_week" id="playoffs_week" <?php echo old('playoffs_week', $round->playoffs_week) ? ' checked' : ''; ?>>
                    <label class="form-check-label" for="playoffs_week">
                        Playoffs Week
                    </label>
                    <small class="form-text text-muted">When checked, this round is part of the playoff/knockout stage (quarterfinals, semifinals, or finals) rather than the regular round-robin season.</small>
                </div>
            </div>
            @error('off_week')
                <div class="alert alert-danger">{{ $message }}</div>
            @enderror

            <div class="form-actions">
                <div class="mb-3">
                    <input id="submit" class="btn btn-primary" type="submit" value="Update"/>
                </div>
                <div class="mb-3">
                    <a class="btn btn-warning" type="submit" href="{{ route('round.delete-confirm', ['association' => $schedule->association, 'schedule' => $schedule, 'round' => $round]) }}">Delete Round</a>
                </div>
            </div>

        </form>
    </div>
@endsection
