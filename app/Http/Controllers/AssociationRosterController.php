<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;

use App\Association;

class AssociationRosterController extends AssociationAwareController
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
            'association.roster',
            [
                'association' => $this->association,
                'teams' => $this->association->teams->where('active', true),
            ]
        );
    }

}
