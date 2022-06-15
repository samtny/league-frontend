<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;

use App\Association;
use App\Schedule;

class AssociationScheduleController extends AssociationAwareController
{

    /**
     * Display the specified resource.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function showUpcoming()
    {
        return view(
            'association.schedule',
            [
                'association' => $this->association,
                'schedules' => $this->association->activeSchedules,
            ]
        );
    }

    public function showFull($string, Schedule $schedule)
    {
        return view(
            'association.schedule.full',
            [
                'association' => $this->association,
                'schedule' => $schedule,
            ]
        );
    }

}
