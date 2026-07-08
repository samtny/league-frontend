<?php

namespace App\Http\Controllers;

use Illuminate\Http\Response;

class AssociationStandingsArchiveController extends AssociationAwareController
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
            'association.standings.archive',
            [
                'association' => $this->association,
                'schedules' => $this->association->archivedSchedules,
            ]
        );
    }
}
