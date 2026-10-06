@extends('layouts.app', ['vendors' => ['datatables', 'sweetalert2']])

@section('title', 'Region')

@section('content')
    <x-page-header title="Region" :breadcrumbs="[['label' => 'Master Organisasi']]">
        <x-slot:actions>
            @can('sales-regions.create')
                <button type="button" class="btn btn-primary" id="btn-add-region">
                    <i class="icon-base bx bx-plus me-1"></i> Tambah Region
                </button>
            @endcan
        </x-slot:actions>
    </x-page-header>

    {{-- Flag permission hanya untuk UX (tampil/sembunyi tombol); server tetap mengecek ulang. --}}
    <div class="card" id="regions-page" data-can-update="{{ (int) auth()->user()->can('sales-regions.update') }}"
        data-can-delete="{{ (int) auth()->user()->can('sales-regions.delete') }}">
        <div class="card-body pb-0">
            <div class="row">
                <div class="col-md-4">
                    <label for="filter-zone" class="form-label">Filter Zona</label>
                    <select class="form-select" id="filter-zone">
                        <option value="">Semua zona</option>
                        @foreach ($zones as $zone)
                            <option value="{{ $zone->id }}">{{ $zone->name }} ({{ $zone->code }})</option>
                        @endforeach
                    </select>
                </div>
            </div>
        </div>
        <div class="card-datatable table-responsive">
            <table class="table" id="regions-table">
                <thead>
                    <tr>
                        <th>Kode</th>
                        <th>Nama</th>
                        <th>Zona</th>
                        <th>Jumlah Area</th>
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
    <div class="modal fade" id="region-modal" tabindex="-1" aria-labelledby="region-modal-title" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered">
            <form class="modal-content" id="region-form" novalidate>
                <div class="modal-header">
                    <h5 class="modal-title" id="region-modal-title">Tambah Region</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Tutup"></button>
                </div>
                <div class="modal-body">
                    <div class="mb-4">
                        <label for="region-code" class="form-label">Kode <span class="text-danger">*</span></label>
                        <input type="text" class="form-control" id="region-code" name="code" maxlength="30" required />
                    </div>
                    <div class="mb-4">
                        <label for="region-name" class="form-label">Nama <span class="text-danger">*</span></label>
                        <input type="text" class="form-control" id="region-name" name="name" maxlength="100" required />
                    </div>
                    <div class="mb-4">
                        <label for="region-zone" class="form-label">Zona <span class="text-danger">*</span></label>
                        <select class="form-select" id="region-zone" name="zone_id" required>
                            <option value="">Pilih zona</option>
                            @foreach ($zones as $zone)
                                <option value="{{ $zone->id }}">
                                    {{ $zone->name }} ({{ $zone->code }}){{ $zone->is_active ? '' : ' - nonaktif' }}
                                </option>
                            @endforeach
                        </select>
                    </div>
                    <input type="hidden" name="is_active" value="0" />
                    <div class="form-check form-switch">
                        <input class="form-check-input" type="checkbox" id="region-active" name="is_active" value="1" checked />
                        <label class="form-check-label" for="region-active">Aktif</label>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-label-secondary" data-bs-dismiss="modal">Batal</button>
                    <button type="submit" class="btn btn-primary">Simpan</button>
                </div>
            </form>
        </div>
    </div>
@endpush

@push('scripts')
    <script src="{{ asset_v('js/pages/sales-regions.js') }}"></script>
@endpush
