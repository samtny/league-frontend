<?php

namespace App\Http\Controllers;

use App\Association;
use App\ContactSubmission;
use Illuminate\Http\Request;

class ContactSubmissionsController extends Controller
{
    /**
     * Display a listing of the resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function index(Association $association)
    {
        return view('association.contact_submissions', ['association' => $association]);
    }

    public function view(Association $association, ContactSubmission $contactSubmission)
    {
        return view('contact_submission.view', ['association' => $association, 'contactSubmission' => $contactSubmission]);
    }

    public function archive(Request $request, Association $association, ContactSubmission $contactSubmission)
    {
        $contactSubmission->archived = 1;

        $contactSubmission->save();

        return redirect()->route('contact_submissions.list', ['association' => $association]);
    }
}
