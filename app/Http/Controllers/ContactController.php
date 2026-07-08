<?php

namespace App\Http\Controllers;

use App\ContactSubmission;
use Illuminate\Http\Request;

class ContactController extends AssociationAwareController
{
    public function contact()
    {
        return view('forms.contact', ['association' => $this->association]);
    }

    public function contactSubmit(Request $request)
    {
        if (empty($this->association)) {
            abort(404);
        }

        $validatedData = $request->validate([
            'email' => 'required|email|max:255',
        ]);

        $contact = new ContactSubmission;

        $contact->email = $request->email;
        $contact->reason = $request->reason;
        $contact->comment = $request->comment;
        $contact->association_id = $this->association->id;

        $contact->save();

        return redirect()->route('contact.thanks');
    }

    public function contactThanks()
    {
        return view('contact-thanks');
    }
}
