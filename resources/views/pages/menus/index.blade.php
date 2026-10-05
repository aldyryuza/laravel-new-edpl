@extends('layouts.app', ['vendors' => ['sweetalert2']])

@section('title', 'Menu')

@section('content')
    <x-page-header title="Menu" :breadcrumbs="[['label' => 'Pengaturan']]"
        subtitle="Kode menu dipakai sebagai kunci hak akses, contoh: users -> users.read.">
        <x-slot:actions>
            @can('menus.create')
                <button type="button" class="btn btn-primary" id="btn-add-menu">
                    <i class="icon-base bx bx-plus me-1"></i> Tambah Menu
                </button>
            @endcan
        </x-slot:actions>
    </x-page-header>

    {{-- Flag permission hanya untuk UX (tampil/sembunyi tombol); server tetap mengecek ulang. --}}
    <div class="card" id="menus-page" data-can-update="{{ (int) auth()->user()->can('menus.update') }}"
        data-can-delete="{{ (int) auth()->user()->can('menus.delete') }}">
        <div class="table-responsive">
            <table class="table table-hover mb-0" id="menus-table">
                <thead>
                    <tr>
                        <th>Judul</th>
                        <th>Kode</th>
                        <th>Route</th>
                        <th class="text-center">Urutan</th>
                        <th class="text-center">Status</th>
                        <th class="text-end">Aksi</th>
                    </tr>
                </thead>
                <tbody>
                    <tr>
                        <td colspan="6" class="text-center text-body-secondary py-6">Memuat...</td>
                    </tr>
                </tbody>
            </table>
        </div>
    </div>
@endsection

@push('modals')
    <div class="modal fade" id="menu-modal" tabindex="-1" aria-labelledby="menu-modal-title" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered">
            <form class="modal-content" id="menu-form" novalidate>
                <div class="modal-header">
                    <h5 class="modal-title" id="menu-modal-title">Tambah Menu</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Tutup"></button>
                </div>
                <div class="modal-body">
                    <div class="mb-4">
                        <label for="menu-parent" class="form-label">Parent</label>
                        <select class="form-select" id="menu-parent" name="parent_id"></select>
                    </div>
                    <div class="row g-4 mb-4">
                        <div class="col-sm-6">
                            <label for="menu-title" class="form-label">Judul <span class="text-danger">*</span></label>
                            <input type="text" class="form-control" id="menu-title" name="title" maxlength="100" required />
                        </div>
                        <div class="col-sm-6">
                            <label for="menu-code" class="form-label">Kode <span class="text-danger">*</span></label>
                            <input type="text" class="form-control" id="menu-code" name="code" maxlength="50" required />
                            <div class="form-text">Huruf, angka, - dan _. Tanpa spasi/titik.</div>
                        </div>
                    </div>
                    <div class="row g-4 mb-4">
                        <div class="col-sm-6">
                            <label for="menu-route" class="form-label">Nama Route</label>
                            <input type="text" class="form-control" id="menu-route" name="route" maxlength="150"
                                placeholder="users.index" />
                            <div class="form-text">Kosongkan untuk menu grup.</div>
                        </div>
                        <div class="col-sm-6">
                            <label for="menu-icon" class="form-label">Icon</label>
                            <input type="text" class="form-control" id="menu-icon" name="icon" maxlength="100"
                                placeholder="bx bx-user" />
                        </div>
                    </div>
                    <div class="row g-4">
                        <div class="col-sm-6">
                            <label for="menu-sort" class="form-label">Urutan <span class="text-danger">*</span></label>
                            <input type="number" class="form-control" id="menu-sort" name="sort_order" min="0" value="0"
                                required />
                        </div>
                        <div class="col-sm-6 d-flex align-items-end">
                            <input type="hidden" name="is_active" value="0" />
                            <div class="form-check form-switch mb-2">
                                <input class="form-check-input" type="checkbox" id="menu-active" name="is_active"
                                    value="1" checked />
                                <label class="form-check-label" for="menu-active">Aktif</label>
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
    <script src="{{ asset_v('js/pages/menus.js') }}"></script>
@endpush
