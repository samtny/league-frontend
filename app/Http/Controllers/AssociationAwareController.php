<?php

namespace App\Http\Controllers;

use App\Association;
use Illuminate\Http\Request;

class AssociationAwareController extends Controller
{

    /**
     * @var \App\Association|null $association
     */
    protected $association;

    /**
     * Resolves directly via Association::resolveForRequest() rather than
     * reading the `association` request attribute ResolveAssociation sets -
     * see the doc comment on that method for why.
     */
    public function __construct(Request $request) {
        $this->association = Association::resolveForRequest($request);
    }

}
