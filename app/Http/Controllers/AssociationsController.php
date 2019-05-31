<?php

namespace App\Http\Controllers;

use App\Association;
use Illuminate\Http\Request;

class AssociationsController extends Controller
{

    /**
     * Store a new association.
     *
     * @param  Request  $request
     * @return Response
     */
    public function store(Request $request) {

        $association = new Association;

        $association->name = $request->name;

        $association->save();

        // TODO: Do not necessarily "onboard" for certain roles?
        return redirect()->route('onboard.association', ['association' => $association]);

    }

}
