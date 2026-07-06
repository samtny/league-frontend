<?php

namespace Database\Seeders;

use App\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class LocalAdminUserSeeder extends Seeder
{
    /**
     * Run the database seeds.
     *
     * @return void
     */
    public function run()
    {
        $user = User::updateOrCreate(
            ['email' => 'admin@admin.com'],
            [
                'name' => 'admin',
                'password' => Hash::make('admin'),
                'email_verified_at' => now(),
            ]
        );

        \Bouncer::assign('admin')->to($user);
        \Bouncer::assign('authenticated')->to($user);
    }
}
