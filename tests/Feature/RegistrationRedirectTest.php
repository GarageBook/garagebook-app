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

    public function test_admin_register_redirects_permanently_to_start(): void
    {
        $this->get('/admin/register')
            ->assertStatus(301)
            ->assertRedirect('/start');
    }
}
