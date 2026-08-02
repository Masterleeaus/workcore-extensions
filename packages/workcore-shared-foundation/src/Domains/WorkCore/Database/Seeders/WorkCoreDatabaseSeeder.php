<?php

declare(strict_types=1);

namespace App\Domains\WorkCore\Database\Seeders;

use Illuminate\Database\Seeder;

final class WorkCoreDatabaseSeeder extends Seeder
{
    public function run(): void
    {
        // WorkCore deliberately ships no tenant or host-identity fixtures.
        // Hosts may call vertical setup actions after creating an authenticated
        // user, company and company membership through their own test seeder.
    }
}
