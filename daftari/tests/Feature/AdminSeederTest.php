<?php

namespace Tests\Feature;

use App\Models\User;
use Database\Seeders\AdminSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

/**
 * Go-live audit finding: AdminSeeder previously created
 * admin@daftari.local with a fixed, documented password
 * ("Admin@12345") via updateOrCreate — meaning (a) that known password
 * would be live on any install where `migrate --seed` ran outside the
 * installer wizard's own flow, and (b) every later `migrate --seed`
 * (e.g. on redeploy) silently reset the password back to it even after
 * an operator had changed it. Now generates a random password on the
 * account's first creation only, and leaves an already-existing admin
 * account untouched on every later run.
 */
class AdminSeederTest extends TestCase
{
    use RefreshDatabase;

    public function test_first_run_creates_the_admin_with_a_random_unpredictable_password(): void
    {
        (new AdminSeeder)->run();

        $admin = User::where('email', 'admin@daftari.local')->first();

        $this->assertNotNull($admin);
        $this->assertSame('super_admin', $admin->role);
        $this->assertFalse(Hash::check('Admin@12345', $admin->password));
    }

    public function test_a_later_run_does_not_touch_an_already_existing_admins_password(): void
    {
        (new AdminSeeder)->run();
        $admin = User::where('email', 'admin@daftari.local')->first();

        $admin->forceFill(['password' => Hash::make('operator-changed-this')])->save();

        (new AdminSeeder)->run();

        $this->assertSame(1, User::where('email', 'admin@daftari.local')->count());
        $this->assertTrue(Hash::check('operator-changed-this', $admin->fresh()->password));
    }
}
