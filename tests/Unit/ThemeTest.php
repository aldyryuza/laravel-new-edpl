<?php

namespace Tests\Unit;

use App\Support\Theme;
use Tests\TestCase;

class ThemeTest extends TestCase
{
    public function test_resolve_expands_dependencies_and_removes_duplicates(): void
    {
        config()->set('sneat.vendors', [
            'moment' => ['js' => ['vendor/libs/moment/moment.js']],
            'daterangepicker' => [
                'requires' => ['moment'],
                'js' => ['vendor/libs/bootstrap-daterangepicker/bootstrap-daterangepicker.js'],
            ],
        ]);

        $files = Theme::resolve(['daterangepicker', 'moment'], 'js');

        $this->assertCount(2, $files);
        $this->assertStringContainsString('moment.js', $files[0]);
        $this->assertStringContainsString('bootstrap-daterangepicker.js', $files[1]);
    }

    public function test_unknown_vendor_is_ignored(): void
    {
        $this->assertSame([], Theme::resolve(['tidak-ada'], 'css'));
    }

    public function test_asset_path_is_url_encoded(): void
    {
        config()->set('sneat.cache_bust', false);

        $this->assertStringContainsString(
            'assets/vendor/libs/%40form-validation/popular.js',
            Theme::asset('vendor/libs/@form-validation/popular.js')
        );
    }
}
