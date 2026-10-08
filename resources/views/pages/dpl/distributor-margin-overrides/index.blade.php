@extends('layouts.app', ['vendors' => ['datatables', 'select2', 'sweetalert2']])

@section('title', 'Margin Khusus Outlet')

@section('content')
    <x-page-header title="Margin Khusus Outlet" subtitle="Margin khusus per grup distributor dan outlet (legacy dpl_distributor_spesial_margin_outlet)."
        :breadcrumbs="[['label' => 'Komersial DPL']]">
        <x-slot:actions>
            {{-- Import membuat sekaligus memperbarui data: butuh hak create + update. --}}
            @if (auth()->user()->can('distributor-margin-overrides.create') && auth()->user()->can('distributor-margin-overrides.update'))
                <button type="button" class="btn btn-label-success" data-bs-toggle="modal" data-bs-target="#excel-import-modal">
                    <i class="icon-base bx bx-upload me-1"></i> Import Excel
                </button>
            @endif
            @can('distributor-margin-overrides.create')
                <button type="button" class="btn btn-primary" id="btn-add-override">
                    <i class="icon-base bx bx-plus me-1"></i> Tambah Margin
                </button>
            @endcan
        </x-slot:actions>
    </x-page-header>

    <div class="alert alert-info d-flex align-items-start" role="alert">
        <i class="icon-base bx bx-info-circle me-2 mt-1"></i>
        <div>Berlaku untuk cabang grup distributor ini yang ditandai "Punya margin khusus per outlet" di master Distributor.</div>
    </div>

    {{-- Flag permission hanya untuk UX (tampil/sembunyi tombol); server tetap mengecek ulang. --}}
    <div class="card" id="overrides-page" data-can-update="{{ (int) auth()->user()->can('distributor-margin-overrides.update') }}"
        data-can-delete="{{ (int) auth()->user()->can('distributor-margin-overrides.delete') }}">
        <div class="card-datatable table-responsive">
            <table class="table" id="overrides-table">
                <thead>
                    <tr>
                        <th>Grup Distributor</th>
                        <th>Outlet</th>
                        <th class="text-end">Margin (%)</th>
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
    <div class="modal fade" id="override-modal" tabindex="-1" aria-labelledby="override-modal-title" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered modal-lg">
            <form class="modal-content" id="override-form" novalidate>
                <div class="modal-header">
                    <h5 class="modal-title" id="override-modal-title">Tambah Margin</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Tutup"></button>
                </div>
                <div class="modal-body">
                    <div class="row g-4">
                        <div class="col-md-6">
                            <label for="override-group" class="form-label">Grup Distributor <span class="text-danger">*</span></label>
                            <input type="text" class="form-control text-uppercase" id="override-group" name="distributor_group_code" maxlength="50"
                                list="override-group-options" placeholder="Kode grup distributor" required />
                            <datalist id="override-group-options">
                                @foreach ($groupCodes as $groupCode)
                                    <option value="{{ $groupCode }}"></option>
                                @endforeach
                            </datalist>
                        </div>
                        <div class="col-12">
                            <label for="override-outlet" class="form-label">Outlet <span class="text-danger">*</span></label>
                            <select class="form-select" id="override-outlet" name="outlet_id" required>
                                <option value="">Cari outlet</option>
                            </select>
                        </div>
                        <div class="col-md-6">
                            <label for="override-margin" class="form-label">Margin (%) <span class="text-danger">*</span></label>
                            <input type="number" class="form-control" id="override-margin" name="margin" min="0" step="0.0001" required />
                        </div>
                        <div class="col-12">
                            <label for="override-note" class="form-label">Catatan</label>
                            <textarea class="form-control" id="override-note" name="note" rows="2" maxlength="2000"></textarea>
                        </div>
                        <div class="col-12">
                            <input type="hidden" name="is_active" value="0" />
                            <div class="form-check form-switch">
                                <input class="form-check-input" type="checkbox" id="override-active" name="is_active" value="1" checked />
                                <label class="form-check-label" for="override-active">Aktif</label>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-label-secondary" data-bs-dismiss="modal">Batal</button>
                    <button type="submit" class="btn btn-primary">Simpan</button>
                </div>
            </form>
        </div>
    </div>

    @if (auth()->user()->can('distributor-margin-overrides.create') && auth()->user()->can('distributor-margin-overrides.update'))
        <x-excel-import-modal title="Import Margin Khusus Outlet dari Excel" upload-path="api/dpl/distributor-margin-overrides/import"
            :template-url="route('dpl.distributor-margin-overrides.import-template')"
            columns="kode_grup, kode_outlet, margin, catatan, aktif" />
    @endif
@endpush

@push('scripts')
    <script src="{{ asset_v('js/excel-import.js') }}"></script>
    <script src="{{ asset_v('js/pages/dpl/distributor-margin-overrides.js') }}"></script>
@endpush
