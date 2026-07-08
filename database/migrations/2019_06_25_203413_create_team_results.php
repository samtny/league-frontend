<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateTeamResults extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('team_results', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->timestamps();
            $table->bigInteger('schedule_id')->nullable();
            $table->bigInteger('match_id')->nullable();
            $table->bigInteger('team_id')->nullable();
            $table->smallInteger('points')->nullable();
            $table->smallInteger('win')->nullable();
            $table->smallInteger('loss')->nullable();
            $table->smallInteger('tie')->nullable();
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('team_results');
    }
}
