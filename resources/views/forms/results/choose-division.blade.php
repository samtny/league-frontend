@extends('layouts.full', ['name' => 'scores'])

@section('title', __('Score Submit'))

@section('content')
    @component('components/page-title')
        @slot('title')
            <?php echo __('Score Submit'); ?>
        @endslot
    @endcomponent
    <?php if (!$divisions->isEmpty()): ?>
    <form class="step" method="POST" action="{{ route('association.submit.score.step2', ['association' => $association]) }}">
        @csrf

        <input type="hidden" name="division_id" id="division_id" value="">
        <input type="submit" name="step1_submit" id="step1_submit" value="Choose">
    </form>
    <div class="link-buttons divisions">
        <nav class="association-nav">
            <ul>
                <?php foreach($divisions->sortBy('name') as $division): ?>
                    <li>
                        <a class="button" href="#" onclick="document.getElementById('division_id').value = '<?php echo $division->id; ?>'; document.getElementById('step1_submit').click();"><?php echo $division->name; ?></a>
                    </li>
                <?php endforeach; ?>
            </ul>
        </nav>
    </div>
    <?php else: ?>
    <div class="message message-empty no-rounds">
        There are no active rounds yet. Check back here later!
    </div>
    <?php endif; ?>
@endsection
