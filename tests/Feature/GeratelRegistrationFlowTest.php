<?php

namespace Tests\Feature;

use App\Filament\Auth\GeratelRegister;
use App\Filament\Auth\Register;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class GeratelRegistrationFlowTest extends TestCase
{
    use RefreshDatabase;

    public function test_geratel_register_page_is_publicly_available(): void
    {
        $this->get('/admin/register/geratel')
            ->assertOk()
            ->assertSee('garagebook-geratel-verified.png', false)
            ->assertDontSee('garagebook-logo.png', false)
            ->assertSee('Registreren')
            ->assertSee('Naam')
            ->assertSee('E-mailadres')
            ->assertSee('Wachtwoord')
            ->assertDontSee('inloggen op je account');
    }

    public function test_registration_via_geratel_flow_sets_registration_source(): void
    {
        Livewire::test(GeratelRegister::class)
            ->fillForm([
                'name' => 'Geratel Tester',
                'email' => 'geratel@example.com',
                'password' => 'password',
                'passwordConfirmation' => 'password',
            ])
            ->call('register');

        $user = User::query()->where('email', 'geratel@example.com')->firstOrFail();

        $this->assertSame('geratel', $user->registration_source);
        $this->assertTrue($user->isGeratelUser());
        $this->assertAuthenticatedAs($user);
    }

    public function test_registration_via_regular_flow_keeps_registration_source_null(): void
    {
        Livewire::test(Register::class)
            ->fillForm([
                'name' => 'Regular Tester',
                'email' => 'regular@example.com',
                'password' => 'password',
                'passwordConfirmation' => 'password',
            ])
            ->call('register');

        $user = User::query()->where('email', 'regular@example.com')->firstOrFail();

        $this->assertNull($user->registration_source);
        $this->assertFalse($user->isGeratelUser());
        $this->assertAuthenticatedAs($user);
    }

    public function test_geratel_logo_is_visible_for_geratel_user(): void
    {
        $user = User::factory()->create([
            'registration_source' => 'geratel',
        ]);

        $this->actingAs($user)
            ->get('/admin')
            ->assertOk()
            ->assertSee('geratel-cursist-verified.png', false)
            ->assertSee('data-geratel-topnav-logo', false);
    }

    public function test_geratel_logo_is_not_visible_for_regular_user(): void
    {
        $user = User::factory()->create([
            'registration_source' => null,
        ]);

        $this->actingAs($user)
            ->get('/admin')
            ->assertOk()
            ->assertDontSee('geratel-cursist-verified.png', false)
            ->assertDontSee('data-geratel-topnav-logo', false);
    }

    public function test_regular_register_page_remains_available_without_geratel_asset(): void
    {
        $this->get('/admin/register')
            ->assertOk()
            ->assertSee('GarageBook')
            ->assertDontSee('garagebook-geratel-verified.png', false);
    }
}
