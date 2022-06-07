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
        <form method="POST" action="{{ route('round.update', ['schedule' => $schedule, 'id' => $round->id]) }}">
            @csrf

            <input type="hidden" name="id" value="<?php echo $schedule->id; ?>">

            <input type="hidden" name="url" value="{{ URL::previous() }}">

            <div class="form-group">
                <label for="name">Name</label>
                <input class="form-control @error('name') is-invalid @enderror" id="name" type="text" name="name" value="{{ old('name', $round->name) }}">
                @error('name')
                    <div class="alert alert-danger">{{ $message }}</div>
                @enderror
            </div>

            <div class="form-group">
                <label for="start_date">Start Date</label>
                <?php $start_date_value = $round->start_date != null ? date('Y-m-d', strtotime($round->start_date)) : null ?>
                <input id="start_date" class="form-control @error('start_date') is-invalid @enderror" type="date" name="start_date" value="{{ old('start_date', $start_date_value) }}">
                @error('start_date')
                    <div class="alert alert-danger">{{ $message }}</div>
                @enderror
            </div>

            <div class="form-group">
                <label for="end_date">End Date</label>
                <?php $end_date_value = $round->end_date != null ? date('Y-m-d', strtotime($round->end_date)) : null ?>
                <input id="end_date" class="form-control" type="date" name="end_date" value="{{ old('end_date', $end_date_value) }}">
                @error('end_date')
                    <div class="alert alert-danger">{{ $message }}</div>
                @enderror
            </div>

            <div class="form-group">
                <div class="form-check">
                    <input class="form-check-input" name="scores_closed" type="checkbox" value="scores_closed" id="scores_closed" <?php echo old('scores_closed', $round->scores_closed) ? ' checked' : ''; ?>>
                    <label class="form-check-label" for="scores_closed">
                        Close Score Submissions
                    </label>
                    <small class="form-text text-muted">When checked, score submission will not be available for this round.</small>
                </div>
            </div>

            <div class="form-group">
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
                                <?php $match = \App\PLMatch::where([
                                        'schedule_id' => $schedule->id,
                                        'round_id' => $round->id,
                                        'venue_id' => $venue->id,
                                        'sequence' => 1,
                                        ])->first();?>

                                <?php if (!empty($match)): ?>
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

            <div class="form-actions">
                <div class="form-group">
                    <input id="submit" class="btn btn-primary" type="submit" value="Update"/>
                </div>
                <div class="form-group">
                    <a class="btn btn-warning" type="submit" href="{{ route('round.delete-confirm', ['schedule' => $schedule, 'round' => $round]) }}">Delete Round</a>
                </div>
            </div>

        </form>
    </div>
@endsection
