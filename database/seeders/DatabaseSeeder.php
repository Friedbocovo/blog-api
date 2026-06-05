<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    /**
     * Seed the application's database.
     * Execution order: TagSeeder → AdminSeeder → PostSeeder → CommentSeeder
     */
    public function run(): void
    {
        $this->call([
            TagSeeder::class,
            AdminSeeder::class,
        ]);
    }
}
