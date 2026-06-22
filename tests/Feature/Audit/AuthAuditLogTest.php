<?php

namespace Tests\Feature\Audit;

use App\Models\AuditLog;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class AuthAuditLogTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->withoutVite();
    }

    public function test_successful_login_creates_an_audit_log(): void
    {
        $user = User::factory()->create([
            'email' => 'auth.login.audit@example.com',
            'password' => Hash::make('password'),
        ]);

        $this->post(route('login.store'), [
            'email' => $user->email,
            'password' => 'password',
        ])
            ->assertRedirect(route('dashboard'));

        $this->assertAuthenticatedAs($user);

        $this->assertDatabaseHas('audit_logs', [
            'event' => 'login',
            'module' => 'auth',
            'user_id' => $user->id,
            'auditable_type' => $user->getMorphClass(),
            'auditable_id' => $user->id,
            'description' => 'User logged in.',
        ]);

        $auditLog = AuditLog::query()
            ->where('event', 'login')
            ->where('module', 'auth')
            ->where('user_id', $user->id)
            ->firstOrFail();

        $this->assertSame(['email' => $user->email], $auditLog->metadata);
    }

    public function test_failed_login_does_not_create_a_login_audit_log(): void
    {
        $user = User::factory()->create([
            'email' => 'auth.failed.audit@example.com',
            'password' => Hash::make('password'),
        ]);

        $this->from(route('login'))
            ->post(route('login.store'), [
                'email' => $user->email,
                'password' => 'wrong-password',
            ])
            ->assertRedirect(route('login'))
            ->assertSessionHasErrors('email');

        $this->assertGuest();

        $this->assertDatabaseMissing('audit_logs', [
            'event' => 'login',
            'module' => 'auth',
            'user_id' => $user->id,
        ]);
    }

    public function test_logout_creates_an_audit_log(): void
    {
        $user = User::factory()->create([
            'email' => 'auth.logout.audit@example.com',
        ]);

        $this->actingAs($user)
            ->post(route('logout'))
            ->assertRedirect(route('login'));

        $this->assertGuest();

        $this->assertDatabaseHas('audit_logs', [
            'event' => 'logout',
            'module' => 'auth',
            'user_id' => $user->id,
            'auditable_type' => $user->getMorphClass(),
            'auditable_id' => $user->id,
            'description' => 'User logged out.',
        ]);
    }
}
