<?php

namespace App\Http\Controllers;

use Bouncer;

class AdminController extends Controller
{
    public function admin()
    {
        if (Bouncer::can('view-admin-pages')) {
            return view('admin');
        } else {
            return view('denied');
        }
    }
}
