<?php

namespace Database\Seeders;

use App\Enums\RoleType;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class DatabaseSeeder extends Seeder
{
    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        $this->createAdmin();

//        $this->call([]);
    }

    protected function createAdmin(): void
    {
        User::query()->updateOrCreate(
            ['email' => env('ADMIN_EMAIL')],
            [
                'role' => RoleType::ADMIN->value,
                'name' => 'Admin',
                'email' => env('ADMIN_EMAIL'),
                'password' => Hash::make(env('ADMIN_PASSWORD')),
            ]
        );
    }
}
