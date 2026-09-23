{{-- Halaman tanpa vendor tambahan: hanya core CSS/JS yang dimuat. --}}
@extends('layouts.app')

@section('title', 'Dashboard')

@section('content')
    <x-page-header title="Dashboard" subtitle="Ringkasan aplikasi." />

    <x-alert />

    <div class="row g-6">
        @foreach ([['Total Data', '0', 'bx-data', 'primary'], ['Menunggu', '0', 'bx-time-five', 'warning'], ['Selesai', '0', 'bx-check-circle', 'success'], ['Ditolak', '0', 'bx-x-circle', 'danger']] as [$label, $value, $icon, $color])
            <div class="col-sm-6 col-xl-3">
                <div class="card">
                    <div class="card-body d-flex align-items-center justify-content-between">
                        <div>
                            <p class="mb-1 text-body-secondary">{{ $label }}</p>
                            <h4 class="mb-0">{{ $value }}</h4>
                        </div>
                        <span class="badge bg-label-{{ $color }} rounded p-2">
                            <i class="icon-base bx {{ $icon }} icon-lg"></i>
                        </span>
                    </div>
                </div>
            </div>
        @endforeach
    </div>
@endsection
