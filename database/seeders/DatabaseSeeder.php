<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        $accounts = [
            ['Partner User',  'user@jmsoneit.com',       'user',        'Partner Company Inc.', 'user'],
            ['IT Support',    'itsupport@jmsoneit.com',  'it_support',  'JMS One IT', 'itsupport'],
            ['System Admin',  'admin@jmsoneit.com',      'admin',       'JMS One IT', 'admin'],
            ['Super Admin',   'superadmin@jmsoneit.com', 'super_admin', 'JMS One IT', 'superadmin'],
        ];

        foreach ($accounts as [$name, $email, $role, $company, $username]) {
            $u = User::updateOrCreate(['email' => $email], [
                'name' => $name, 'username' => $username, 'role' => $role, 'company' => $company,
                'password' => Hash::make('P@ssw0rd123'),
            ]);
            $u->forceFill(['email_verified_at' => now()])->save();
        }
    }
}
