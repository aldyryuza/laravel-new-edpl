@extends('layouts.app', ['vendors' => ['datatables', 'sweetalert2']])

@section('title', 'Provinsi')

@section('content')
    <x-page-header title="Provinsi" :breadcrumbs="[['label' => 'Master Organisasi']]">
        <x-slot:actions>
            {{-- Import membuat sekaligus memperbarui data: butuh hak create + update. --}}
            @if (auth()->user()->can('provinces.create') && auth()->user()->can('provinces.update'))
                <button type="button" class="btn btn-label-success" data-bs-toggle="modal" data-bs-target="#excel-import-modal">
                    <i class="icon-base bx bx-upload me-1"></i> Import Excel
                </button>
            @endif
            @can('provinces.create')
                <button type="button" class="btn btn-primary" id="btn-add-province">
                    <i class="icon-base bx bx-plus me-1"></i> Tambah Provinsi
                </button>
            @endcan
        </x-slot:actions>
    </x-page-header>

    {{-- Flag permission hanya untuk UX (tampil/sembunyi tombol); server tetap mengecek ulang. --}}
    <div class="card" id="provinces-page" data-can-update="{{ (int) auth()->user()->can('provinces.update') }}"
        data-can-delete="{{ (int) auth()->user()->can('provinces.delete') }}">
        <div class="card-datatable table-responsive">
            <table class="table" id="provinces-table">
                <thead>
                    <tr>
                        <th>Kode</th>
                        <th>Nama</th>
                        <th>Jumlah Kota</th>
                        <th>Pulau Jawa</th>
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
    <div class="modal fade" id="province-modal" tabindex="-1" aria-labelledby="province-modal-title" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered">
            <form class="modal-content" id="province-form" novalidate>
                <div class="modal-header">
                    <h5 class="modal-title" id="province-modal-title">Tambah Provinsi</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Tutup"></button>
                </div>
                <div class="modal-body">
                    <div class="mb-4">
                        <label for="province-code" class="form-label">Kode <span class="text-danger">*</span></label>
                        <input type="text" class="form-control" id="province-code" name="code" maxlength="10" required />
                    </div>
                    <div class="mb-4">
                        <label for="province-name" class="form-label">Nama <span class="text-danger">*</span></label>
                        <input type="text" class="form-control" id="province-name" name="name" maxlength="100" required />
                    </div>
                    <input type="hidden" name="is_java" value="0" />
                    <div class="form-check form-switch mb-2">
                        <input class="form-check-input" type="checkbox" id="province-java" name="is_java" value="1" />
                        <label class="form-check-label" for="province-java">Pulau Jawa (margin DPL Direct outlet Instansi)</label>
                    </div>
                    <input type="hidden" name="is_active" value="0" />
                    <div class="form-check form-switch">
                        <input class="form-check-input" type="checkbox" id="province-active" name="is_active" value="1" checked />
                        <label class="form-check-label" for="province-active">Aktif</label>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-label-secondary" data-bs-dismiss="modal">Batal</button>
                    <button type="submit" class="btn btn-primary">Simpan</button>
                </div>
            </form>
        </div>
    </div>
    @if (auth()->user()->can('provinces.create') && auth()->user()->can('provinces.update'))
        <x-excel-import-modal title="Import Provinsi dari Excel" upload-path="api/provinces/import"
            :template-url="route('provinces.import-template')" columns="kode, nama, aktif" />
    @endif
@endpush

@push('scripts')
    <script src="{{ asset_v('js/excel-import.js') }}"></script>
    <script src="{{ asset_v('js/pages/provinces.js') }}"></script>
@endpush
