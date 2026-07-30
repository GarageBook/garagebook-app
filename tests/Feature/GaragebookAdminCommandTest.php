<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Log;
use Tests\TestCase;

class GaragebookAdminCommandTest extends TestCase
{
    use RefreshDatabase;

    public function test_grant_makes_existing_user_admin_with_force(): void
    {
        Log::spy();

        $user = User::factory()->create([
            'name' => 'Trusted User',
            'email' => 'Trusted.User@Example.com',
            'is_admin' => false,
        ]);

        $this->artisan('garagebook:admin', [
            'action' => 'grant',
            'email' => 'trusted.user@example.com',
            '--force' => true,
        ])
            ->expectsOutput('User ID: '.$user->id)
            ->expectsOutput('Name: Trusted User')
            ->expectsOutput('Email: Trusted.User@Example.com')
            ->expectsOutput('Current admin status: no')
            ->expectsOutput('Desired admin status: yes')
            ->expectsOutput('Admin rights granted.')
            ->assertSuccessful();

        $this->assertTrue($user->fresh()->isAdmin());
        $this->assertDatabaseHas('users', [
            'id' => $user->id,
            'is_admin' => true,
        ]);

        Log::shouldHaveReceived('info')
            ->with('garagebook_admin_rights_changed', \Mockery::on(fn (array $context): bool => $context['user_id'] === $user->id
                && $context['email'] === 'Trusted.User@Example.com'
                && $context['action'] === 'grant'
                && $context['environment'] === 'testing'
                && $context['changed'] === true
            ))
            ->once();
    }

    public function test_revoke_removes_admin_rights_when_another_admin_remains(): void
    {
        $user = User::factory()->create([
            'email' => 'admin-one@example.com',
            'is_admin' => true,
        ]);
        User::factory()->create([
            'email' => 'admin-two@example.com',
            'is_admin' => true,
        ]);

        $this->artisan('garagebook:admin', [
            'action' => 'revoke',
            'email' => 'ADMIN-ONE@example.com',
            '--force' => true,
        ])
            ->expectsOutput('Current admin status: yes')
            ->expectsOutput('Desired admin status: no')
            ->expectsOutput('Admin rights revoked.')
            ->assertSuccessful();

        $this->assertFalse($user->fresh()->isAdmin());
    }

    public function test_unknown_user_fails_without_changes(): void
    {
        $this->artisan('garagebook:admin', [
            'action' => 'grant',
            'email' => 'missing@example.com',
            '--force' => true,
        ])
            ->expectsOutput('User not found: missing@example.com')
            ->assertFailed();
    }

    public function test_invalid_action_fails(): void
    {
        User::factory()->create([
            'email' => 'user@example.com',
        ]);

        $this->artisan('garagebook:admin', [
            'action' => 'promote',
            'email' => 'user@example.com',
            '--force' => true,
        ])
            ->expectsOutput('Invalid action. Use grant or revoke.')
            ->assertFailed();
    }

    public function test_cancelled_interactive_change_does_not_update_user(): void
    {
        $user = User::factory()->create([
            'email' => 'cancel@example.com',
            'is_admin' => false,
        ]);

        $this->artisan('garagebook:admin', [
            'action' => 'grant',
            'email' => 'cancel@example.com',
        ])
            ->expectsConfirmation('Apply this admin change?', 'no')
            ->expectsOutput('Cancelled. No changes saved.')
            ->assertFailed();

        $this->assertFalse($user->fresh()->isAdmin());
    }

    public function test_force_skips_confirmation_and_is_idempotent(): void
    {
        $user = User::factory()->create([
            'email' => 'already-admin@example.com',
            'is_admin' => true,
        ]);

        $this->artisan('garagebook:admin', [
            'action' => 'grant',
            'email' => 'already-admin@example.com',
            '--force' => true,
        ])
            ->expectsOutput('Current admin status: yes')
            ->expectsOutput('Desired admin status: yes')
            ->expectsOutput('Admin rights granted.')
            ->assertSuccessful();

        $this->assertTrue($user->fresh()->isAdmin());
    }

    public function test_revoke_refuses_to_remove_last_admin_by_default(): void
    {
        $admin = User::factory()->create([
            'email' => 'last-admin@example.com',
            'is_admin' => true,
        ]);

        $this->artisan('garagebook:admin', [
            'action' => 'revoke',
            'email' => 'last-admin@example.com',
            '--force' => true,
        ])
            ->expectsOutput('Refusing to revoke the last admin account. Pass --allow-no-admin to override.')
            ->assertFailed();

        $this->assertTrue($admin->fresh()->isAdmin());
    }

    public function test_allow_no_admin_can_revoke_last_admin(): void
    {
        $admin = User::factory()->create([
            'email' => 'last-admin@example.com',
            'is_admin' => true,
        ]);

        $this->artisan('garagebook:admin', [
            'action' => 'revoke',
            'email' => 'last-admin@example.com',
            '--force' => true,
            '--allow-no-admin' => true,
        ])
            ->expectsOutput('Admin rights revoked.')
            ->assertSuccessful();

        $this->assertFalse($admin->fresh()->isAdmin());
    }
}
