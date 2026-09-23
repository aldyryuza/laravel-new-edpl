<aside id="layout-menu" class="layout-menu menu-vertical menu">
    <div class="app-brand demo">
        <a href="{{ route('dashboard') }}" class="app-brand-link">
            {{--
                Dua varian logo: Sneat menukar keduanya lewat CSS saat sidebar
                menciut/terbuka, jadi tidak butuh JS. Ukuran diatur di public/css/app.css.
            --}}
            @php($brand = config('sneat.layout.brand'))
            <span class="app-brand-logo demo">
                <img src="{{ theme_asset(config('sneat.layout.logo')) }}" alt="{{ $brand }}" title="{{ $brand }}"
                    class="app-brand-img" />
                <img src="{{ theme_asset(config('sneat.layout.logo_collapsed') ?: config('sneat.layout.logo')) }}"
                    alt="{{ $brand }}" title="{{ $brand }}" class="app-brand-img-collapsed" />
            </span>
        </a>

        <a href="javascript:void(0);" class="layout-menu-toggle menu-link text-large ms-auto">
            <i class="icon-base bx bx-chevron-left"></i>
        </a>
    </div>

    <div class="menu-inner-shadow"></div>

    <ul class="menu-inner py-1">
        @include('layouts.partials.menu-items', ['items' => config('menu', [])])
    </ul>
</aside>

<div class="menu-mobile-toggler d-xl-none rounded-1">
    <a href="javascript:void(0);" class="layout-menu-toggle menu-link text-large text-bg-secondary p-2 rounded-1">
        <i class="bx bx-menu icon-base"></i>
        <i class="bx bx-chevron-right icon-base"></i>
    </a>
</div>