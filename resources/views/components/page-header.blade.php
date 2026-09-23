{{--
    Judul halaman + breadcrumb + tombol aksi.

        <x-page-header title="Daftar User" :breadcrumbs="[['label' => 'Master', 'url' => '#']]">
            <x-slot:actions>
                <button class="btn btn-primary">Tambah</button>
            </x-slot:actions>
        </x-page-header>
--}}
@props([
    'title',
    'subtitle' => null,
    'breadcrumbs' => [],
    'actions' => null,
])

<div class="d-flex flex-wrap align-items-start justify-content-between gap-3 mb-4">
    <div>
        <h4 class="mb-1">
            @if ($breadcrumbs)
                <span class="text-body-secondary fw-light">
                    @foreach ($breadcrumbs as $crumb)
                        @isset($crumb['url'])
                            <a href="{{ $crumb['url'] }}" class="text-body-secondary">{{ $crumb['label'] }}</a>
                        @else
                            {{ $crumb['label'] }}
                        @endisset
                        /
                    @endforeach
                </span>
            @endif
            {{ $title }}
        </h4>

        @isset($subtitle)
            <p class="mb-0 text-body-secondary">{{ $subtitle }}</p>
        @endisset
    </div>

    @isset($actions)
        <div class="d-flex flex-wrap gap-2">{{ $actions }}</div>
    @endisset
</div>
