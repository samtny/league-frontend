@extends('layouts.full', ['name' => 'venues'])

@section('title', __(':association :label', ['association' => $association->name, 'label' => $association->venues_label_override ?: 'Venues']))

@section('content')
    @component('components/page-title')
        @slot('title')
            <?php echo ($association->venues_label_override ?: 'Venues') . ' - ' . $association->name; ?>
        @endslot
    @endcomponent
    <?php if (!$venues->isEmpty()): ?>
    <div class="venues">
        <?php foreach ($venues as $venue): ?>
            <div class="venue">
                <h2 class="venue-title"><?php echo $venue->name; ?></h2>

                <?php if (!empty($venue->games)): ?>
                <div class="games">
                    <?php foreach ($venue->games as $game): ?>
                        <div class="game">
                            <div class="game-name">
                                <?php if (!empty($game['opdb_id'])): ?>
                                    <a href="<?php echo e('https://app.matchplay.events/opdb/entries/'.$game['opdb_id'].'/pintips'); ?>" target="_blank" rel="noopener noreferrer">{{ $game['name'] }}</a>
                                <?php else: ?>
                                    {{ $game['name'] }}
                                <?php endif; ?>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
                <?php else: ?>
                <div class="message message-empty no-games">
                    No games listed yet.
                </div>
                <?php endif; ?>
            </div>
        <?php endforeach; ?>
    </div>
    <?php else: ?>
    <div class="message message-empty no-venues">
        There are no venues yet. Check back here later!
    </div>
    <?php endif; ?>
    <div class="row">
        <div class="attribution">
            Game data courtesy of <a href="https://pinballmap.com" target="_blank" rel="noopener noreferrer">Pinball Map</a>
        </div>
    </div>
@endsection
