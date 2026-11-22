<?php

namespace Database\Seeders;

use App\Models\Company;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        // JMS One IT's own people (Super Admin, Admin, IT Engineers) belong to no partner company.
        // Only customers belong to a partner company.
        $partner = Company::firstOrCreate(['name' => 'Partner Company Inc.']);

        $accounts = [
            ['Partner Customer', 'user@jmsoneit.com',         'user',        $partner->id, 'user'],
            ['IT Engineer',      'itsupport@jmsoneit.com',    'it_support',  null,         'itsupport'],
            ['System Admin',     'admin@jmsoneit.com',        'admin',       null,         'admin'],
            ['Super Admin',      'superadmin@jmsoneit.com',   'super_admin', null,         'superadmin'],
            ['JMS Engineer',     'jmsengineer@jmsoneit.com',  'it_support',  null,         'jmsengineer'],
        ];

        foreach ($accounts as [$name, $email, $role, $companyId, $username]) {
            // The company name shown on screen (JMS One IT for our own people) is filled in by the User model.
            $u = User::updateOrCreate(['email' => $email], [
                'name' => $name, 'username' => $username, 'role' => $role, 'company_id' => $companyId,
                'password' => Hash::make('P@ssw0rd123'),
            ]);
            $u->forceFill(['email_verified_at' => now()])->save();
        }
    }
}
