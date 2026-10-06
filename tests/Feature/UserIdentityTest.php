<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class UserIdentityTest extends TestCase
{
    use RefreshDatabase;

    public function test_new_user_gets_unique_uuid_while_id_stays_integer_key(): void
    {
        $first = User::factory()->create();
        $second = User::factory()->create();

        $this->assertTrue(Str::isUuid($first->uuid, 4));
        $this->assertNotSame($first->uuid, $second->uuid);
        $this->assertIsInt($first->getKey());
        $this->assertTrue($first->fresh()->is_active);
    }

    public function test_uuid_does_not_change_on_update(): void
    {
        $user = User::factory()->create();
        $uuid = $user->uuid;

        $user->update(['name' => 'Nama Baru']);

        $this->assertSame($uuid, $user->fresh()->uuid);
    }
}
