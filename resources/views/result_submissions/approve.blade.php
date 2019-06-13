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
            <div class="result-submissions row">
                <div class="col-md-12">
                    <h2><?php echo $seriesItem->name; ?></h2>
                    <h3><?php echo $schedule->name . '[Schedule Name Here]'; ?></h3>
                    <?php foreach ($schedule->resultSubmissions->where('approved', FALSE) as $submission): ?>
                    <form class="form-inline" method="POST" action="{{ route('result_submission.update', ['id' => $submission->id]) }}">
                        @csrf
                        <input type="hidden" name="result_submission_id" value="{{ $submission->id }}">
                        <div class="form-group">
                            <label for="submission_{{ $submission->id }}_home_team_score">{{ $submission->match->homeTeam->name }}</label>
                            <input type="text" id="submission_{{ $submission->id }}_home_team_score" name="home_team_score" value="{{ $submission->home_team_score }}" size="5">
                        </div>
                        <div class="form-group">
                            <label for="submission_{{ $submission->id }}_away_team_score">{{ $submission->match->awayTeam->name }}</label>
                            <input type="text" id="submission_{{ $submission->id }}_away_team_score" name="away_team_score" value="{{ $submission->away_team_score }}" size="5">
                        </div>
                        <button id="submission_{{ $submission->id }}_submit" type="submit" class="btn btn-primary">Accept</button>
                        <input type="hidden" id="submission_{{ $submission->id }}_delete" name="delete" value="nodelete">
                        <button type="submit" class="btn btn-danger" onclick="event.preventDefault(); document.getElementById('submission_{{ $submission->id }}_delete').value = 'delete'; document.getElementById('submission_{{ $submission->id }}_submit').click();">Delete</button>
                        <input type="hidden" name="url" value="{{ route('result_submissions.list', ['association' => $association]) }}">
                    </form>
                    <?php endforeach; ?>
                </div>
            </div>
            <?php endif; ?>
        <?php endforeach; ?>
    <?php endforeach; ?>
@endsection