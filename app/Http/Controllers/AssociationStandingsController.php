<?php

namespace App\Http\Controllers;

use Illuminate\Http\Response;

class AssociationStandingsController extends AssociationAwareController
{
    /**
     * Display the specified resource.
     *
     * @param  int  $id
     * @return Response
     */
    public function show()
    {
        return view(
            'association.standings',
            [
                'association' => $this->association,
                'schedules' => $this->association->activeSchedules,
            ]
        );
    }
}
