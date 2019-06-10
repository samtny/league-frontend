@extends('layouts.full', ['name' => 'standings'])

@section('title', __(':association Standings', ['association' => $association->name]))

@section('content')
    @component('components/page-title')
        @slot('title')
            Standings - <?php echo $association->name; ?>
        @endslot
    @endcomponent
    <div class="standings-list">
            <?php foreach ($association->schedules->sortBy(['start_date', 'DESC']) as $schedule): ?>
            <div class="standings">

                <?php if (!empty($schedule->division)): ?>
                <h2 class="schedule-title"><?php echo $schedule->division->name; ?></h2>
                <?php else: ?>
                <h2 class="schedule-title no-division"><?php echo date('l, M j', strtotime($schedule->start_date)); ?></h2>
                <?php endif; ?>

                <?php
                $results_table = [];

                foreach($schedule->matches as $match) {
                    $result = $match->result;

                    if (!empty($result)) {
                        $updated_datetime = new \DateTime($result->updated_at);

                        $since_updated = $updated_datetime->diff(new \DateTime('-15 minutes'));

                        $minutes = $since_updated->days * 24 * 60;
                        $minutes += $since_updated->h * 60;
                        $minutes += $since_updated->i;

                        if ($minutes > 15) {
                            if (!empty($result->home_team_score) && is_numeric($result->home_team_score)) {
                                if (empty($results_table[$result->home_team_id])) {
                                    $results_table[$result->home_team_id] = 0;
                                }
                                $results_table[$result->home_team_id] = $results_table[$result->home_team_id] + intval($result->home_team_score);

                                if (empty($results_table[$result->away_team_id])) {
                                    $results_table[$result->away_team_id] = 0;
                                }
                                $results_table[$result->away_team_id] = $results_table[$result->away_team_id] + intval($result->away_team_score);
                            }
                        }
                    }
                }

                arsort($results_table);
                ?>

                <?php if (!empty($results_table)): ?>

                    <table>
                        <thead>
                            <tr>
                                <th>Team</th>
                                <th>Points</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach($results_table as $team_id => $points): ?>
                            <tr>
                                <td><?php echo $association->teams->find($team_id)->name; ?></td>
                                <td><?php echo $points; ?></td>
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
@endsection
