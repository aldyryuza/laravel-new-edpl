<?php

namespace Tests\Feature;

use Tests\TestCase;

class LayoutTest extends TestCase
{
    public function test_dashboard_renders_with_core_assets_only(): void
    {
        config()->set('sneat.customizer', false);

        $response = $this->get('/dashboard');

        $response->assertOk();
        $response->assertSee('assets/vendor/css/core.css', escape: false);
        $response->assertSee('assets/js/main.js', escape: false);

        // Vendor yang tidak diminta halaman tidak boleh ikut dimuat.
        $response->assertDontSee('datatables-bootstrap5.js', escape: false);
        $response->assertDontSee('select2.js', escape: false);

        // Template customizer mati secara default.
        $response->assertDontSee('template-customizer.js', escape: false);
    }

    public function test_page_only_loads_declared_vendors(): void
    {
        $response = $this->get('/example');

        $response->assertOk();
        $response->assertSee('datatables-bootstrap5.js', escape: false);
        $response->assertSee('select2.js', escape: false);
        $response->assertDontSee('apexcharts.js', escape: false);
    }

    public function test_sidebar_shows_logo_variants_without_brand_text(): void
    {
        $response = $this->get('/dashboard');

        // Dua varian logo: normal dan saat sidebar menciut.
        $response->assertSee('class="app-brand-img"', escape: false);
        $response->assertSee('class="app-brand-img-collapsed"', escape: false);

        // Nama brand tidak ditampilkan sebagai teks.
        $response->assertDontSee('app-brand-text', escape: false);
    }

    public function test_active_menu_is_marked(): void
    {
        $this->get('/dashboard')->assertSee('menu-item active', escape: false);
    }

    public function test_theme_switcher_is_wired_without_template_customizer(): void
    {
        config()->set('sneat.customizer', false);

        $response = $this->get('/dashboard');

        // Tombol theme + data-icon tanpa prefix "bx-" (dipakai helpers.js).
        $response->assertSee('data-bs-theme-value="dark"', escape: false);
        $response->assertSee('data-icon="moon"', escape: false);

        // Theme tersimpan diterapkan sebelum render, dan binding klik ada di app.js.
        $response->assertSee('templateCustomizer-vertical-menu-template--Theme', escape: false);
        $response->assertSee('js/app.js', escape: false);
    }

    public function test_theme_switcher_is_hidden_when_customizer_is_active(): void
    {
        config()->set('sneat.customizer', true);

        $response = $this->get('/dashboard');

        // Customizer punya pengatur theme sendiri, jadi tombol navbar disembunyikan.
        $response->assertSee('template-customizer.js', escape: false);
        $response->assertDontSee('data-bs-theme-value', escape: false);
    }

    public function test_root_redirects_to_dashboard(): void
    {
        $this->get('/')->assertRedirect('/dashboard');
    }
}
