<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class MatchAssociationId extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::table('matches', function (Blueprint $table) {
            $table->bigInteger('association_id')->nullable();
            $table->bigInteger('schedule_id')->nullable();
            $table->bigInteger('venue_id')->nullable();
            $table->smallInteger('sequence')->nullable();
            $table->unique(['schedule_id', 'round_id', 'venue_id', 'sequence']);
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('matches', function (Blueprint $table) {
            $table->dropUnique('matches_schedule_id_round_id_venue_id_sequence_unique');
            $table->dropColumn('association_id');
            $table->dropColumn('schedule_id');
            $table->dropColumn('venue_id');
            $table->dropColumn('sequence');
        });
    }
}
