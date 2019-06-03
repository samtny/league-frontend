<?php

namespace App\Http\Controllers;

use App\User;
use Bouncer;
use Illuminate\Http\Request;

class AdminController extends Controller
{

    public function users() {
        if (Bouncer::can('administer-users')) {
            return view('admin.users', [
                'users' => User::all(),
            ]);
        }
        else {
            return view('denied');
        }
    }

}
