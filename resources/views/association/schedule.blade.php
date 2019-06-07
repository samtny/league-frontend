@extends('layouts.app')

@section('title', 'Schedule')

@section('content')
    @component('page-title')
        @slot('title')
            Schedule - <?php echo $association->name; ?>
        @endslot
    @endcomponent
    <div class="schedules">
        <?php foreach ($association->schedules->sortBy('start_date') as $schedule): ?>
            <?php echo $schedule->division->name; ?>

            <?php foreach ($schedule->rounds->sortBy('start_date') as $round): ?>

                <h2><?php echo date('l, F j, Y', strtotime($round->start_date)); ?></h2>

                <table>
                    <thead>
                        <tr>
                            <th>Match</th>
                            <th>Location</th>
                            <th>Score</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($round->matches as $match): ?>
                            <?php $homeTeam = $match->homeTeam; ?>
                            <?php $awayTeam = $match->awayteam; ?>

                            <?php if (!empty($homeTeam) && !empty($awayTeam)): ?>
                            <tr>
                                <td>
                                    <strong><?php echo $match->homeTeam->name; ?></strong>
                                    <?php echo $match->awayTeam->name; ?>
                                </td>
                                <td>
                                    <?php echo $match->venue->name; ?>
                                </td>
                                <td>
                                </td>
                            </tr>
                            <?php endif; ?>
                        <?php endforeach; ?>
                    </tbody>
                </table>

            <?php endforeach ?>

        <?php endforeach; ?>
    </div>
@endsection
