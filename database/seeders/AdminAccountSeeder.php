<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\DB;

class AdminAccountSeeder extends Seeder
{
    public function run(): void
    {
        DB::table('accounts')->updateOrInsert(
            ['email' => 'admin@admin.dcs'],
            [
                'password'       => Hash::make('password'),
                'account_role'   => 'admin',
                'account_active' => true,
                'date_created'   => now(),
                'date_modified'  => now(),
            ]
        );

        $this->command->info('Admin account created: admin@admin.dcs / password');
    }
}