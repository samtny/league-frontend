<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;

use App\Association;
use Illuminate\Support\Arr;

class AssociationAwareController extends Controller
{

    /**
     * @var \App\Association $association
     */
    protected $association;

    public function __construct(Request $request) {
        $subdomain = Arr::first(explode('.', \Request::getHost()));

        $this->association = Association::where('subdomain', $subdomain)->first();
    }

}
