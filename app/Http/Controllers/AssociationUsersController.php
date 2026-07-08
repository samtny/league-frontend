<?php

namespace App\Http\Controllers;

use App\Association;
use App\User;
use Bouncer;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class AssociationUsersController extends Controller
{
    public function index(Association $association)
    {
        return view('association.users', ['association' => $association]);
    }

    public function view(Association $association, User $user)
    {
        return view('association.user.view', ['association' => $association, 'user' => $user]);
    }

    public function edit(Association $association, User $user)
    {
        return view('association.user.edit', ['association' => $association, 'user' => $user]);
    }

    public function update(Request $request, Association $association, User $user)
    {
        if (isset($request->assoc_admin)) {
            Bouncer::assign('assocadmin')->to($user);
            Bouncer::allow($user)->toManage($association);
        } else {
            Bouncer::disallow($user)->toManage($association);
            Bouncer::retract('assocadmin')->from($user);
        }

        $url = $request->url;

        if (! empty($url)) {
            return redirect($url)->with('success', 'Data saved successfully!');
        }

        return redirect()->route('user', ['id' => \Auth::user()->id]);

    }

    public function create(Association $association)
    {
        return view('association.user.add', ['association' => $association]);
    }

    public function token(Association $association, User $user)
    {
        return view('association.user.token', ['association' => $association, 'user' => $user]);
    }

    public function tokenRefresh(Association $association, User $user)
    {
        $token = Str::random(60);

        $user->forceFill([
            'api_token' => hash('sha256', $token),
        ])->save();

        return view('association.user.token-refresh', ['association' => $association, 'user' => $user, 'token' => $token]);
    }
}
