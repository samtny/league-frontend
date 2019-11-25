@extends('layouts.full', ['name' => 'standings'])

@section('title', __(':association Standings', ['association' => $association->name]))

@section('content')
    @component('components/page-title')
        @slot('title')
            Standings - <?php echo $association->name; ?>
        @endslot
    @endcomponent
    <?php if (!$schedules->isEmpty()): ?>
    <div class="standings-list">
            <?php foreach ($schedules->sortBy(['start_date', 'DESC']) as $schedule): ?>
            <div class="standings">

                <?php if (!empty($schedule->division)): ?>
                <h2 class="schedule-title"><?php echo $schedule->division->name; ?></h2>
                <?php else: ?>
                <h2 class="schedule-title no-division"><?php echo date('l, M j', strtotime($schedule->start_date)); ?></h2>
                <?php endif; ?>

                <?php

                $teamResults = DB::table('team_results')
                    ->join('teams', 'team_results.team_id', '=', 'teams.id')
                    ->select('teams.name', DB::raw('SUM(win) AS wins'), DB::raw('SUM(loss) AS losses'), DB::raw('SUM(points) as points'))
                    ->where('team_results.schedule_id', $schedule->id)
                    ->groupBy('teams.name')
                    ->orderByRaw('SUM(win) DESC')
                    ->orderByRaw('SUM(points) DESC')
                    ->orderBy('name')
                    ->get();

                ?>

                <?php if (!empty($teamResults)): ?>

                    <table>
                        <thead>
                            <tr>
                                <th>Team</th>
                                <th>Record</th>
                                <th>Points</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach($teamResults as $teamResult): ?>
                            <tr>
                                <td><?php echo $teamResult->name; ?></td>
                                <td><?php echo $teamResult->wins . ' - ' . $teamResult->losses; ?></td>
                                <td><?php echo $teamResult->points; ?></td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>

                <?php else: ?>
                    No results to report.
                <?php endif; ?>

            </div>
        <?php endforeach; ?>
    </div>
    <?php else: ?>
    There are no active standings yet. Check back here later!
    <?php endif; ?>
@endsection
