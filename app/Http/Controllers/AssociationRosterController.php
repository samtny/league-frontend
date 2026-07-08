<?php

namespace App\Http\Controllers;

use Illuminate\Http\Response;

class AssociationRosterController extends AssociationAwareController
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
            'association.roster',
            [
                'association' => $this->association,
                'teams' => $this->association->teams->where('active', true),
            ]
        );
    }
}
