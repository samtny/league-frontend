<?php

namespace App\Http\Controllers\Api;

use App\Association;
use App\Http\Controllers\Controller;
use App\Http\Resources\AssociationCollection as AssociationCollectionResource;
use Bouncer;
use Illuminate\Http\Request;

class AssociationsController extends Controller
{
    public function index(Request $request)
    {
        $user = $request->user();

        $userAssociations = Association::all()->filter(function ($association) {
            return Bouncer::can('manage', $association);
        });

        return new AssociationCollectionResource($userAssociations);
    }
}
