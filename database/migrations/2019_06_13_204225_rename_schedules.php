<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class RenameSchedules extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        // Uses the query builder rather than the App\Schedule model: migrations
        // must reflect the schema as it existed at the time they were written,
        // not whatever the model looks like today (e.g. later SoftDeletes/scopes).
        $schedules = DB::table('schedules')->get();

        foreach ($schedules as $schedule) {
            $division = ! empty($schedule->division_id)
                ? DB::table('divisions')->find($schedule->division_id)
                : null;

            $name = $division ? $division->name : date('Y-m-d', strtotime($schedule->start_date));

            DB::table('schedules')->where('id', $schedule->id)->update(['name' => $name]);
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
