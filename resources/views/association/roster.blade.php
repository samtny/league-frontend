@extends('layouts.full', ['name' => 'roster'])

@section('title', __(':association Roster', ['association' => $association->name]))

@section('content')
    @component('components/page-title')
        @slot('title')
            Roster - <?php echo $association->name; ?>
        @endslot
    @endcomponent
    <?php if (!$teams->isEmpty()): ?>
    <div class="rosters">
        <?php foreach ($teams->sortBy('name') as $team): ?>
            <div class="roster">
                <h2 class="roster-title">
                    <?php if ($team->homeVenue && $team->homeVenue->name !== $team->name): ?>
                        <?php echo $team->name; ?> <span class="text-light">@ <?php echo $team->homeVenue->name; ?></span>
                    <?php else: ?>
                        <?php echo $team->name; ?>
                    <?php endif; ?>
                </h2>

                <?php
                    $roleOrder = ['Captain' => 0, 'Player' => 1, 'Reserve' => 2];
                    $sortedRoster = $team->roster->sortBy(function ($member) use ($roleOrder) {
                        return sprintf('%d-%05d-%s', $roleOrder[$member->role], $member->order, $member->name);
                    });
                ?>
                <div class="members">
                    <?php foreach ($sortedRoster as $member): ?>
                        <div class="member"><?php echo $member->name; ?> - <?php echo $member->role; ?></div>
                    <?php endforeach; ?>
                </div>
            </div>
        <?php endforeach; ?>
    </div>
    <?php else: ?>
    <div class="message message-empty no-teams">
        There are no teams yet. Check back here later!
    </div>
    <?php endif; ?>
@endsection
