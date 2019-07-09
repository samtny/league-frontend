<?php

namespace App\Http\Controllers;

use App\ContactSubmission;
use Illuminate\Http\Request;

class InfoController extends Controller
{

    public function about() {
        return view('about');
    }

    public function contact() {
        return view('forms.contact');
    }

    public function contactSubmit(Request $request) {
        $validatedData = $request->validate([
            'email' => 'required|email|max:255',
        ]);

        $contact = new ContactSubmission();

        $contact->email = $request->email;
        $contact->reason = $request->reason;
        $contact->comment = $request->comment;

        $contact->save();

        return redirect()->route('message', ['title' => __('Thank You!'), 'message' => 'Thank you for contacting us, we will be in touch shortly.']);
    }

}
