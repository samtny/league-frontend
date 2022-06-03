<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;

use App\Association;
use App\Division;
use App\Match;
use App\ResultSubmission;
use App\Round;
use App\Series;
use App\Schedule;
use App\User;
use App\Venue;

use App\Http\Resources\Association as AssociationResource;
use App\Http\Resources\AssociationCollection as AssociationCollectionResource;

use Bouncer;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;
use Illuminate\Support\Str;

class AssociationsController extends Controller
{

    public function __construct(Request $request) {
        $subdomain = Arr::first(explode('.', \Request::getHost()));

        $this->association = Association::where('subdomain', $subdomain)->first();
    }

    public function index(Request $request) {
        $user = $request->user();

        $userAssociations = Association::all()->filter(function ($association) {
            return Bouncer::can('manage', $association);
        });

        return new AssociationCollectionResource($userAssociations);
    }

}
