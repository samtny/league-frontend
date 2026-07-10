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
                        <div class="game"><?php echo $game['name']; ?></div>
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
@endsection
