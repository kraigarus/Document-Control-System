<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

class AdminAccountSeeder extends Seeder
{
    public function run(): void
    {
        $roleId = DB::table('condition_key')->where('key_name', 'Super Admin')->value('id');
        if (!$roleId) {
            $this->command?->error('Super Admin role is missing. Run migrations first.');
            return;
        }

        $accountId = DB::table('account')->updateOrInsert(
            ['username' => 'admin'],
            [
                'password' => Hash::make('123123123'),
                'account_status' => $roleId,
                'account_role' => $roleId,
                'account_active' => true,
                'date_updated' => now(),
            ]
        );

        $id = DB::table('account')->where('username', 'admin')->value('id');
        $dcOfficeId = DB::table('office')->where('office_code', 'DC')->value('id');

        DB::table('account_details')->updateOrInsert(
            ['account_id' => $id],
            [
                'first_name' => 'System',
                'last_name' => 'Administrator',
                'office_id' => $dcOfficeId,
                'email' => 'admin@admin.dcs',
            ]
        );

        $this->command?->info('Admin account ready: admin / 123123123');
    }
}
