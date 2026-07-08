<?php

namespace App\Listeners;

use App\Association;
use App\AssociationUser;
use Illuminate\Auth\Events\Registered;
use Illuminate\Http\Request;
use Illuminate\Routing\Route;
use Illuminate\Support\Arr;

class UserRegistered
{
    private $request;

    /**
     * Create the event listener.
     *
     * @return void
     */
    public function __construct(Request $request)
    {
        $this->request = $request;
    }

    /**
     * Handle the event.
     *
     * @return void
     */
    public function handle(Registered $event)
    {

        $user = $event->user;

        // FIXME: put the 'register' route in a subdomain and get it that way instead:
        $subdomain = Arr::first(explode('.', $this->request->getHost()));

        $association = Association::where('subdomain', $subdomain)->first();

        if (! empty($user) && ! empty($association)) {
            $associationUser = new AssociationUser;

            $associationUser->user_id = $user->id;
            $associationUser->association_id = $association->id;

            $associationUser->save();
        }

    }
}
