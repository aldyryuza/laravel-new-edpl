@extends('layouts.app', ['vendors' => ['datatables', 'select2', 'sweetalert2']])

@section('title', 'PIC Outlet')

@section('content')
    <x-page-header title="PIC Outlet" subtitle="AM dan SPV penanggung jawab outlet (legacy dpl_map_outlet_users)."
        :breadcrumbs="[['label' => 'Master Outlet']]">
        <x-slot:actions>
            {{-- Import membuat sekaligus memperbarui data: butuh hak create + update. --}}
            @if (auth()->user()->can('outlet-sales-assignments.create') && auth()->user()->can('outlet-sales-assignments.update'))
                <button type="button" class="btn btn-label-success" data-bs-toggle="modal" data-bs-target="#excel-import-modal">
                    <i class="icon-base bx bx-upload me-1"></i> Import Excel
                </button>
            @endif
            @can('outlet-sales-assignments.create')
                <button type="button" class="btn btn-primary" id="btn-add-assignment">
                    <i class="icon-base bx bx-plus me-1"></i> Tambah PIC
                </button>
            @endcan
        </x-slot:actions>
    </x-page-header>

    <div class="alert alert-info d-flex align-items-start" role="alert">
        <i class="icon-base bx bx-info-circle me-2 mt-1"></i>
        <div>Pemilik DPL anak hasil konsolidasi Group RS = SPV outlet, bila kosong AM. Outlet tanpa PIC tidak bisa diajukan dalam konsolidasi Group RS.</div>
    </div>

    {{-- Flag permission hanya untuk UX (tampil/sembunyi tombol); server tetap mengecek ulang. --}}
    <div class="card" id="assignments-page" data-can-update="{{ (int) auth()->user()->can('outlet-sales-assignments.update') }}"
        data-can-delete="{{ (int) auth()->user()->can('outlet-sales-assignments.delete') }}">
        <div class="card-datatable table-responsive">
            <table class="table" id="assignments-table">
                <thead>
                    <tr>
                        <th>Outlet</th>
                        <th>AM</th>
                        <th>SPV</th>
                        <th class="text-end">Aksi</th>
                    </tr>
                </thead>
                <tbody></tbody>
            </table>
        </div>
    </div>
@endsection

@push('modals')
    <div class="modal fade" id="assignment-modal" tabindex="-1" aria-labelledby="assignment-modal-title" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered modal-lg">
            <form class="modal-content" id="assignment-form" novalidate>
                <div class="modal-header">
                    <h5 class="modal-title" id="assignment-modal-title">Tambah PIC</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Tutup"></button>
                </div>
                <div class="modal-body">
                    <div class="row g-4">
                        <div class="col-12">
                            <label for="assignment-outlet" class="form-label">Outlet <span class="text-danger">*</span></label>
                            <select class="form-select" id="assignment-outlet" name="outlet_id" required>
                                <option value="">Cari outlet</option>
                            </select>
                        </div>
                        <div class="col-md-6">
                            <label for="assignment-am" class="form-label">AM</label>
                            <select class="form-select user-select" id="assignment-am" name="am_user_id">
                                <option value="">Cari user</option>
                            </select>
                        </div>
                        <div class="col-md-6">
                            <label for="assignment-spv" class="form-label">SPV</label>
                            <select class="form-select user-select" id="assignment-spv" name="spv_user_id">
                                <option value="">Cari user</option>
                            </select>
                        </div>
                        <div class="col-12">
                            <div class="form-text">Isi minimal AM atau SPV.</div>
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

    @if (auth()->user()->can('outlet-sales-assignments.create') && auth()->user()->can('outlet-sales-assignments.update'))
        <x-excel-import-modal title="Import PIC Outlet dari Excel" upload-path="api/outlet-sales-assignments/import"
            :template-url="route('outlet-sales-assignments.import-template')"
            columns="kode_outlet, nik_am, nik_spv" />
    @endif
@endpush

@push('scripts')
    <script src="{{ asset_v('js/excel-import.js') }}"></script>
    <script src="{{ asset_v('js/pages/outlet-sales-assignments.js') }}"></script>
@endpush
