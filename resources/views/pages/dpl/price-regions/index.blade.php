@extends('layouts.app', ['vendors' => ['datatables', 'sweetalert2']])

@section('title', 'Region Harga')

@section('content')
    <x-page-header title="Region Harga" :breadcrumbs="[['label' => 'Komersial DPL']]">
        <x-slot:actions>
            {{-- Import membuat sekaligus memperbarui data: butuh hak create + update. --}}
            @if (auth()->user()->can('price-regions.create') && auth()->user()->can('price-regions.update'))
                <button type="button" class="btn btn-label-success" data-bs-toggle="modal" data-bs-target="#excel-import-modal">
                    <i class="icon-base bx bx-upload me-1"></i> Import Excel
                </button>
            @endif
            @can('price-regions.create')
                <button type="button" class="btn btn-primary" id="btn-add-price-region">
                    <i class="icon-base bx bx-plus me-1"></i> Tambah Region Harga
                </button>
            @endcan
        </x-slot:actions>
    </x-page-header>

    {{-- Flag permission hanya untuk UX (tampil/sembunyi tombol); server tetap mengecek ulang. --}}
    <div class="card" id="price-regions-page" data-can-update="{{ (int) auth()->user()->can('price-regions.update') }}"
        data-can-delete="{{ (int) auth()->user()->can('price-regions.delete') }}">
        <div class="card-datatable table-responsive">
            <table class="table" id="price-regions-table">
                <thead>
                    <tr>
                        <th>Kode</th>
                        <th>Nama</th>
                        <th>Keterangan</th>
                        <th>Outlet</th>
                        <th>Distributor</th>
                        <th>Harga Product</th>
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
    <div class="modal fade" id="price-region-modal" tabindex="-1" aria-labelledby="price-region-modal-title" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered">
            <form class="modal-content" id="price-region-form" novalidate>
                <div class="modal-header">
                    <h5 class="modal-title" id="price-region-modal-title">Tambah Region Harga</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Tutup"></button>
                </div>
                <div class="modal-body">
                    <div class="mb-4">
                        <label for="price-region-code" class="form-label">Kode <span class="text-danger">*</span></label>
                        <input type="text" class="form-control" id="price-region-code" name="code" maxlength="30" required />
                    </div>
                    <div class="mb-4">
                        <label for="price-region-name" class="form-label">Nama <span class="text-danger">*</span></label>
                        <input type="text" class="form-control" id="price-region-name" name="name" maxlength="100" required />
                    </div>
                    <div class="mb-4">
                        <label for="price-region-description" class="form-label">Keterangan</label>
                        <input type="text" class="form-control" id="price-region-description" name="description" maxlength="255" />
                    </div>
                    <input type="hidden" name="is_active" value="0" />
                    <div class="form-check form-switch">
                        <input class="form-check-input" type="checkbox" id="price-region-active" name="is_active" value="1" checked />
                        <label class="form-check-label" for="price-region-active">Aktif</label>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-label-secondary" data-bs-dismiss="modal">Batal</button>
                    <button type="submit" class="btn btn-primary">Simpan</button>
                </div>
            </form>
        </div>
    </div>

    @if (auth()->user()->can('price-regions.create') && auth()->user()->can('price-regions.update'))
        <x-excel-import-modal title="Import Region Harga dari Excel" upload-path="api/dpl/price-regions/import"
            :template-url="route('dpl.price-regions.import-template')" columns="kode, nama, aktif" />
    @endif
@endpush

@push('scripts')
    <script src="{{ asset_v('js/excel-import.js') }}"></script>
    <script src="{{ asset_v('js/pages/dpl/price-regions.js') }}"></script>
@endpush
