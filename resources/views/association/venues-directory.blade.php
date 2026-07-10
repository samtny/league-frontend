@extends('layouts.full', ['name' => 'venues'])

@section('title', __(':association Venues', ['association' => $association->name]))

@section('content')
    @component('components/page-title')
        @slot('title')
            Venues - <?php echo $association->name; ?>
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
                        <?php
                            $refLinks = [];
                            if (!empty($game['ipdb_id'])) {
                                $refLinks[] = '<a href="'.e('https://www.ipdb.org/machine.cgi?id='.$game['ipdb_id']).'" target="_blank" rel="noopener noreferrer">ipdb</a>';
                            }
                            if (!empty($game['opdb_id'])) {
                                $refLinks[] = '<a href="'.e('https://app.matchplay.events/opdb/entries/'.$game['opdb_id'].'/pintips').'" target="_blank" rel="noopener noreferrer">pintips</a>';
                            }
                        ?>
                        <div class="game">
                            <div class="game-name">
                                {{ $game['name'] }}
                                <?php if (!empty($refLinks)): ?>
                                    <div class="game-links text-muted">(<?php echo implode(' / ', $refLinks); ?>)</div>
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
