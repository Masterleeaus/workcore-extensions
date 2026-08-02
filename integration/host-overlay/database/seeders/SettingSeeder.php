<?php

namespace Database\Seeders;

use App\Models\Setting;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Cache;

class SettingSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $settings = [

            // Basic Info
            ['name' => 'app_name', 'value' => 'WorkCore'],
            ['name' => 'email', 'value' => 'info@workcore.local'],
            ['name' => 'phone', 'value' => '+01000000000'],
            ['name' => 'address', 'value' => ''],

            // System Settings
            ['name' => 'timezone', 'value' => 'UTC'],
            ['name' => 'date_format', 'value' => 'Y-m-d'],

            // Theme Settings
            ['name' => 'theme_primary_color', 'value' => '#724ff9'],
            ['name' => 'theme_secondary_color', 'value' => '#724ff9'],

            // Logo & Favicon
            ['name' => 'logo', 'value' => 'seeder/logo.png'],
            ['name' => 'favicon', 'value' => 'seeder/favicon.png'],
        ];

        foreach ($settings as $setting) {
            Setting::updateOrCreate(['name' => $setting['name']], ['value' => !blank($setting['value']) ? $setting['value'] : null]);
        }

        Cache::forget('settings');
    }
}
