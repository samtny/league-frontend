<?php

use App\MatchResult;
use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class ResultsToMatchResults extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        $match_results = DB::select('SELECT m.schedule_id, r.match_id, r.home_team_id AS team_id, r.home_team_score AS points, IF(r.home_team_score > r.away_team_score, 1, 0) AS win, IF(r.home_team_score < r.away_team_score, 1, 0) AS loss, 0 as tie
        FROM results r
        INNER JOIN matches m ON r.match_id = m.id

        UNION ALL

        SELECT m.schedule_id, r.match_id, r.away_team_id AS team_id, r.away_team_score AS points, IF(r.away_team_score > r.away_team_score, 1, 0) AS win, IF(r.away_team_score < r.away_team_score, 1, 0) AS loss, 0 as tie
        FROM results r
        INNER JOIN matches m ON r.match_id = m.id
        ');

        foreach ($match_results as $match_result) {
            $matchResult = new MatchResult();

            $matchResult->schedule_id = $match_result->schedule_id;
            $matchResult->match_id = $match_result->match_id;
            $matchResult->team_id = $match_result->team_id;
            $matchResult->points = $match_result->points;
            $matchResult->win = $match_result->win;
            $matchResult->loss = $match_result->loss;
            $matchResult->tie = $match_result->tie;

            $matchResult->save();

            $matchResult = NULL;
        }

    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('match_results', function (Blueprint $table) {
            DB::select('DELETE FROM match_results');
        });
    }
}
