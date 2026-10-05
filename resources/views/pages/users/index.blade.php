@extends('layouts.app', ['vendors' => ['datatables', 'sweetalert2']])

@section('title', 'User')

@section('content')
    <x-page-header title="User" :breadcrumbs="[['label' => 'Pengaturan']]">
        <x-slot:actions>
            @can('users.create')
                <button type="button" class="btn btn-primary" id="btn-add-user">
                    <i class="icon-base bx bx-plus me-1"></i> Tambah User
                </button>
            @endcan
        </x-slot:actions>
    </x-page-header>

    {{-- Flag permission hanya untuk UX (tampil/sembunyi tombol); server tetap mengecek ulang. --}}
    <div class="card" id="users-page" data-can-update="{{ (int) auth()->user()->can('users.update') }}"
        data-can-delete="{{ (int) auth()->user()->can('users.delete') }}">
        <div class="card-datatable table-responsive">
            <table class="table" id="users-table">
                <thead>
                    <tr>
                        <th>NIK</th>
                        <th>Nama</th>
                        <th>Email</th>
                        <th>Role</th>
                        <th class="text-end">Aksi</th>
                    </tr>
                </thead>
                <tbody></tbody>
            </table>
        </div>
    </div>
@endsection

@push('modals')
    <div class="modal fade" id="user-modal" tabindex="-1" aria-labelledby="user-modal-title" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered">
            <form class="modal-content" id="user-form" novalidate>
                <div class="modal-header">
                    <h5 class="modal-title" id="user-modal-title">Tambah User</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Tutup"></button>
                </div>
                <div class="modal-body">
                    <div class="mb-4">
                        <label for="user-nik" class="form-label">NIK <span class="text-danger">*</span></label>
                        <input type="text" class="form-control" id="user-nik" name="nik" maxlength="30" required />
                    </div>
                    <div class="mb-4">
                        <label for="user-name" class="form-label">Nama <span class="text-danger">*</span></label>
                        <input type="text" class="form-control" id="user-name" name="name" maxlength="255" required />
                    </div>
                    <div class="mb-4">
                        <label for="user-email" class="form-label">Email <span class="text-danger">*</span></label>
                        <input type="email" class="form-control" id="user-email" name="email" maxlength="255" required />
                    </div>
                    <div class="mb-4">
                        <label for="user-password" class="form-label">
                            Password <span class="text-danger" id="user-password-required">*</span>
                        </label>
                        <input type="password" class="form-control" id="user-password" name="password"
                            autocomplete="new-password" />
                        <div class="form-text d-none" id="user-password-hint">Kosongkan jika tidak ingin mengganti password.</div>
                    </div>
                    <div>
                        <label class="form-label d-block">Role</label>
                        @forelse ($roles as $role)
                            <div class="form-check form-check-inline">
                                <input class="form-check-input" type="checkbox" name="role_ids[]"
                                    id="user-role-{{ $role->id }}" value="{{ $role->id }}" />
                                <label class="form-check-label" for="user-role-{{ $role->id }}">{{ $role->name }}</label>
                            </div>
                        @empty
                            <p class="text-body-secondary mb-0">Belum ada role.</p>
                        @endforelse
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
    <script src="{{ asset_v('js/pages/users.js') }}"></script>
@endpush
