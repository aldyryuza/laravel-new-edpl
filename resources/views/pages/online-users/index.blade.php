@extends('layouts.app')

@section('title', 'User Online')

@section('content')
    <x-page-header title="User Online" :breadcrumbs="[['label' => 'Pengaturan']]"
        subtitle="Diperbarui otomatis secara real-time, tanpa perlu reload.">
        <x-slot:actions>
            <span class="badge bg-label-secondary align-self-center" id="realtime-status" role="status">Menghubungkan...</span>
        </x-slot:actions>
    </x-page-header>

    <div class="alert alert-warning d-none" id="realtime-unavailable" role="alert">
        Server real-time tidak tersambung. Pastikan Reverb berjalan (<code>php artisan reverb:start</code>)
        dan konfigurasi <code>REVERB_*</code> di <code>.env</code> sudah benar.
    </div>

    <div class="card">
        <div class="card-header d-flex align-items-center justify-content-between">
            <h5 class="mb-0">Sedang online</h5>
            <span class="badge rounded-pill bg-success" data-online-count>-</span>
        </div>
        <div class="table-responsive">
            <table class="table table-hover mb-0" id="online-users-table">
                <thead>
                    <tr>
                        <th>NIK</th>
                        <th>Nama</th>
                        <th>Role aktif</th>
                        <th>Halaman yang dibuka</th>
                    </tr>
                </thead>
                <tbody>
                    <tr>
                        <td colspan="4" class="text-center text-body-secondary py-6">Menunggu data...</td>
                    </tr>
                </tbody>
            </table>
        </div>
    </div>
@endsection

@push('scripts')
    <script src="{{ asset_v('js/pages/online-users.js') }}"></script>
@endpush
