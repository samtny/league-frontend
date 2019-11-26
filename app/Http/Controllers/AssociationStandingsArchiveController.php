<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;

class AssociationStandingsArchiveController extends AssociationAwareController
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
            'association.standings.archive',
            [
                'association' => $this->association,
                'schedules' => $this->association->archivedSchedules,
            ]
        );
    }

}
