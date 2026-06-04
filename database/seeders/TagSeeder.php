<?php

namespace Database\Seeders;

use App\Models\Tag;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

class TagSeeder extends Seeder
{
    /**
     * Seed 5 test tags.
     */
    public function run(): void
    {
        $tags = ['PHP', 'Laravel', 'JavaScript', 'React', 'DevOps'];

        foreach ($tags as $name) {
            Tag::firstOrCreate(
                ['slug' => Str::slug($name)],
                ['name' => $name]
            );
        }

        $this->command->info('TagSeeder: 5 tags created.');
    }
}
