<?php

namespace Tests\Feature;

use App\Filament\Resources\Users\Pages\CreateUser;
use App\Filament\Resources\Users\Pages\EditUser;
use App\Filament\Resources\Users\UserResource;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Livewire\Livewire;
use Tests\TestCase;

class UserResourceTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_can_update_user_without_overwriting_password(): void
    {
        $admin = User::factory()->create([
            'is_admin' => true,
        ]);

        $user = User::factory()->create([
            'name' => 'Oude naam',
            'email' => 'oude@example.com',
            'password' => Hash::make('bestaand-wachtwoord'),
        ]);

        $originalPasswordHash = $user->password;

        $this->actingAs($admin);

        Livewire::test(EditUser::class, ['record' => $user->getRouteKey()])
            ->fillForm([
                'name' => 'Nieuwe naam',
                'email' => 'nieuw@example.com',
                'password' => '',
                'password_confirmation' => '',
            ])
            ->call('save')
            ->assertHasNoFormErrors();

        $user->refresh();

        $this->assertSame('Nieuwe naam', $user->name);
        $this->assertSame('nieuw@example.com', $user->email);
        $this->assertSame($originalPasswordHash, $user->password);
        $this->assertTrue(Hash::check('bestaand-wachtwoord', $user->password));
    }

    public function test_admin_can_change_user_password_with_confirmation(): void
    {
        $admin = User::factory()->create([
            'is_admin' => true,
        ]);

        $user = User::factory()->create([
            'password' => Hash::make('oud-wachtwoord'),
        ]);

        $this->actingAs($admin);

        Livewire::test(EditUser::class, ['record' => $user->getRouteKey()])
            ->fillForm([
                'name' => $user->name,
                'email' => $user->email,
                'password' => 'nieuw-veilig-wachtwoord',
                'password_confirmation' => 'nieuw-veilig-wachtwoord',
            ])
            ->call('save')
            ->assertHasNoFormErrors();

        $user->refresh();

        $this->assertTrue(Hash::check('nieuw-veilig-wachtwoord', $user->password));
        $this->assertFalse(Hash::check('oud-wachtwoord', $user->password));
    }

    public function test_admin_can_create_user_with_password_confirmation(): void
    {
        $admin = User::factory()->create([
            'is_admin' => true,
        ]);

        $this->actingAs($admin);

        Livewire::test(CreateUser::class)
            ->fillForm([
                'name' => 'Nieuwe gebruiker',
                'email' => 'nieuwe-gebruiker@example.com',
                'password' => 'tijdelijk-wachtwoord',
                'password_confirmation' => 'tijdelijk-wachtwoord',
            ])
            ->call('create')
            ->assertHasNoFormErrors();

        $user = User::query()->where('email', 'nieuwe-gebruiker@example.com')->first();

        $this->assertNotNull($user);
        $this->assertTrue(Hash::check('tijdelijk-wachtwoord', $user->password));
    }

    public function test_regular_users_cannot_access_user_management(): void
    {
        $user = User::factory()->create([
            'is_admin' => false,
        ]);

        $otherUser = User::factory()->create();

        $this->actingAs($user);

        $this->assertFalse(UserResource::canViewAny());
        $this->assertFalse(UserResource::shouldRegisterNavigation());
        $this->assertFalse(UserResource::canCreate());
        $this->assertFalse(UserResource::canEdit($otherUser));
        $this->assertFalse(UserResource::canDelete($otherUser));
    }
}
