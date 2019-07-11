<?php

namespace App\Http\Controllers;

use App\Association;
use App\ContactSubmission;
use Illuminate\Http\Request;

class ContactSubmissionsController extends Controller
{

    public function view(Association $association, ContactSubmission $contactSubmission) {
        return view('contact_submission.view', ['association' => $association, 'contactSubmission' => $contactSubmission]);
    }

    public function archive(Request $request, Association $association, ContactSubmission $contactSubmission) {
        $contactSubmission->archived = 1;

        $contactSubmission->save();

        return redirect()->route('contact_submissions.list', ['association' => $association]);
    }

}
