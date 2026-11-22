<?php

namespace Tests\Feature;

use App\Models\Company;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class FixRolesCommandTest extends TestCase
{
    use RefreshDatabase;

    /** Old data had admins and engineers filed under a partner company; the model would now fix them on save, so write straight to the table. */
    private function oldRow(string $email, string $role, Company $company): User
    {
        $u = User::factory()->create(['email' => $email, 'role' => 'user', 'company_id' => $company->id]);
        DB::table('users')->where('id', $u->id)->update(['role' => $role, 'company_id' => $company->id, 'company' => $company->name]);

        return $u->fresh();
    }

    public function test_it_only_previews_until_apply_is_given(): void
    {
        $acme = Company::create(['name' => 'Acme']);
        $u = $this->oldRow('boss@acme.test', 'admin', $acme);

        $this->artisan('jms:fix-roles')->assertSuccessful();

        $this->assertSame('admin', $u->fresh()->role);
        $this->assertSame($acme->id, (int) $u->fresh()->company_id);
    }

    public function test_apply_turns_partner_side_staff_into_customers_and_keeps_the_named_jms_staff(): void
    {
        $acme = Company::create(['name' => 'Acme']);
        $partnerAdmin = $this->oldRow('boss@acme.test', 'admin', $acme);
        $partnerIt    = $this->oldRow('it@acme.test', 'it_support', $acme);
        $ourAdmin     = $this->oldRow('admin@jmsoneit.com', 'admin', $acme);

        $this->artisan('jms:fix-roles', ['--keep-staff' => 'admin@jmsoneit.com', '--apply' => true])->assertSuccessful();

        $this->assertSame('user', $partnerAdmin->fresh()->role);
        $this->assertSame($acme->id, (int) $partnerAdmin->fresh()->company_id);
        $this->assertSame('user', $partnerIt->fresh()->role);

        $this->assertSame('admin', $ourAdmin->fresh()->role);
        $this->assertNull($ourAdmin->fresh()->company_id);
        $this->assertSame('JMS One IT', $ourAdmin->fresh()->company);
    }
}
