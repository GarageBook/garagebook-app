<?php

namespace App\Console\Commands;

use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

class GaragebookAdminCommand extends Command
{
    protected $signature = 'garagebook:admin
        {action : grant or revoke}
        {email : Existing user email address}
        {--force : Run without interactive confirmation}
        {--allow-no-admin : Allow revoking the last admin account}';

    protected $description = 'Grant or revoke GarageBook admin rights for an existing user.';

    public function handle(): int
    {
        $action = strtolower(trim((string) $this->argument('action')));

        if (! in_array($action, ['grant', 'revoke'], true)) {
            $this->error('Invalid action. Use grant or revoke.');

            return self::FAILURE;
        }

        $email = strtolower(trim((string) $this->argument('email')));
        $user = User::query()
            ->whereRaw('lower(email) = ?', [$email])
            ->first();

        if (! $user instanceof User) {
            $this->error('User not found: '.$this->argument('email'));

            return self::FAILURE;
        }

        $desiredAdminStatus = $action === 'grant';
        $currentAdminStatus = $user->isAdmin();

        $this->line('User ID: '.$user->id);
        $this->line('Name: '.$user->name);
        $this->line('Email: '.$user->email);
        $this->line('Current admin status: '.($currentAdminStatus ? 'yes' : 'no'));
        $this->line('Desired admin status: '.($desiredAdminStatus ? 'yes' : 'no'));

        if ($action === 'revoke' && $currentAdminStatus && ! $this->option('allow-no-admin') && $this->wouldRevokeLastAdmin($user)) {
            $this->error('Refusing to revoke the last admin account. Pass --allow-no-admin to override.');

            return self::FAILURE;
        }

        if (! $this->option('force') && ! $this->confirm('Apply this admin change?', false)) {
            $this->warn('Cancelled. No changes saved.');

            return self::FAILURE;
        }

        if ($currentAdminStatus !== $desiredAdminStatus) {
            $user->forceFill([
                'is_admin' => $desiredAdminStatus,
            ])->save();
        }

        Log::info('garagebook_admin_rights_changed', [
            'user_id' => $user->id,
            'email' => $user->email,
            'action' => $action,
            'environment' => app()->environment(),
            'changed' => $currentAdminStatus !== $desiredAdminStatus,
        ]);

        $this->info('Admin rights '.($desiredAdminStatus ? 'granted' : 'revoked').'.');

        return self::SUCCESS;
    }

    private function wouldRevokeLastAdmin(User $user): bool
    {
        return User::query()
            ->where('is_admin', true)
            ->whereKeyNot($user->getKey())
            ->doesntExist();
    }
}
