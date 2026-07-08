<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class NormalizeForeignKeyColumnTypes extends Migration
{
    /**
     * Run the migrations.
     *
     * Every id primary key in this schema is bigIncrements (bigint unsigned),
     * but nearly every column that references one was originally declared as
     * a plain (signed) bigInteger. InnoDB requires a foreign key column to
     * match the referenced column's type, including signedness, so each of
     * these must become unsignedBigInteger before the constraints in the
     * next migration can be added. Nullability is preserved as-is for each
     * column; only associations.user_id also needs nullable() added (see
     * the previous migration's original scope).
     *
     * divisions.association_id and members.team_id are already correct,
     * fixed in the fix_column_types_for_foreign_keys migration.
     *
     * @return void
     */
    public function up()
    {
        Schema::table('associations', function (Blueprint $table) {
            $table->unsignedBigInteger('user_id')->nullable()->change();
        });

        Schema::table('association_users', function (Blueprint $table) {
            $table->unsignedBigInteger('user_id')->change();
            $table->unsignedBigInteger('association_id')->change();
        });

        Schema::table('teams', function (Blueprint $table) {
            $table->unsignedBigInteger('association_id')->nullable()->change();
            $table->unsignedBigInteger('venue_id')->nullable()->change();
        });

        Schema::table('venues', function (Blueprint $table) {
            $table->unsignedBigInteger('association_id')->nullable()->change();
        });

        Schema::table('members', function (Blueprint $table) {
            $table->unsignedBigInteger('association_id')->change();
        });

        Schema::table('series', function (Blueprint $table) {
            $table->unsignedBigInteger('association_id')->nullable()->change();
        });

        Schema::table('schedules', function (Blueprint $table) {
            $table->unsignedBigInteger('association_id')->nullable()->change();
            $table->unsignedBigInteger('series_id')->nullable()->change();
            $table->unsignedBigInteger('division_id')->nullable()->change();
        });

        Schema::table('rounds', function (Blueprint $table) {
            $table->unsignedBigInteger('schedule_id')->nullable()->change();
            $table->unsignedBigInteger('series_id')->nullable()->change();
            $table->unsignedBigInteger('division_id')->nullable()->change();
        });

        Schema::table('matches', function (Blueprint $table) {
            $table->unsignedBigInteger('schedule_id')->nullable()->change();
            $table->unsignedBigInteger('round_id')->nullable()->change();
            $table->unsignedBigInteger('series_id')->nullable()->change();
            $table->unsignedBigInteger('association_id')->nullable()->change();
            $table->unsignedBigInteger('division_id')->nullable()->change();
            $table->unsignedBigInteger('venue_id')->nullable()->change();
            $table->unsignedBigInteger('home_team_id')->nullable()->change();
            $table->unsignedBigInteger('away_team_id')->nullable()->change();
        });

        Schema::table('result_submissions', function (Blueprint $table) {
            $table->unsignedBigInteger('association_id')->change();
            $table->unsignedBigInteger('schedule_id')->change();
            $table->unsignedBigInteger('match_id')->change();
            $table->unsignedBigInteger('win_team_id')->nullable()->change();
        });

        Schema::table('results', function (Blueprint $table) {
            $table->unsignedBigInteger('match_id')->change();
            $table->unsignedBigInteger('home_team_id')->nullable()->change();
            $table->unsignedBigInteger('away_team_id')->nullable()->change();
        });

        Schema::table('team_results', function (Blueprint $table) {
            $table->unsignedBigInteger('schedule_id')->nullable()->change();
            $table->unsignedBigInteger('match_id')->nullable()->change();
            $table->unsignedBigInteger('team_id')->nullable()->change();
        });

        Schema::table('contact_submissions', function (Blueprint $table) {
            $table->unsignedBigInteger('association_id')->nullable()->change();
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('associations', function (Blueprint $table) {
            $table->bigInteger('user_id')->nullable()->change();
        });

        Schema::table('association_users', function (Blueprint $table) {
            $table->bigInteger('user_id')->change();
            $table->bigInteger('association_id')->change();
        });

        Schema::table('teams', function (Blueprint $table) {
            $table->bigInteger('association_id')->nullable()->change();
            $table->bigInteger('venue_id')->nullable()->change();
        });

        Schema::table('venues', function (Blueprint $table) {
            $table->bigInteger('association_id')->nullable()->change();
        });

        Schema::table('members', function (Blueprint $table) {
            $table->bigInteger('association_id')->change();
        });

        Schema::table('series', function (Blueprint $table) {
            $table->bigInteger('association_id')->nullable()->change();
        });

        Schema::table('schedules', function (Blueprint $table) {
            $table->bigInteger('association_id')->nullable()->change();
            $table->bigInteger('series_id')->nullable()->change();
            $table->bigInteger('division_id')->nullable()->change();
        });

        Schema::table('rounds', function (Blueprint $table) {
            $table->bigInteger('schedule_id')->nullable()->change();
            $table->bigInteger('series_id')->nullable()->change();
            $table->bigInteger('division_id')->nullable()->change();
        });

        Schema::table('matches', function (Blueprint $table) {
            $table->bigInteger('schedule_id')->nullable()->change();
            $table->bigInteger('round_id')->nullable()->change();
            $table->bigInteger('series_id')->nullable()->change();
            $table->bigInteger('association_id')->nullable()->change();
            $table->bigInteger('division_id')->nullable()->change();
            $table->bigInteger('venue_id')->nullable()->change();
            $table->bigInteger('home_team_id')->nullable()->change();
            $table->bigInteger('away_team_id')->nullable()->change();
        });

        Schema::table('result_submissions', function (Blueprint $table) {
            $table->bigInteger('association_id')->change();
            $table->bigInteger('schedule_id')->change();
            $table->bigInteger('match_id')->change();
            $table->bigInteger('win_team_id')->nullable()->change();
        });

        Schema::table('results', function (Blueprint $table) {
            $table->bigInteger('match_id')->change();
            $table->bigInteger('home_team_id')->nullable()->change();
            $table->bigInteger('away_team_id')->nullable()->change();
        });

        Schema::table('team_results', function (Blueprint $table) {
            $table->bigInteger('schedule_id')->nullable()->change();
            $table->bigInteger('match_id')->nullable()->change();
            $table->bigInteger('team_id')->nullable()->change();
        });

        Schema::table('contact_submissions', function (Blueprint $table) {
            $table->bigInteger('association_id')->nullable()->change();
        });
    }
}
