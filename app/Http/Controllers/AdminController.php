<?php

namespace App\Http\Controllers;

use App\Association;
use App\User;
use Bouncer;
use Illuminate\Http\Request;

class AdminController extends Controller
{

    public function overview() {
        if (Bouncer::can('view-admin-pages')) {
            return view('admin.overview');
        }
        else {
            return view('denied');
        }
    }

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

    public function associationsDeleted() {
        if (Bouncer::can('administer-associations')) {
            $associations = Association::onlyTrashed()->get();

            return view('admin.associations.trashed', ['associations' => $associations]);
        }
        else {
            return view('denied');
        }
    }

}
