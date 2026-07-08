<?php

use App\TeamResult;
use Illuminate\Database\Migrations\Migration;

class ResultsToTeamResults extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        $team_results = DB::select('SELECT m.schedule_id, r.match_id, r.home_team_id AS team_id, r.home_team_score AS points, CASE WHEN r.home_team_score > r.away_team_score THEN 1 ELSE 0 END AS win, CASE WHEN r.home_team_score < r.away_team_score THEN 1 ELSE 0 END AS loss, 0 as tie
        FROM results r
        INNER JOIN matches m ON r.match_id = m.id

        UNION ALL

        SELECT m.schedule_id, r.match_id, r.away_team_id AS team_id, r.away_team_score AS points, CASE WHEN r.away_team_score > r.home_team_score THEN 1 ELSE 0 END AS win, CASE WHEN r.away_team_score < r.home_team_score THEN 1 ELSE 0 END AS loss, 0 as tie
        FROM results r
        INNER JOIN matches m ON r.match_id = m.id
        ');

        foreach ($team_results as $team_result) {
            $teamResult = new TeamResult;

            $teamResult->schedule_id = $team_result->schedule_id;
            $teamResult->match_id = $team_result->match_id;
            $teamResult->team_id = $team_result->team_id;
            $teamResult->points = $team_result->points;
            $teamResult->win = $team_result->win;
            $teamResult->loss = $team_result->loss;
            $teamResult->tie = $team_result->tie;

            $teamResult->save();

            $teamResult = null;
        }

    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        DB::select('DELETE FROM match_results');
    }
}
