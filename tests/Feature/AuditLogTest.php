<?php

namespace Tests\Feature;

use App\Http\Middleware\ResolveActiveRole;
use App\Models\Menu;
use App\Models\Role;
use App\Models\User;
use App\Support\AuditLog;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Activitylog\Models\Activity;
use Tests\TestCase;

class AuditLogTest extends TestCase
{
    use RefreshDatabase;

    public function test_creating_user_is_logged_without_password(): void
    {
        $actor = $this->userWithAccess(['users' => ['create']]);

        $this->actingAs($actor)->postJson('/api/users', [
            'nik' => '123', 'name' => 'Budi', 'email' => 'budi@example.com', 'password' => 'rahasia123',
        ])->assertCreated();

        $log = Activity::query()->where('log_name', AuditLog::DATA)->where('event', 'created')->latest('id')->first();

        $this->assertSame('User Budi (123) dibuat', $log->description);
        $this->assertTrue($log->causer->is($actor));
        $this->assertSame('123', $log->attribute_changes['attributes']['nik']);
        $this->assertArrayNotHasKey('password', $log->attribute_changes['attributes']);
    }

    public function test_role_assignment_change_is_logged(): void
    {
        $user = User::factory()->create();
        $user->roles()->attach($old = Role::factory()->create(['name' => 'Lama']));
        $new = Role::factory()->create(['name' => 'Baru']);

        $this->actingAs($this->userWithAccess(['users' => ['update']]))->putJson("/api/users/{$user->id}", [
            'nik' => $user->nik, 'name' => $user->name, 'email' => $user->email, 'role_ids' => [$new->id],
        ])->assertOk();

        $log = Activity::query()->where('event', 'roles_updated')->sole();
        $this->assertSame(['Lama'], $log->attribute_changes['old']['roles']);
        $this->assertSame(['Baru'], $log->attribute_changes['attributes']['roles']);
    }

    public function test_role_access_change_logs_only_changed_flags(): void
    {
        $menu = Menu::factory()->create(['title' => 'Laporan']);
        $role = Role::factory()->create(['name' => 'Staff']);

        $this->actingAs($this->userWithAccess(['roles' => ['update']]))->putJson("/api/roles/{$role->id}/access", [
            'access' => [['menu_id' => $menu->id, 'can_read' => true]],
        ])->assertOk();

        $log = Activity::query()->where('description', 'like', 'Hak akses role Staff pada menu Laporan%')->sole();
        $this->assertTrue($log->attribute_changes['attributes']['can_read']);
    }

    public function test_login_failed_login_and_logout_are_logged(): void
    {
        $user = User::factory()->create(['nik' => '999']);

        $this->post('/login', ['nik' => '999', 'password' => 'salah']);
        $this->post('/login', ['nik' => '999', 'password' => 'password']);
        $this->post('/logout');

        $events = Activity::query()->where('log_name', AuditLog::AUTH)->orderBy('id')->pluck('event')->all();
        $this->assertSame(['login_failed', 'login', 'logout'], $events);

        $failed = Activity::query()->where('event', 'login_failed')->sole();
        $this->assertSame('999', $failed->properties['nik']);
        $this->assertNotEmpty($failed->properties['ip']);
        $this->assertTrue($failed->subject->is($user));
    }

    public function test_password_change_and_role_switch_are_logged(): void
    {
        $user = User::factory()->create();
        $user->roles()->attach([$a = Role::factory()->create(['name' => 'A'])->id, Role::factory()->create(['name' => 'B'])->id]);

        $this->actingAs($user)->withSession([ResolveActiveRole::SESSION_KEY => $a]);

        $this->putJson('/api/profile/password', [
            'current_password' => 'password', 'password' => 'passwordbaru', 'password_confirmation' => 'passwordbaru',
        ])->assertOk();
        $this->post('/active-role', ['role_id' => Role::query()->firstWhere('name', 'B')->id]);

        $password = Activity::query()->where('event', 'password_changed')->sole();
        $this->assertStringNotContainsString('passwordbaru', json_encode($password->toArray()));

        $switch = Activity::query()->where('event', 'role_switched')->sole();
        $this->assertSame('Ganti role dari A ke B', $switch->description);
    }

    public function test_audit_log_api_requires_access_and_filters(): void
    {
        $this->actingAs($this->userWithAccess([]))->getJson('/api/activity-logs')->assertForbidden();

        $budi = User::factory()->create(['nik' => '777', 'name' => 'Budi']);
        activity(AuditLog::AUTH)->causedBy($budi)->log('Login');
        activity(AuditLog::SECURITY)->log('Rate limit terlampaui');

        $reader = $this->userWithAccess(['audit-log' => ['read']]);

        $this->actingAs($reader)
            ->getJson('/api/activity-logs?category=security')
            ->assertOk()
            ->assertJsonPath('meta.total', 1)
            ->assertJsonPath('data.0.description', 'Rate limit terlampaui');

        $this->actingAs($reader)
            ->getJson('/api/activity-logs?category=auth&search=777')
            ->assertJsonPath('meta.total', 1)
            ->assertJsonPath('data.0.causer.name', 'Budi');

        $this->actingAs($reader)->get('/audit-log')->assertOk()->assertSee('audit-log.js');
    }
}
