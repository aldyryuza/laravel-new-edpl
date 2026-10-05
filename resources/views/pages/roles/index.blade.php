@extends('layouts.app', ['vendors' => ['datatables', 'sweetalert2']])

@section('title', 'Role & Hak Akses')

@section('content')
    <x-page-header title="Role & Hak Akses" :breadcrumbs="[['label' => 'Pengaturan']]">
        <x-slot:actions>
            @can('roles.create')
                <button type="button" class="btn btn-primary" id="btn-add-role">
                    <i class="icon-base bx bx-plus me-1"></i> Tambah Role
                </button>
            @endcan
        </x-slot:actions>
    </x-page-header>

    {{-- Flag permission hanya untuk UX (tampil/sembunyi tombol); server tetap mengecek ulang. --}}
    <div class="card" id="roles-page" data-can-update="{{ (int) auth()->user()->can('roles.update') }}"
        data-can-delete="{{ (int) auth()->user()->can('roles.delete') }}"
        data-access-url="{{ route('roles.access', ['role' => '__ID__']) }}">
        <div class="card-datatable table-responsive">
            <table class="table" id="roles-table">
                <thead>
                    <tr>
                        <th>Nama Role</th>
                        <th>Keterangan</th>
                        <th>Jumlah User</th>
                        <th class="text-end">Aksi</th>
                    </tr>
                </thead>
                <tbody></tbody>
            </table>
        </div>
    </div>
@endsection

@push('modals')
    <div class="modal fade" id="role-modal" tabindex="-1" aria-labelledby="role-modal-title" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered">
            <form class="modal-content" id="role-form" novalidate>
                <div class="modal-header">
                    <h5 class="modal-title" id="role-modal-title">Tambah Role</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Tutup"></button>
                </div>
                <div class="modal-body">
                    <div class="mb-4">
                        <label for="role-name" class="form-label">Nama Role <span class="text-danger">*</span></label>
                        <input type="text" class="form-control" id="role-name" name="name" maxlength="100" required />
                    </div>
                    <div>
                        <label for="role-description" class="form-label">Keterangan</label>
                        <input type="text" class="form-control" id="role-description" name="description" maxlength="255" />
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
    <script src="{{ asset_v('js/pages/roles.js') }}"></script>
@endpush
