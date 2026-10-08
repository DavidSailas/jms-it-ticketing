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
        // JMS itself has no company; partner accounts belong to a company.
        $partner = Company::firstOrCreate(['name' => 'Partner Company Inc.']);

        $accounts = [
            ['Partner User',  'user@jmsoneit.com',       'user',        $partner->id, 'user'],
            ['IT Support',    'itsupport@jmsoneit.com',  'it_support',  $partner->id, 'itsupport'],
            ['System Admin',  'admin@jmsoneit.com',      'admin',       $partner->id, 'admin'],
            ['Super Admin',   'superadmin@jmsoneit.com', 'super_admin', null,         'superadmin'],
            // JMS's own engineer: no partner company, so a super admin can assign them to any partner's ticket.
            ['JMS Engineer',  'jmsengineer@jmsoneit.com', 'it_support', null,         'jmsengineer'],
        ];

        foreach ($accounts as [$name, $email, $role, $companyId, $username]) {
            $u = User::updateOrCreate(['email' => $email], [
                'name' => $name, 'username' => $username, 'role' => $role, 'company_id' => $companyId,
                'company' => $role === 'super_admin' ? 'JMS One IT' : null,
                'password' => Hash::make('P@ssw0rd123'),
            ]);
            $u->forceFill(['email_verified_at' => now()])->save();
        }
    }
}
