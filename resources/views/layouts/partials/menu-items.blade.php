{{--
    Render menu secara rekursif.

    @param array $items  daftar item menu
--}}
@foreach ($items as $item)
    @continue(! \App\Support\Theme::menuIsVisible($item))

    @if (isset($item['header']))
        <li class="menu-header small">
            <span class="menu-header-text">{{ $item['header'] }}</span>
        </li>
    @else
        @php
            $hasChildren = ! empty($item['children']);
            $isActive = \App\Support\Theme::menuIsActive($item);
        @endphp

        <li class="menu-item {{ $isActive ? 'active' : '' }} {{ $hasChildren && $isActive ? 'open' : '' }}">
            <a href="{{ $hasChildren ? 'javascript:void(0);' : \App\Support\Theme::menuUrl($item) }}"
                class="menu-link {{ $hasChildren ? 'menu-toggle' : '' }}"
                @if (! $hasChildren && ! empty($item['target'])) target="{{ $item['target'] }}" @endif>
                @isset($item['icon'])
                    <i class="menu-icon icon-base {{ $item['icon'] }}"></i>
                @endisset
                <div>{{ $item['title'] }}</div>
                @isset($item['badge'])
                    <div class="badge rounded-pill ms-auto {{ $item['badge']['class'] ?? 'bg-primary' }}">
                        {{ $item['badge']['text'] }}
                    </div>
                @endisset
            </a>

            @if ($hasChildren)
                <ul class="menu-sub">
                    @include('layouts.partials.menu-items', ['items' => $item['children']])
                </ul>
            @endif
        </li>
    @endif
@endforeach
