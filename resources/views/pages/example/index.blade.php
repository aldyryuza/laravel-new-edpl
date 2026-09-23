{{--
    Contoh halaman CRUD.

    Vendor dideklarasikan di sini, jadi DataTables/Select2 hanya dimuat
    pada halaman yang memang membutuhkannya.
--}}
@extends('layouts.app', ['vendors' => ['datatables', 'select2', 'sweetalert2']])

@section('title', 'Contoh Halaman')

@section('content')
    <x-page-header title="Contoh Halaman" :breadcrumbs="[['label' => 'Master Data']]">
        <x-slot:actions>
            <button type="button" class="btn btn-primary">
                <i class="icon-base bx bx-plus me-1"></i> Tambah
            </button>
        </x-slot:actions>
    </x-page-header>

    <x-alert />

    <div class="card">
        <div class="card-datatable table-responsive">
            <table class="table" id="example-table">
                <thead>
                    <tr>
                        <th>#</th>
                        <th>Nama</th>
                        <th>Status</th>
                        <th>Aksi</th>
                    </tr>
                </thead>
                <tbody></tbody>
            </table>
        </div>
    </div>
@endsection

@push('scripts')
    <script src="{{ asset_v('js/pages/example.js') }}"></script>
@endpush
