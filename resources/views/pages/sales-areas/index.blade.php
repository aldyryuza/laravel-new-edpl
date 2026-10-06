@extends('layouts.app', ['vendors' => ['datatables', 'sweetalert2']])

@section('title', 'Area')

@section('content')
    <x-page-header title="Area" :breadcrumbs="[['label' => 'Master Organisasi']]">
        <x-slot:actions>
            {{-- Import membuat sekaligus memperbarui data: butuh hak create + update. --}}
            @if (auth()->user()->can('sales-areas.create') && auth()->user()->can('sales-areas.update'))
                <button type="button" class="btn btn-label-success" data-bs-toggle="modal" data-bs-target="#excel-import-modal">
                    <i class="icon-base bx bx-upload me-1"></i> Import Excel
                </button>
            @endif
            @can('sales-areas.create')
                <button type="button" class="btn btn-primary" id="btn-add-area">
                    <i class="icon-base bx bx-plus me-1"></i> Tambah Area
                </button>
            @endcan
        </x-slot:actions>
    </x-page-header>

    {{-- Flag permission hanya untuk UX (tampil/sembunyi tombol); server tetap mengecek ulang. --}}
    <div class="card" id="areas-page" data-can-update="{{ (int) auth()->user()->can('sales-areas.update') }}"
        data-can-delete="{{ (int) auth()->user()->can('sales-areas.delete') }}">
        <div class="card-body pb-0">
            <div class="row">
                <div class="col-md-4">
                    <label for="filter-region" class="form-label">Filter Region</label>
                    <select class="form-select" id="filter-region">
                        <option value="">Semua region</option>
                        @foreach ($regions as $region)
                            <option value="{{ $region->id }}">{{ $region->name }} ({{ $region->code }})</option>
                        @endforeach
                    </select>
                </div>
            </div>
        </div>
        <div class="card-datatable table-responsive">
            <table class="table" id="areas-table">
                <thead>
                    <tr>
                        <th>Kode</th>
                        <th>Nama</th>
                        <th>Region</th>
                        <th>Zona</th>
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
    <div class="modal fade" id="area-modal" tabindex="-1" aria-labelledby="area-modal-title" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered">
            <form class="modal-content" id="area-form" novalidate>
                <div class="modal-header">
                    <h5 class="modal-title" id="area-modal-title">Tambah Area</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Tutup"></button>
                </div>
                <div class="modal-body">
                    <div class="mb-4">
                        <label for="area-code" class="form-label">Kode <span class="text-danger">*</span></label>
                        <input type="text" class="form-control" id="area-code" name="code" maxlength="30" required />
                    </div>
                    <div class="mb-4">
                        <label for="area-name" class="form-label">Nama <span class="text-danger">*</span></label>
                        <input type="text" class="form-control" id="area-name" name="name" maxlength="100" required />
                    </div>
                    <div class="mb-4">
                        <label for="area-region" class="form-label">Region <span class="text-danger">*</span></label>
                        <select class="form-select" id="area-region" name="region_id" required>
                            <option value="">Pilih region</option>
                            @foreach ($regions as $region)
                                <option value="{{ $region->id }}">
                                    {{ $region->name }} ({{ $region->code }}) - {{ $region->zone?->name }}{{ $region->is_active ? '' : ' - nonaktif' }}
                                </option>
                            @endforeach
                        </select>
                    </div>
                    <input type="hidden" name="is_active" value="0" />
                    <div class="form-check form-switch">
                        <input class="form-check-input" type="checkbox" id="area-active" name="is_active" value="1" checked />
                        <label class="form-check-label" for="area-active">Aktif</label>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-label-secondary" data-bs-dismiss="modal">Batal</button>
                    <button type="submit" class="btn btn-primary">Simpan</button>
                </div>
            </form>
        </div>
    </div>
    @if (auth()->user()->can('sales-areas.create') && auth()->user()->can('sales-areas.update'))
        <x-excel-import-modal title="Import Area dari Excel" upload-path="api/sales-areas/import"
            :template-url="route('sales-areas.import-template')" columns="kode, nama, kode_region, aktif" />
    @endif
@endpush

@push('scripts')
    <script src="{{ asset_v('js/excel-import.js') }}"></script>
    <script src="{{ asset_v('js/pages/sales-areas.js') }}"></script>
@endpush
