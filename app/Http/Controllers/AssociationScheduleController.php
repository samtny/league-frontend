<?php

namespace App\Http\Controllers;

use App\Schedule;
use Illuminate\Http\Response;

class AssociationScheduleController extends AssociationAwareController
{
    /**
     * Display the specified resource.
     *
     * @param  int  $id
     * @return Response
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
