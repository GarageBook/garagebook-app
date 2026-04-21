<?php

namespace Tests\Feature;

use App\Mail\ContactFormMail;
use App\Models\Page;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

class ContactPageTest extends TestCase
{
    use RefreshDatabase;

    public function test_contact_page_shows_contact_form(): void
    {
        Page::query()->create([
            'title' => 'Contact',
            'slug' => 'contact',
            'content' => 'Neem contact met ons op.',
        ]);

        $this->get('/contact')
            ->assertOk()
            ->assertSee('Neem contact op')
            ->assertSee('Naam')
            ->assertSee('E-mailadres')
            ->assertSee('Vragen of opmerkingen')
            ->assertSee('Verstuur');
    }

    public function test_contact_form_sends_mail(): void
    {
        Mail::fake();

        Page::query()->create([
            'title' => 'Contact',
            'slug' => 'contact',
            'content' => 'Neem contact met ons op.',
        ]);

        $this->post('/contact', [
            'name' => 'Willem Tester',
            'email' => 'willem@example.com',
            'message' => 'Ik heb een vraag over GarageBook.',
        ])->assertRedirect();

        Mail::assertSent(ContactFormMail::class, function (ContactFormMail $mail) {
            return $mail->hasTo('willem@garagebook.nl')
                && $mail->name === 'Willem Tester'
                && $mail->email === 'willem@example.com'
                && $mail->message === 'Ik heb een vraag over GarageBook.';
        });
    }

    public function test_contact_form_validates_required_fields(): void
    {
        Page::query()->create([
            'title' => 'Contact',
            'slug' => 'contact',
            'content' => 'Neem contact met ons op.',
        ]);

        $this->from('/contact')
            ->post('/contact', [
                'name' => '',
                'email' => '',
                'message' => '',
            ])
            ->assertRedirect('/contact')
            ->assertSessionHasErrors(['name', 'email', 'message']);
    }
}
