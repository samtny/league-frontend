@extends('layouts.full', ['name' => 'scores'])

@section('title', __('Score Submit'))

@section('content')
    @component('components/page-title')
        @slot('title')
            <?php echo __('Score Submit'); ?>
        @endslot
    @endcomponent
    <form class="step" method="POST" action="{{ route('association.submit.score.step3', ['association' => $association]) }}">
        @csrf
        <input type="hidden" name="match_id" id="match_id" value="">
        <input type="submit" name="step2_submit" id="step2_submit" value="Choose">
    </form>
    @forelse($rounds as $round)
    <h2 class="round-title"><?php echo date('l, M j', strtotime($round->start_date)); ?></h2>
    <div class="link-buttons matches">
        <nav class="association-nav">
            <ul>
                <?php foreach($round->matches as $match): ?>
                    <?php if (!empty($match->homeTeam) && !empty($match->awayTeam)): ?>
                    <li>
                        <a class="button" href="#" onclick="document.getElementById('match_id').value = '<?php echo $match->id; ?>'; document.getElementById('step2_submit').click();">
                            <span class="away-team"><?php echo $match->awayTeam->name; ?>&nbsp;@&nbsp;</span><strong><?php echo $match->homeTeam->name; ?></strong>
                        </a>
                    </li>
                    <?php endif; ?>
                <?php endforeach; ?>
            </ul>
        </nav>
    </div>
    @empty
    <div class="message no-scores">
        No rounds are available for score submission.
    </div>
    @endforelse
@endsection
