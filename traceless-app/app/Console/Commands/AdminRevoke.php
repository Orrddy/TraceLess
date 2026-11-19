<?php

namespace App\Console\Commands;

use App\Models\User;
use App\Services\AuditService;
use Illuminate\Console\Command;

class AdminRevoke extends Command
{
    protected $signature = 'admin:revoke {email : The account email}';

    protected $description = 'Remove administrator access from an account';

    public function handle(AuditService $audit): int
    {
        $user = User::where('email', mb_strtolower(trim((string) $this->argument('email'))))->first();

        if (! $user) {
            $this->error('No account exists with that email.');

            return self::FAILURE;
        }

        if (! $user->isAdmin()) {
            $this->warn('That account is not an administrator.');

            return self::SUCCESS;
        }

        if (User::whereIn('role', [User::ROLE_ADMIN, User::ROLE_SUPER_ADMIN])->count() <= 1) {
            $this->error('Refusing to remove the last administrator.');

            return self::FAILURE;
        }

        $user->update(['role' => User::ROLE_CLIENT]);

        $audit->log(null, 'admin.revoked', "CLI removed admin access from user #{$user->id}.");

        $this->info("{$user->email} is back to a client account.");

        return self::SUCCESS;
    }
}
