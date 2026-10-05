<?php

namespace Tests\Feature;

use Carbon\CarbonImmutable;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;
use Tests\TestCase;

class CacheSerializationTest extends TestCase
{
    /**
     * Regresi: dashboard Pulse error "incomplete object" karena cache Laravel 13
     * menolak unserialize class yang tidak di-allowlist (cache.serializable_classes).
     */
    public function test_pulse_dashboard_results_survive_cache_roundtrip(): void
    {
        $store = Cache::store('file');
        $value = [collect([(object) ['latest' => CarbonImmutable::now(), 'items' => collect([1, 2])]]), 0.5, 'now'];

        $store->put('pulse-roundtrip-test', $value, 60);
        [$results] = $store->get('pulse-roundtrip-test');
        $store->forget('pulse-roundtrip-test');

        $this->assertInstanceOf(Collection::class, $results);
        $this->assertInstanceOf(CarbonImmutable::class, $results->first()->latest);
        $this->assertSame(2, $results->first()->items->count());
    }

    public function test_other_classes_are_still_not_unserialized_from_cache(): void
    {
        $store = Cache::store('file');

        $store->put('gadget-test', new \ArrayObject([1]), 60);
        $value = $store->get('gadget-test');
        $store->forget('gadget-test');

        $this->assertInstanceOf(\__PHP_Incomplete_Class::class, $value);
    }
}
