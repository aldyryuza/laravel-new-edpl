@extends('layouts.app', ['vendors' => ['datatables', 'sweetalert2']])

@section('title', 'Zona')

@section('content')
    <x-page-header title="Zona" :breadcrumbs="[['label' => 'Master Organisasi']]">
        <x-slot:actions>
            {{-- Import membuat sekaligus memperbarui data: butuh hak create + update. --}}
            @if (auth()->user()->can('sales-zones.create') && auth()->user()->can('sales-zones.update'))
                <button type="button" class="btn btn-label-success" data-bs-toggle="modal" data-bs-target="#excel-import-modal">
                    <i class="icon-base bx bx-upload me-1"></i> Import Excel
                </button>
            @endif
            @can('sales-zones.create')
                <button type="button" class="btn btn-primary" id="btn-add-zone">
                    <i class="icon-base bx bx-plus me-1"></i> Tambah Zona
                </button>
            @endcan
        </x-slot:actions>
    </x-page-header>

    {{-- Flag permission hanya untuk UX (tampil/sembunyi tombol); server tetap mengecek ulang. --}}
    <div class="card" id="zones-page" data-can-update="{{ (int) auth()->user()->can('sales-zones.update') }}"
        data-can-delete="{{ (int) auth()->user()->can('sales-zones.delete') }}">
        <div class="card-datatable table-responsive">
            <table class="table" id="zones-table">
                <thead>
                    <tr>
                        <th>Kode</th>
                        <th>Nama</th>
                        <th>Jumlah Region</th>
                        <th>Status</th>
                        <th class="text-end">Aksi</th>
                    </tr>
                </thead>
                <tbody></tbody>
            </table>
        </div>
    </div>
@endsection

@push('modals')
    <div class="modal fade" id="zone-modal" tabindex="-1" aria-labelledby="zone-modal-title" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered">
            <form class="modal-content" id="zone-form" novalidate>
                <div class="modal-header">
                    <h5 class="modal-title" id="zone-modal-title">Tambah Zona</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Tutup"></button>
                </div>
                <div class="modal-body">
                    <div class="mb-4">
                        <label for="zone-code" class="form-label">Kode <span class="text-danger">*</span></label>
                        <input type="text" class="form-control" id="zone-code" name="code" maxlength="30" required />
                    </div>
                    <div class="mb-4">
                        <label for="zone-name" class="form-label">Nama <span class="text-danger">*</span></label>
                        <input type="text" class="form-control" id="zone-name" name="name" maxlength="100" required />
                    </div>
                    <input type="hidden" name="is_active" value="0" />
                    <div class="form-check form-switch">
                        <input class="form-check-input" type="checkbox" id="zone-active" name="is_active" value="1" checked />
                        <label class="form-check-label" for="zone-active">Aktif</label>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-label-secondary" data-bs-dismiss="modal">Batal</button>
                    <button type="submit" class="btn btn-primary">Simpan</button>
                </div>
            </form>
        </div>
    </div>
    @if (auth()->user()->can('sales-zones.create') && auth()->user()->can('sales-zones.update'))
        <x-excel-import-modal title="Import Zona dari Excel" upload-path="api/sales-zones/import"
            :template-url="route('sales-zones.import-template')" columns="kode, nama, aktif" />
    @endif
@endpush

@push('scripts')
    <script src="{{ asset_v('js/excel-import.js') }}"></script>
    <script src="{{ asset_v('js/pages/sales-zones.js') }}"></script>
@endpush
