<?php

namespace App\Console\Commands;

use App\Models\User;
use Illuminate\Console\Command;

class ShowAuthCodes extends Command
{
    protected $signature = 'app:show-auth-codes {--email=} {--role=}';
    protected $description = 'Show users\' auth_code values (optionally filtered by email or role)';

    public function handle(): int
    {
        $query = User::query();
        if ($email = $this->option('email')) {
            $query->where('email', $email);
        }
        if ($role = $this->option('role')) {
            $query->where('role', $role);
        }

        $users = $query->get(['id', 'name', 'email', 'auth_code', 'role']);
        if ($users->isEmpty()) {
            $this->warn('No users found.');
            return self::SUCCESS;
        }

        $this->table(['ID', 'Name', 'Email', 'Auth Code', 'Role'], $users->map(function ($u) {
            return [$u->id, $u->name, $u->email, $u->auth_code, (string) $u->role];
        })->toArray());

        return self::SUCCESS;
    }
}

