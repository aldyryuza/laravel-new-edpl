<nav class="layout-navbar container-xxl navbar-detached navbar navbar-expand-xl align-items-center bg-navbar-theme"
    id="layout-navbar">

    {{-- Toggle menu (mobile) --}}
    <div class="layout-menu-toggle navbar-nav align-items-xl-center me-4 me-xl-0 d-xl-none">
        <a class="nav-item nav-link px-0 me-xl-6" href="javascript:void(0)">
            <i class="icon-base bx bx-menu icon-md"></i>
        </a>
    </div>

    <div class="navbar-nav-right d-flex align-items-center justify-content-end" id="navbar-collapse">
        @if (config('sneat.search'))
            <div class="navbar-nav align-items-center">
                <div class="nav-item navbar-search-wrapper mb-0">
                    <a class="nav-item nav-link search-toggler px-0" href="javascript:void(0);">
                        <span class="d-inline-block text-body-secondary fw-normal" id="autocomplete"></span>
                    </a>
                </div>
            </div>
        @endif

        <ul class="navbar-nav flex-row align-items-center ms-auto">

            {{--
                Light / dark / system.
                Disembunyikan saat template customizer aktif, karena panel
                customizer sudah menyediakan pengatur theme sendiri.
            --}}
            @unless (config('sneat.customizer'))
            <li class="nav-item dropdown me-2 me-xl-0">
                <a class="nav-link dropdown-toggle hide-arrow" id="nav-theme" href="javascript:void(0);"
                    data-bs-toggle="dropdown">
                    <i class="icon-base bx bx-sun icon-md theme-icon-active"></i>
                    <span class="d-none ms-2" id="nav-theme-text">Toggle theme</span>
                </a>
                <ul class="dropdown-menu dropdown-menu-end" aria-labelledby="nav-theme-text">
                    {{-- data-icon dipakai helpers.js untuk mengganti ikon aktif: tanpa prefix "bx-". --}}
                    @foreach (['light' => 'sun', 'dark' => 'moon', 'system' => 'desktop'] as $value => $icon)
                        <li>
                            <button type="button"
                                class="dropdown-item align-items-center {{ config('sneat.layout.theme') === $value ? 'active' : '' }}"
                                data-bs-theme-value="{{ $value }}" aria-pressed="false">
                                <span>
                                    <i class="icon-base bx bx-{{ $icon }} icon-md me-3"
                                        data-icon="{{ $icon }}"></i>{{ ucfirst($value) }}
                                </span>
                            </button>
                        </li>
                    @endforeach
                </ul>
            </li>
            @endunless

            {{-- User --}}
            <li class="nav-item navbar-dropdown dropdown-user dropdown">
                <a class="nav-link dropdown-toggle hide-arrow p-0" href="javascript:void(0);" data-bs-toggle="dropdown">
                    <div class="avatar avatar-online">
                        <img src="{{ theme_asset('img/avatars/1.png') }}" alt="" class="rounded-circle" />
                    </div>
                </a>
                <ul class="dropdown-menu dropdown-menu-end">
                    <li>
                        <div class="dropdown-item-text">
                            <div class="d-flex">
                                <div class="flex-shrink-0 me-3">
                                    <div class="avatar avatar-online">
                                        <img src="{{ theme_asset('img/avatars/1.png') }}" alt=""
                                            class="w-px-40 h-auto rounded-circle" />
                                    </div>
                                </div>
                                <div class="flex-grow-1">
                                    <h6 class="mb-0">{{ auth()->user()?->name ?? 'Guest' }}</h6>
                                    <small class="text-body-secondary">{{ auth()->user()?->email ?? '-' }}</small>
                                </div>
                            </div>
                        </div>
                    </li>
                    <li>
                        <div class="dropdown-divider my-1"></div>
                    </li>
                    @auth
                        @if (Route::has('logout'))
                            <li>
                                <form method="POST" action="{{ route('logout') }}">
                                    @csrf
                                    <button type="submit" class="dropdown-item">
                                        <i class="icon-base bx bx-power-off icon-md me-3"></i><span>Log Out</span>
                                    </button>
                                </form>
                            </li>
                        @endif
                    @else
                        @if (Route::has('login'))
                            <li>
                                <a class="dropdown-item" href="{{ route('login') }}">
                                    <i class="icon-base bx bx-log-in icon-md me-3"></i><span>Login</span>
                                </a>
                            </li>
                        @endif
                    @endauth
                </ul>
            </li>
        </ul>
    </div>
</nav>
