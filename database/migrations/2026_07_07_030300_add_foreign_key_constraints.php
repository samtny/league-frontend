<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class AddForeignKeyConstraints extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::table('associations', function (Blueprint $table) {
            $table->foreign('user_id')->references('id')->on('users')->nullOnDelete();
        });

        Schema::table('association_users', function (Blueprint $table) {
            $table->foreign('user_id')->references('id')->on('users')->cascadeOnDelete();
            $table->foreign('association_id')->references('id')->on('associations')->cascadeOnDelete();
        });

        Schema::table('divisions', function (Blueprint $table) {
            $table->foreign('association_id')->references('id')->on('associations')->cascadeOnDelete();
        });

        Schema::table('teams', function (Blueprint $table) {
            $table->foreign('association_id')->references('id')->on('associations')->cascadeOnDelete();
            $table->foreign('venue_id')->references('id')->on('venues')->nullOnDelete();
        });

        Schema::table('venues', function (Blueprint $table) {
            $table->foreign('association_id')->references('id')->on('associations')->cascadeOnDelete();
        });

        Schema::table('members', function (Blueprint $table) {
            $table->foreign('team_id')->references('id')->on('teams')->nullOnDelete();
            $table->foreign('association_id')->references('id')->on('associations')->cascadeOnDelete();
        });

        Schema::table('series', function (Blueprint $table) {
            $table->foreign('association_id')->references('id')->on('associations')->cascadeOnDelete();
        });

        Schema::table('schedules', function (Blueprint $table) {
            $table->foreign('association_id')->references('id')->on('associations')->cascadeOnDelete();
            $table->foreign('series_id')->references('id')->on('series')->cascadeOnDelete();
            $table->foreign('division_id')->references('id')->on('divisions')->nullOnDelete();
        });

        Schema::table('rounds', function (Blueprint $table) {
            $table->foreign('schedule_id')->references('id')->on('schedules')->cascadeOnDelete();
            $table->foreign('series_id')->references('id')->on('series')->cascadeOnDelete();
            $table->foreign('division_id')->references('id')->on('divisions')->nullOnDelete();
        });

        Schema::table('matches', function (Blueprint $table) {
            $table->foreign('schedule_id')->references('id')->on('schedules')->cascadeOnDelete();
            $table->foreign('round_id')->references('id')->on('rounds')->cascadeOnDelete();
            $table->foreign('series_id')->references('id')->on('series')->cascadeOnDelete();
            $table->foreign('association_id')->references('id')->on('associations')->cascadeOnDelete();
            $table->foreign('division_id')->references('id')->on('divisions')->nullOnDelete();
            $table->foreign('venue_id')->references('id')->on('venues')->nullOnDelete();
            $table->foreign('home_team_id')->references('id')->on('teams')->nullOnDelete();
            $table->foreign('away_team_id')->references('id')->on('teams')->nullOnDelete();
        });

        Schema::table('result_submissions', function (Blueprint $table) {
            $table->foreign('association_id')->references('id')->on('associations')->cascadeOnDelete();
            $table->foreign('schedule_id')->references('id')->on('schedules')->cascadeOnDelete();
            $table->foreign('match_id')->references('id')->on('matches')->cascadeOnDelete();
            $table->foreign('win_team_id')->references('id')->on('teams')->nullOnDelete();
        });

        Schema::table('results', function (Blueprint $table) {
            $table->foreign('match_id')->references('id')->on('matches')->cascadeOnDelete();
            $table->foreign('home_team_id')->references('id')->on('teams')->nullOnDelete();
            $table->foreign('away_team_id')->references('id')->on('teams')->nullOnDelete();
        });

        Schema::table('team_results', function (Blueprint $table) {
            $table->foreign('schedule_id')->references('id')->on('schedules')->cascadeOnDelete();
            $table->foreign('match_id')->references('id')->on('matches')->cascadeOnDelete();
            $table->foreign('team_id')->references('id')->on('teams')->cascadeOnDelete();
        });

        Schema::table('contact_submissions', function (Blueprint $table) {
            $table->foreign('association_id')->references('id')->on('associations')->cascadeOnDelete();
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('contact_submissions', function (Blueprint $table) {
            $table->dropForeign(['association_id']);
        });

        Schema::table('team_results', function (Blueprint $table) {
            $table->dropForeign(['schedule_id']);
            $table->dropForeign(['match_id']);
            $table->dropForeign(['team_id']);
        });

        Schema::table('results', function (Blueprint $table) {
            $table->dropForeign(['match_id']);
            $table->dropForeign(['home_team_id']);
            $table->dropForeign(['away_team_id']);
        });

        Schema::table('result_submissions', function (Blueprint $table) {
            $table->dropForeign(['association_id']);
            $table->dropForeign(['schedule_id']);
            $table->dropForeign(['match_id']);
            $table->dropForeign(['win_team_id']);
        });

        Schema::table('matches', function (Blueprint $table) {
            $table->dropForeign(['schedule_id']);
            $table->dropForeign(['round_id']);
            $table->dropForeign(['series_id']);
            $table->dropForeign(['association_id']);
            $table->dropForeign(['division_id']);
            $table->dropForeign(['venue_id']);
            $table->dropForeign(['home_team_id']);
            $table->dropForeign(['away_team_id']);
        });

        Schema::table('rounds', function (Blueprint $table) {
            $table->dropForeign(['schedule_id']);
            $table->dropForeign(['series_id']);
            $table->dropForeign(['division_id']);
        });

        Schema::table('schedules', function (Blueprint $table) {
            $table->dropForeign(['association_id']);
            $table->dropForeign(['series_id']);
            $table->dropForeign(['division_id']);
        });

        Schema::table('series', function (Blueprint $table) {
            $table->dropForeign(['association_id']);
        });

        Schema::table('members', function (Blueprint $table) {
            $table->dropForeign(['team_id']);
            $table->dropForeign(['association_id']);
        });

        Schema::table('venues', function (Blueprint $table) {
            $table->dropForeign(['association_id']);
        });

        Schema::table('teams', function (Blueprint $table) {
            $table->dropForeign(['association_id']);
            $table->dropForeign(['venue_id']);
        });

        Schema::table('divisions', function (Blueprint $table) {
            $table->dropForeign(['association_id']);
        });

        Schema::table('association_users', function (Blueprint $table) {
            $table->dropForeign(['user_id']);
            $table->dropForeign(['association_id']);
        });

        Schema::table('associations', function (Blueprint $table) {
            $table->dropForeign(['user_id']);
        });
    }
}
