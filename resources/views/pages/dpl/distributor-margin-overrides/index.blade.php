@extends('layouts.app', ['vendors' => ['datatables', 'select2', 'sweetalert2']])

@section('title', 'Margin Khusus Outlet')

@section('content')
    <x-page-header title="Margin Khusus Outlet" subtitle="Margin khusus per distributor dan outlet (legacy dpl_distributor_spesial_margin_outlet)."
        :breadcrumbs="[['label' => 'Komersial DPL']]">
        <x-slot:actions>
            @can('distributor-margin-overrides.create')
                <button type="button" class="btn btn-primary" id="btn-add-override">
                    <i class="icon-base bx bx-plus me-1"></i> Tambah Margin
                </button>
            @endcan
        </x-slot:actions>
    </x-page-header>

    <div class="alert alert-info d-flex align-items-start" role="alert">
        <i class="icon-base bx bx-info-circle me-2 mt-1"></i>
        <div>Margin hanya berlaku bila distributor ditandai "Punya margin khusus per outlet" di master Distributor.</div>
    </div>

    {{-- Flag permission hanya untuk UX (tampil/sembunyi tombol); server tetap mengecek ulang. --}}
    <div class="card" id="overrides-page" data-can-update="{{ (int) auth()->user()->can('distributor-margin-overrides.update') }}"
        data-can-delete="{{ (int) auth()->user()->can('distributor-margin-overrides.delete') }}">
        <div class="card-datatable table-responsive">
            <table class="table" id="overrides-table">
                <thead>
                    <tr>
                        <th>Distributor</th>
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
                        <div class="col-12">
                            <label for="override-distributor" class="form-label">Distributor <span class="text-danger">*</span></label>
                            <select class="form-select" id="override-distributor" name="distributor_id" required>
                                <option value="">Pilih distributor</option>
                                @foreach ($distributors as $distributor)
                                    <option value="{{ $distributor->id }}">{{ $distributor->name }} ({{ $distributor->code }}){{ $distributor->is_active ? '' : ' - nonaktif' }}{{ $distributor->is_special_case_margin ? '' : ' - belum ditandai margin khusus outlet' }}</option>
                                @endforeach
                            </select>
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
@endpush

@push('scripts')
    <script src="{{ asset_v('js/pages/dpl/distributor-margin-overrides.js') }}"></script>
@endpush
