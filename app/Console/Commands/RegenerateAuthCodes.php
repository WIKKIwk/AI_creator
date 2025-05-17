<?php

namespace App\Console\Commands;

use App\Models\User;
use Illuminate\Console\Command;

class RegenerateAuthCodes extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'app:regenerate-auth-codes';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Regenerate auth_codes for all users';

    /**
     * Execute the console command.
     */
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
