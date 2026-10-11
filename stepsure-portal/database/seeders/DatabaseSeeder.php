<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    /**
     * Local development only: creates the staff login from DEV_ADMIN_EMAIL / DEV_ADMIN_PASSWORD in .env.
     * On the live server, create staff accounts with `php artisan make:filament-user`.
     */
    public function run(): void
    {
        $email = env('DEV_ADMIN_EMAIL');
        $password = env('DEV_ADMIN_PASSWORD');

        if (app()->isLocal() && $email && $password) {
            User::updateOrCreate(['email' => $email], ['name' => 'Development Admin', 'password' => $password]);
            $this->command?->info("Dev admin ready: {$email} (password in .env)");
        }
    }
}
