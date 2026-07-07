<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class FixColumnTypesForForeignKeys extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        // divisions.association_id was created as a string column by mistake;
        // it needs to match associations.id's integer affinity for the foreign
        // key constraint added in a later migration to be reliable.
        Schema::table('divisions', function (Blueprint $table) {
            $table->unsignedBigInteger('association_id')->change();
        });

        // members.team_id will be set to null when its Team is deleted, so it
        // can no longer be NOT NULL.
        Schema::table('members', function (Blueprint $table) {
            $table->unsignedBigInteger('team_id')->nullable()->change();
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('members', function (Blueprint $table) {
            $table->unsignedBigInteger('team_id')->nullable(false)->change();
        });

        Schema::table('divisions', function (Blueprint $table) {
            $table->string('association_id')->change();
        });
    }
}
