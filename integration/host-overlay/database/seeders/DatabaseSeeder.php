<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Log;

class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    public function run(): void
    {
        $this->call([
            SettingSeeder::class,
            UserSeeder::class,
        ]);

        $sourcePath = public_path('seeder');
        $destinationPath = storage_path('app/public/seeder');

        if (!File::exists($destinationPath)) {
            File::makeDirectory($destinationPath, 0755, true);
        }

        if (File::exists($sourcePath)) {
            File::copyDirectory($sourcePath, $destinationPath);
            Log::info('Seeder files copied successfully');
        }

        if (!file_exists(public_path('storage'))) {
            Artisan::call('storage:link');
            Log::info('Storage linked successfully');
        }
    }
}
