<?php

namespace Database\Factories;

use App\Models\Server;
use App\Models\StoredFile;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<StoredFile>
 */
class StoredFileFactory extends Factory
{
    protected $model = StoredFile::class;

    /** @return array<string, mixed> */
    public function definition(): array
    {
        return [
            'server_id' => Server::factory(),
            'uploaded_by' => User::factory(),
            'attachable_type' => null,
            'attachable_id' => null,
            'disk' => 'local',
            'path' => 'uploads/'.fake()->uuid().'.txt',
            'original_name' => fake()->word().'.txt',
            'mime_type' => 'text/plain',
            'size' => fake()->numberBetween(100, 100000),
            'preview_path' => null,
            'preview_status' => null,
        ];
    }
}
