@extends('layouts.full', ['name' => 'scores'])

@section('title', __('Scores'))

@section('content')
    @component('components/page-title')
        @slot('title')
            <?php echo __('Scores'); ?>
        @endslot
    @endcomponent

    <form class="scores" method="POST" action="{{ route('association.submit.score.step4', ['association' => $association]) }}">
        @csrf

        <input type="hidden" name="match_id" id="match_id" value="<?php echo $match->id; ?>">
        <input type="hidden" name="home_team_id" id="home_team_id" value="<?php echo $match->homeTeam->id; ?>">
        <input type="hidden" name="away_team_id" id="away_team_id" value="<?php echo $match->awayTeam->id; ?>">

        <div class="form-item">
            <label for="home_team_score"><?php echo $match->hometeam->name; ?></label>
            <input type="text" class="score" name="home_team_score" id="home_team_score">
        </div>

        <div class="form-item">
            <label for="away_team_score"><?php echo $match->awayteam->name; ?></label>
            <input type="text" class="score" name="away_team_score" id="away_team_score">
        </div>

        <div class="form-actions">
            <input type="submit" class="button" name="submit" id="submit" value="Submit">
        </div>
    </form>
@endsection
