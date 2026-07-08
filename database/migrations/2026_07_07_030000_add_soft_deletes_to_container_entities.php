<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AddSoftDeletesToContainerEntities extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::table('teams', function (Blueprint $table) {
            $table->softDeletes();
        });

        Schema::table('venues', function (Blueprint $table) {
            $table->softDeletes();
        });

        Schema::table('divisions', function (Blueprint $table) {
            $table->softDeletes();
        });

        Schema::table('series', function (Blueprint $table) {
            $table->softDeletes();
        });

        Schema::table('schedules', function (Blueprint $table) {
            $table->softDeletes();
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('teams', function (Blueprint $table) {
            $table->dropSoftDeletes();
        });

        Schema::table('venues', function (Blueprint $table) {
            $table->dropSoftDeletes();
        });

        Schema::table('divisions', function (Blueprint $table) {
            $table->dropSoftDeletes();
        });

        Schema::table('series', function (Blueprint $table) {
            $table->dropSoftDeletes();
        });

        Schema::table('schedules', function (Blueprint $table) {
            $table->dropSoftDeletes();
        });
    }
}
