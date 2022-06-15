@extends('layouts.full', ['name' => 'schedule'])

@section('title', __(':association Schedule', ['association' => $association->name]))

@section('content')
    @component('components/page-title')
        @slot('title')
            Schedule - <?php echo $association->name; ?>
        @endslot
    @endcomponent
    <?php if (!$schedules->isEmpty()): ?>
    <div class="schedules">
        <?php foreach ($schedules->sortBy('start_date') as $schedule): ?>
            <div class="schedule">

                <?php if (!empty($schedule->division)): ?>
                <h2 class="schedule-title division"><?php echo $schedule->division->name; ?></h2>
                <?php else: ?>
                <h2 class="schedule-title no-division">Schedule</h2>
                <?php endif; ?>

                <?php foreach ($schedule->rounds
                    ->where('start_date','>=', date('Y-m-d', strtotime('-1 week')))
                    ->where('start_date', '<=', date('Y-m-d', strtotime("+2 weeks")))
                    ->sortBy('start_date') as $round): ?>

                    <h3><?php echo date('l, F j, Y', strtotime($round->start_date)); ?></h3>

                    <?php if ($round->matches->first()): ?>
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
                                            <?php echo $match->awayTeam->name; ?> @ <strong><?php echo $match->homeTeam->name; ?></strong>
                                        </td>
                                        <td>
                                            <?php echo $match->venue->name; ?>
                                        </td>
                                        <td>
                                            <?php
                                                $result = $match->result;

                                                if (!empty($result)) {
                                                    if (
                                                        !empty($result->away_team_score)
                                                        && is_numeric($result->away_team_score)
                                                        && !empty($result->home_team_score)
                                                        && is_numeric($result->home_team_score)
                                                        ) {

                                                            echo $result->away_team_score . ' - <strong>' . $result->home_team_score . '</strong>';

                                                    }
                                                }
                                            ?>
                                        </td>
                                    </tr>
                                    <?php endif; ?>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    <?php else: ?>
                        TBD
                    <?php endif; ?>

                <?php endforeach ?>
            </div>
            <nav class="link-menu">
                <ul>
                    <li>
                        <a href="{{ route('association.schedule.full', ['schedule' => $schedule]) }}">View Full Schedule</a>
                    </li>
                </ul>
            </nav>
        <?php endforeach; ?>
    </div>
    <?php else: ?>
    <div class="message message-empty no-schedules">
        There are no active schedules yet. Check back here later!
    </div>
    <?php endif; ?>
@endsection
