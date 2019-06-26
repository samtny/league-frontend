@extends('layouts.full', ['name' => 'scores'])

@section('title', __('Who Won?'))

@section('content')
    @component('components/page-title')
        @slot('title')
            <?php echo __('Who Won?'); ?>
        @endslot
    @endcomponent
    <form class="scores" method="POST" action="{{ route('association.submit.score.step5', ['association' => $association]) }}">
        @csrf
        @honeypot

        <input type="hidden" name="submission_id" id="submission_id" value="<?php echo $submission->id; ?>">
        <input type="hidden" name="win_team_id" id="win_team_id" value="">

        <div class="link-buttons teams">
            <nav class="association-nav">
                <ul>
                    <li>
                        <a class="button" href="#" onclick="document.getElementById('win_team_id').value = '<?php echo $match->homeTeam->id; ?>'; document.getElementById('step5_submit').click();">
                            <span class="home-team"><?php echo $match->homeTeam->name; ?>
                        </a>
                    </li>
                    <li>
                        <a class="button" href="#" onclick="document.getElementById('win_team_id').value = '<?php echo $match->awayTeam->id; ?>'; document.getElementById('step5_submit').click();">
                            <span class="away-team"><?php echo $match->awayTeam->name; ?>
                        </a>
                    </li>
                </ul>
            </nav>
        </div>

        <div class="form-actions">
            <input type="submit" class="button" name="submit" id="step5_submit" value="Submit">
        </div>
    </form>
@endsection
