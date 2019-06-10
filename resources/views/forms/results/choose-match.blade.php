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
    <?php foreach ($rounds as $round): ?>
    <h2 class="round-title"><?php echo date('l, M j', strtotime($round->start_date)); ?></h2>
    <div class="link-buttons matches">
        <nav class="association-nav">
            <ul>
                <?php foreach($round->matches as $match): ?>
                    <?php if (!empty($match->homeTeam) && !empty($match->awayTeam)): ?>
                    <li>
                        <a class="button" href="#" onclick="document.getElementById('match_id').value = '<?php echo $match->id; ?>'; document.getElementById('step2_submit').click();">
                            <?php echo $match->awayTeam->name; ?> @&nbsp;<strong><?php echo $match->homeTeam->name; ?></strong>
                        </a>
                    </li>
                    <?php endif; ?>
                <?php endforeach; ?>
            </ul>
        </nav>
    </div>
    <?php endforeach; ?>
@endsection
