<?php

namespace App\Console\Commands;

use App\Models\User;
use Illuminate\Console\Command;

class RegenerateAuthCodes extends Command
{
    protected $signature = 'app:regenerate-auth-codes';
    protected $description = 'Regenerate auth_codes for all users';

    public function handle(): void
    {
        $users = User::all();
        foreach ($users as $user) {
            $user->generateAuthCode();
            $user->save();
        }

        $this->info('Auth codes regenerated successfully.');
    }
}
