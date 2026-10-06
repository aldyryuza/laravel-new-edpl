@extends('layouts.app', ['vendors' => ['datatables', 'sweetalert2']])

@section('title', 'Group RS')

@section('content')
    <x-page-header title="Group RS" subtitle="Group rumah sakit / group outlet (legacy GRUP3). Anggota diatur dari halaman Outlet."
        :breadcrumbs="[['label' => 'Master Outlet']]">
        <x-slot:actions>
            @can('outlet-groups.create')
                <button type="button" class="btn btn-primary" id="btn-add-group">
                    <i class="icon-base bx bx-plus me-1"></i> Tambah Group
                </button>
            @endcan
        </x-slot:actions>
    </x-page-header>

    {{-- Flag permission hanya untuk UX (tampil/sembunyi tombol); server tetap mengecek ulang. --}}
    <div class="card" id="groups-page" data-can-update="{{ (int) auth()->user()->can('outlet-groups.update') }}"
        data-can-delete="{{ (int) auth()->user()->can('outlet-groups.delete') }}"
        data-can-view-outlets="{{ (int) auth()->user()->can('outlets.read') }}" data-outlets-url="{{ route('outlets.index') }}">
        <div class="card-datatable table-responsive">
            <table class="table" id="groups-table">
                <thead>
                    <tr>
                        <th>Kode</th>
                        <th>Nama</th>
                        <th>Keterangan</th>
                        <th>Anggota Aktif</th>
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
    <div class="modal fade" id="group-modal" tabindex="-1" aria-labelledby="group-modal-title" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered">
            <form class="modal-content" id="group-form" novalidate>
                <div class="modal-header">
                    <h5 class="modal-title" id="group-modal-title">Tambah Group</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Tutup"></button>
                </div>
                <div class="modal-body">
                    <div class="mb-4">
                        <label for="group-code" class="form-label">Kode</label>
                        <input type="text" class="form-control" id="group-code" name="code" maxlength="50" />
                        <div class="form-text">Opsional, tapi wajib diisi bila group akan dirujuk dari import Excel outlet.</div>
                    </div>
                    <div class="mb-4">
                        <label for="group-name" class="form-label">Nama <span class="text-danger">*</span></label>
                        <input type="text" class="form-control" id="group-name" name="name" maxlength="150" required />
                    </div>
                    <div class="mb-4">
                        <label for="group-description" class="form-label">Keterangan</label>
                        <textarea class="form-control" id="group-description" name="description" rows="2" maxlength="1000"></textarea>
                    </div>
                    <input type="hidden" name="is_active" value="0" />
                    <div class="form-check form-switch">
                        <input class="form-check-input" type="checkbox" id="group-active" name="is_active" value="1" checked />
                        <label class="form-check-label" for="group-active">Aktif</label>
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
    <script src="{{ asset_v('js/pages/outlet-groups.js') }}"></script>
@endpush
