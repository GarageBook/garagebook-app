<?php

namespace App\Http\Controllers;

use App\Mail\ContactFormMail;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Mail;

class ContactFormController extends Controller
{
    public function __invoke(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255'],
            'message' => ['required', 'string', 'max:5000'],
        ]);

        Mail::to('willem@garagebook.nl')->send(
            new ContactFormMail(
                name: $data['name'],
                email: $data['email'],
                message: $data['message'],
            )
        );

        return back()->with('contact_status', 'Je bericht is verzonden. We nemen zo snel mogelijk contact met je op.');
    }
}
