<?php

namespace App\Http\Controllers;

use App\Association;
use App\Series;
use App\User;
use Bouncer;
use Illuminate\Http\Request;

class UsersController extends Controller
{

    public function view(User $user) {
        if (Bouncer::can('view-users')) {
            $associations = Association::where('user_id', $user->id)->get();

            $series = Series::where('user_id', $user->id)->get();

            return view('user', [
                'user' => $user,
                'associations' => $associations,
                'series' => $series,
            ]);
        }
        else {
            return view('denied');
        }
    }

}
