<?php

namespace Tests\Feature;

use App\Models\User;
use App\Support\AuditLog;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Activitylog\Models\Activity;
use Tests\TestCase;

class RateLimitTest extends TestCase
{
    use RefreshDatabase;

    public function test_login_is_limited_per_nik_and_blocked_attempts_are_not_flooding_the_log(): void
    {
        User::factory()->create(['nik' => '123']);

        foreach (range(1, 5) as $attempt) {
            $this->post('/login', ['nik' => '123', 'password' => 'salah'])->assertRedirect();
        }

        foreach (range(1, 3) as $attempt) {
            $this->post('/login', ['nik' => '123', 'password' => 'salah'])->assertTooManyRequests();
        }

        // Hanya 5 percobaan yang sampai ke proses login, dan pelanggaran limit dicatat sekali.
        $this->assertSame(5, Activity::query()->where('event', 'login_failed')->count());

        $security = Activity::query()->where('log_name', AuditLog::SECURITY)->sole();
        $this->assertSame('123', $security->properties['nik']);
        $this->assertSame('login.store', $security->properties['route']);
    }

    public function test_api_is_limited_per_user(): void
    {
        $user = $this->userWithAccess(['users' => ['read']]);

        foreach (range(1, 60) as $request) {
            $this->actingAs($user)->getJson('/api/users/datatable')->assertOk();
        }

        $this->actingAs($user)->getJson('/api/users/datatable')->assertTooManyRequests();

        // User lain tidak ikut terkena limit.
        $this->actingAs($this->userWithAccess(['users' => ['read']]))->getJson('/api/users/datatable')->assertOk();
    }

    public function test_password_change_is_limited(): void
    {
        $user = User::factory()->create();

        foreach (range(1, 5) as $attempt) {
            $this->actingAs($user)->putJson('/api/profile/password', ['current_password' => 'salah'])->assertUnprocessable();
        }

        $this->actingAs($user)->putJson('/api/profile/password', ['current_password' => 'salah'])->assertTooManyRequests();
    }
}
