<?php

namespace Tests\Feature;

use App\Filament\Auth\GeratelRegister;
use App\Filament\Auth\Register;
use App\Filament\Resources\Users\Pages\CreateUser;
use App\Filament\Resources\Users\Pages\EditUser;
use App\Models\User;
use App\Models\Vehicle;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Queue;
use Livewire\Livewire;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class AdminSecurityTest extends TestCase
{
    use RefreshDatabase;

    public static function allowedEmails(): array
    {
        return [['willemvanveelen@icloud.com'], ['leroy@lenduria.nl']];
    }

    #[DataProvider('allowedEmails')]
    public function test_allowed_accounts_require_an_explicit_admin_grant(string $email): void
    {
        $user = User::factory()->create(['email' => $email, 'is_admin' => false]);
        $this->assertFalse($user->isAdmin());

        $this->artisan('garagebook:admin', [
            'action' => 'grant', 'email' => $email, '--force' => true,
        ])->assertSuccessful();

        $this->assertTrue($user->fresh()->isAdmin());
        $this->actingAs($user->fresh())->get('/admin/users')->assertOk();
    }

    public function test_database_flag_cannot_grant_another_email_admin_access(): void
    {
        $user = User::factory()->create();
        DB::table('users')->where('id', $user->id)->update(['is_admin' => true]);
        $user->refresh();

        $this->assertTrue($user->is_admin);
        $this->assertFalse($user->isAdmin());
        $this->actingAs($user)->get('/admin/users')->assertForbidden();
        $this->get('/admin')->assertOk()->assertDontSee('/admin/users', false);
    }

    #[DataProvider('allowedEmails')]
    public function test_mass_assignment_cannot_grant_admin_even_to_an_allowed_email(string $email): void
    {
        $this->assertFalse((new User)->isFillable('is_admin'));
        $user = User::query()->create([
            'name' => 'Account', 'email' => $email, 'password' => 'password', 'is_admin' => true,
        ]);
        $this->assertFalse($user->fresh()->isAdmin());

        $user->update(['name' => 'Updated', 'is_admin' => true]);
        $this->assertSame('Updated', $user->fresh()->name);
        $this->assertFalse($user->fresh()->is_admin);
    }

    public function test_model_save_cannot_persist_an_admin_flag_for_another_email(): void
    {
        $user = User::factory()->create();
        $user->forceFill(['is_admin' => true])->save();
        $this->assertFalse($user->fresh()->is_admin);
    }

    public function test_email_change_cannot_activate_a_preexisting_unauthorized_flag(): void
    {
        $user = User::factory()->create();
        DB::table('users')->where('id', $user->id)->update(['is_admin' => true]);
        $user->refresh()->update(['email' => 'leroy@lenduria.nl']);
        $this->assertFalse($user->fresh()->is_admin);
        $this->assertFalse($user->fresh()->isAdmin());
    }

    public function test_changing_admin_email_revokes_rights_but_case_only_change_preserves_them(): void
    {
        $user = User::factory()->admin()->create();
        $user->update(['email' => 'WillemVanVeelen@ICloud.Com']);
        $this->assertTrue($user->fresh()->isAdmin());
        $user->update(['email' => 'other@example.com']);
        $this->assertFalse($user->fresh()->is_admin);
        $this->assertFalse($user->fresh()->isAdmin());
    }

    public static function registrationForms(): array
    {
        return [[Register::class], [GeratelRegister::class]];
    }

    #[DataProvider('registrationForms')]
    public function test_registration_ignores_injected_admin_flag(string $component): void
    {
        Mail::fake();
        Queue::fake();
        Livewire::test($component)
            ->fillForm([
                'name' => 'New user', 'email' => 'leroy@lenduria.nl',
                'password' => 'password', 'passwordConfirmation' => 'password',
            ])
            ->set('data.is_admin', true)
            ->call('register')
            ->assertHasNoFormErrors();

        $user = User::where('email', 'leroy@lenduria.nl')->firstOrFail();
        $this->assertFalse($user->is_admin);
        $this->assertFalse($user->isAdmin());
    }

    public function test_normal_user_cannot_mount_account_editor_even_for_self(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user);
        Livewire::test(EditUser::class, ['record' => $user->getRouteKey()])->assertForbidden();
        $this->assertFalse($user->fresh()->is_admin);
    }

    public function test_account_editor_ignores_injected_admin_field(): void
    {
        $this->actingAs(User::factory()->admin()->create());
        $user = User::factory()->create(['email' => 'leroy@lenduria.nl']);
        Livewire::test(EditUser::class, ['record' => $user->getRouteKey()])
            ->fillForm(['name' => 'Changed', 'email' => $user->email])
            ->set('data.is_admin', true)
            ->call('save')
            ->assertHasNoFormErrors();
        $this->assertSame('Changed', $user->fresh()->name);
        $this->assertFalse($user->fresh()->is_admin);
    }

    public function test_user_creation_form_ignores_injected_admin_field(): void
    {
        $this->actingAs(User::factory()->admin()->create());
        Livewire::test(CreateUser::class)
            ->fillForm([
                'name' => 'Created', 'email' => 'leroy@lenduria.nl',
                'password' => 'password', 'password_confirmation' => 'password',
            ])
            ->set('data.is_admin', true)
            ->call('create')
            ->assertHasNoFormErrors();

        $this->assertFalse(User::where('email', 'leroy@lenduria.nl')->firstOrFail()->is_admin);
    }

    public function test_user_edit_rechecks_authorization_when_saving(): void
    {
        $admin = User::factory()->admin()->create();
        $user = User::factory()->create();
        $this->actingAs($admin);
        $component = Livewire::test(EditUser::class, ['record' => $user->getRouteKey()]);

        $this->actingAs($user);
        $component->set('data.is_admin', true)->call('save')->assertForbidden();
        $this->assertFalse($user->fresh()->is_admin);
    }

    public function test_unauthorized_database_flag_does_not_bypass_vehicle_ownership(): void
    {
        $owner = User::factory()->create();
        $user = User::factory()->create();
        DB::table('users')->where('id', $user->id)->update(['is_admin' => true]);
        $vehicle = Vehicle::query()->create([
            'user_id' => $owner->id, 'brand' => 'Kia', 'model' => 'Ceed',
        ]);

        $this->assertFalse(Gate::forUser($user->fresh())->allows('update', $vehicle));
        // The resource query scopes records to their owner before policy evaluation.
        $this->actingAs($user->fresh())->get('/admin/vehicles/'.$vehicle->id.'/edit')->assertNotFound();
    }
}
