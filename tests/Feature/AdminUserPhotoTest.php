<?php

namespace Tests\Feature;

use App\Models\Company;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/** A partner company's admin sets the profile photo of their own employees. */
class AdminUserPhotoTest extends TestCase
{
    use RefreshDatabase;

    private const PNG = 'iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mNkYPhfDwAChwGA60e6kgAAAABJRU5ErkJggg==';

    private Company $acme;
    private Company $globex;
    private User $acmeAdmin;
    private User $otherAcmeAdmin;
    private User $employee;
    private User $globexEmployee;
    private User $super;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('local');

        $this->acme   = Company::create(['name' => 'Acme Corp']);
        $this->globex = Company::create(['name' => 'Globex Ltd']);

        $this->super          = $this->person('super_admin', null);
        $this->acmeAdmin      = $this->person('admin', $this->acme);
        $this->otherAcmeAdmin = $this->person('admin', $this->acme);
        $this->employee       = $this->person('user', $this->acme);
        $this->globexEmployee = $this->person('user', $this->globex);
    }

    private function person(string $role, ?Company $company): User
    {
        static $n = 0;
        $n++;

        return User::factory()->create([
            'role' => $role, 'company_id' => $company?->id, 'username' => "photo{$n}", 'email' => "photo{$n}@example.test",
        ]);
    }

    private function png(string $name = 'face.png'): UploadedFile
    {
        return UploadedFile::fake()->createWithContent($name, base64_decode(self::PNG));
    }

    public function test_a_partner_admin_can_set_and_replace_an_employees_photo(): void
    {
        $this->actingAs($this->acmeAdmin)->post(route('users.avatar.update', $this->employee), ['avatar' => $this->png()])
            ->assertSessionHasNoErrors()->assertSessionHas('success');

        $first = $this->employee->fresh()->avatar;
        $this->assertNotNull($first);
        Storage::disk('local')->assertExists($first);

        // The employee and their team now see it.
        $this->actingAs($this->employee)->get("/avatars/{$this->employee->id}")->assertOk();

        // Replacing deletes the old file.
        $this->actingAs($this->acmeAdmin)->post(route('users.avatar.update', $this->employee), ['avatar' => $this->png('new.png')]);
        $second = $this->employee->fresh()->avatar;
        $this->assertNotSame($first, $second);
        Storage::disk('local')->assertMissing($first);
        Storage::disk('local')->assertExists($second);

        // The employee can see who changed it.
        $this->assertTrue($this->employee->activityLogs()->where('action', 'avatar_updated')->where('description', 'like', '%changed by%')->exists());
    }

    public function test_the_admin_can_remove_it(): void
    {
        $this->actingAs($this->acmeAdmin)->post(route('users.avatar.update', $this->employee), ['avatar' => $this->png()]);
        $path = $this->employee->fresh()->avatar;

        $this->actingAs($this->acmeAdmin)->delete(route('users.avatar.destroy', $this->employee))->assertSessionHas('success');

        $this->assertNull($this->employee->fresh()->avatar);
        Storage::disk('local')->assertMissing($path);
    }

    public function test_only_people_an_admin_manages_can_be_changed(): void
    {
        $file = fn () => ['avatar' => $this->png()];

        // Another company's employee, a fellow admin and the admin themselves are off limits.
        $this->actingAs($this->acmeAdmin)->post(route('users.avatar.update', $this->globexEmployee), $file())->assertForbidden();
        $this->actingAs($this->acmeAdmin)->post(route('users.avatar.update', $this->otherAcmeAdmin), $file())->assertForbidden();
        $this->actingAs($this->acmeAdmin)->post(route('users.avatar.update', $this->acmeAdmin), $file())->assertForbidden();
        $this->actingAs($this->acmeAdmin)->delete(route('users.avatar.destroy', $this->globexEmployee))->assertForbidden();

        // Ordinary staff cannot change anybody's photo.
        $this->actingAs($this->employee)->post(route('users.avatar.update', $this->employee), $file())->assertForbidden();

        $this->assertNull($this->globexEmployee->fresh()->avatar);

        // A super admin can reach every company.
        $this->actingAs($this->super)->post(route('users.avatar.update', $this->globexEmployee), $file())->assertSessionHasNoErrors();
        $this->assertNotNull($this->globexEmployee->fresh()->avatar);
    }

    public function test_bad_files_are_rejected(): void
    {
        $this->actingAs($this->acmeAdmin)->post(route('users.avatar.update', $this->employee), ['avatar' => UploadedFile::fake()->createWithContent('cv.pdf', '%PDF')])
            ->assertSessionHas('error');
        $this->actingAs($this->acmeAdmin)->post(route('users.avatar.update', $this->employee), ['avatar' => UploadedFile::fake()->create('big.png', 3000, 'image/png')])
            ->assertSessionHas('error');
        $this->actingAs($this->acmeAdmin)->post(route('users.avatar.update', $this->employee), [])->assertSessionHas('error');

        $this->assertNull($this->employee->fresh()->avatar);
    }

    public function test_the_edit_window_on_the_users_page_offers_the_photo_controls(): void
    {
        $this->actingAs($this->acmeAdmin)->get(route('users.index'))->assertOk()
            ->assertSee('Add photo')->assertSee('edit-photo-form', false)->assertSee('photo_url', false);
    }

    public function test_the_user_page_offers_the_photo_controls_to_the_admin_only(): void
    {
        $this->actingAs($this->acmeAdmin)->get(route('users.show', $this->employee))->assertOk()
            ->assertSee('Change the photo of', false)->assertDontSee('Remove photo');

        $this->actingAs($this->acmeAdmin)->post(route('users.avatar.update', $this->employee), ['avatar' => $this->png()]);
        $this->actingAs($this->acmeAdmin)->get(route('users.show', $this->employee))->assertSee('Remove photo');
    }
}
