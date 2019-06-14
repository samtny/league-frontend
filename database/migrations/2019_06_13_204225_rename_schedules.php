<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class RenameSchedules extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        $schedules = \App\Schedule::all();

        foreach ($schedules as $schedule) {
            if (!empty($schedule->division)) {
                $schedule->name = $schedule->division->name;
            }
            else {
                $schedule->name = date('Y-m-d', strtotime($schedule->start_date));
            }

            $schedule->save();
        }
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        //
    }
}
