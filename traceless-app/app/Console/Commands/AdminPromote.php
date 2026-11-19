<?php

namespace App\Console\Commands;

use App\Models\User;
use App\Services\AuditService;
use Illuminate\Console\Command;

/**
 * Admin provisioning is deliberately a CLI operation: there is no web route that
 * can grant admin rights, so a compromised client session cannot escalate.
 */
class AdminPromote extends Command
{
    protected $signature = 'admin:promote
                            {email : The account email}
                            {--role=super_admin : admin or super_admin}';

    protected $description = 'Grant administrator access to an existing account';

    public function handle(AuditService $audit): int
    {
        $role = (string) $this->option('role');

        if (! in_array($role, [User::ROLE_ADMIN, User::ROLE_SUPER_ADMIN], true)) {
            $this->error('Role must be admin or super_admin.');

            return self::FAILURE;
        }

        $user = User::where('email', mb_strtolower(trim((string) $this->argument('email'))))->first();

        if (! $user) {
            $this->error('No account exists with that email. Register the account first.');

            return self::FAILURE;
        }

        $previous = $user->role;
        $user->update(['role' => $role, 'suspended_at' => null]);

        $audit->log(null, 'admin.provisioned', "CLI granted role {$role} to user #{$user->id} (was {$previous}).");

        $this->info("{$user->email} is now {$role}.");

        return self::SUCCESS;
    }
}
