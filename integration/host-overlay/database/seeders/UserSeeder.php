<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;

class UserSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        if (config('app.app_demo')) {
            $users = [
                [
                    'name' => 'John Doe',
                    'email' => 'john@example.com',
                    'password' => bcrypt('password'),
                    'email_verified_at' => now(),
                ],
                [
                    'name' => 'Jane Smith',
                    'email' => 'jane@example.com',
                    'password' => bcrypt('password'),
                    'email_verified_at' => now(),
                ],
                [
                    'name' => 'Bob Johnson',
                    'email' => 'bob@example.com',
                    'password' => bcrypt('password'),
                    'email_verified_at' => now(),
                ],
                [
                    'name' => 'Alice Williams',
                    'email' => 'alice@example.com',
                    'password' => bcrypt('password'),
                    'email_verified_at' => now(),
                ],
                [
                    'name' => 'Charlie Brown',
                    'email' => 'charlie@example.com',
                    'password' => bcrypt('password'),
                    'email_verified_at' => now(),
                ],
                [
                    'name' => 'Diana Prince',
                    'email' => 'diana@example.com',
                    'password' => bcrypt('password'),
                    'email_verified_at' => now(),
                ],
                [
                    'name' => 'Eve Davis',
                    'email' => 'eve@example.com',
                    'password' => bcrypt('password'),
                    'email_verified_at' => now(),
                ],
                [
                    'name' => 'Frank Miller',
                    'email' => 'frank@example.com',
                    'password' => bcrypt('password'),
                    'email_verified_at' => now(),
                ],
            ];
        } else {
            $users = [
                [
                    'name' => 'John Doe',
                    'email' => 'john@example.com',
                    'password' => bcrypt('password'),
                    'email_verified_at' => now(),
                ],
            ];
        }


        foreach ($users as $user) {
            User::create($user);
        }
    }
}
