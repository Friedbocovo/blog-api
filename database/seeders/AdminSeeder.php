<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class AdminSeeder extends Seeder
{
    /**
     * Create the unique admin account.
     * Requirements 1.8: admin account created exclusively via seeder.
     */
    public function run(): void
    {
        User::firstOrCreate(
            ['email' => 'admin@blog.com'],
            [
                'name'     => 'Admin',
                'password' => Hash::make('password'),
                'role'     => 'admin',
            ]
        );

        $this->command->info('AdminSeeder: admin account ready (admin@blog.com).');
    }
}
