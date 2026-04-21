<?php

namespace Tests\Feature;

use App\Models\Page;
use Illuminate\Foundation\Testing\RefreshDatabase;
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
            ->assertSee('Verstuur')
            ->assertSee('https://api.web3forms.com/submit')
            ->assertSee('cbee6c88-fd19-48a6-bc15-e21d10b8849d');
    }

    public function test_contact_page_shows_success_message_after_redirect(): void
    {
        Page::query()->create([
            'title' => 'Contact',
            'slug' => 'contact',
            'content' => 'Neem contact met ons op.',
        ]);

        $this->get('/contact?contact_sent=1')
            ->assertOk()
            ->assertSee('Je bericht is verzonden. We nemen zo snel mogelijk contact met je op.');
    }
}
