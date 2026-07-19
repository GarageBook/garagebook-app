<?php

namespace Tests\Feature;

use App\Filament\Auth\Http\Responses\RegistrationResponse;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Tests\TestCase;

class RegistrationRedirectTest extends TestCase
{
    use RefreshDatabase;

    public function test_registration_response_redirects_to_admin_dashboard(): void
    {
        $response = app(RegistrationResponse::class)->toResponse(Request::create('/admin/register', 'POST'));

        $this->assertSame(url('/admin'), $response->getTargetUrl());
    }

    public function test_start_redirects_temporarily_to_admin_register(): void
    {
        $this->get('https://app.garagebook.nl/start')
            ->assertStatus(302)
            ->assertRedirect('https://app.garagebook.nl/admin/register');
    }

    public function test_admin_register_page_is_publicly_available(): void
    {
        $this->get('/admin/register')
            ->assertOk()
            ->assertSee('GarageBook');
    }
}
