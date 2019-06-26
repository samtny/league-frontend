@extends('layouts.admin')

@section('title', __('Score Submissions'))

@section('breadcrumb')
    {{ Breadcrumbs::render('result_submissions.approve', $association) }}
@endsection

@section('content')
    <div class="row">
        <div class="col-md-12">
            <div class="title m-b-md h1">
                {{ __('Score Submissions') }}
            </div>
        </div>
    </div>
    <?php foreach ($association->series as $seriesItem): ?>
        <?php foreach ($seriesItem->schedules as $schedule): ?>
            <?php if (!empty($schedule->resultSubmissions->where('approved', FALSE))): ?>
            <div class="result-submissions row mb-3">
                <div class="col-md-12">
                    <h2><?php echo $seriesItem->name; ?></h2>
                    <h3><?php echo $schedule->name . '[Schedule Name Here]'; ?></h3>
                    <?php foreach ($schedule->resultSubmissions->where('approved', FALSE) as $submission): ?>
                    <form class="form-inline" method="POST" action="{{ route('result_submission.update', ['id' => $submission->id]) }}">
                        @csrf
                        <input type="hidden" name="result_submission_id" value="{{ $submission->id }}">
                        <div class="form-group mr-2">
                        <label class="mr-sm-2" for="submission_{{ $submission->id }}_home_team_score">{{ !empty($submission->match->homeTeam) ? $submission->match->homeTeam->name : '[no team]' }}{{ $submission->match->home_team_id == $submission->win_team_id ? '*' : '' }}</label>
                            <input type="text" class="form-control" id="submission_{{ $submission->id }}_home_team_score" name="home_team_score" value="{{ $submission->home_team_score }}" size="4">
                        </div>
                        <div class="form-group mr-2">
                            <label class="mr-sm-2" for="submission_{{ $submission->id }}_away_team_score">{{ !empty($submission->match->awayTeam) ? $submission->match->awayTeam->name : '[no team]' }}{{ $submission->match->away_team_id == $submission->win_team_id ? '*' : '' }}</label>
                            <input type="text" class="form-control" id="submission_{{ $submission->id }}_away_team_score" name="away_team_score" value="{{ $submission->away_team_score }}" size="4">
                        </div>
                        <button id="submission_{{ $submission->id }}_submit" type="submit" class="btn btn-primary mr-2 mt-3 mt-sm-0">Accept</button>
                        <input type="hidden" id="submission_{{ $submission->id }}_delete" name="delete" value="nodelete">
                        <button type="submit" class="btn btn-danger mt-3 mt-sm-0" onclick="event.preventDefault(); document.getElementById('submission_{{ $submission->id }}_delete').value = 'delete'; document.getElementById('submission_{{ $submission->id }}_submit').click();">Delete</button>
                        <input type="hidden" name="url" value="{{ route('result_submissions.list', ['association' => $association]) }}">
                    </form>
                    <?php endforeach; ?>
                </div>
            </div>
            <?php endif; ?>
        <?php endforeach; ?>
    <?php endforeach; ?>
@endsection
