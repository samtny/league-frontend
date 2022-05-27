<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;

use App\Association;

class AssociationStandingsController extends AssociationAwareController
{

    /**
     * Display the specified resource.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
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
