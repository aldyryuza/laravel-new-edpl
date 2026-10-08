@extends('layouts.app', ['vendors' => ['datatables', 'sweetalert2']])

@section('title', 'Kota/Kabupaten')

@section('content')
    <x-page-header title="Kota/Kabupaten" :breadcrumbs="[['label' => 'Master Organisasi']]">
        <x-slot:actions>
            {{-- Import membuat sekaligus memperbarui data: butuh hak create + update. --}}
            @if (auth()->user()->can('cities.create') && auth()->user()->can('cities.update'))
                <button type="button" class="btn btn-label-success" data-bs-toggle="modal" data-bs-target="#excel-import-modal">
                    <i class="icon-base bx bx-upload me-1"></i> Import Excel
                </button>
            @endif
            @can('cities.create')
                <button type="button" class="btn btn-primary" id="btn-add-city">
                    <i class="icon-base bx bx-plus me-1"></i> Tambah Kota
                </button>
            @endcan
        </x-slot:actions>
    </x-page-header>

    {{-- Flag permission hanya untuk UX (tampil/sembunyi tombol); server tetap mengecek ulang. --}}
    <div class="card" id="cities-page" data-can-update="{{ (int) auth()->user()->can('cities.update') }}"
        data-can-delete="{{ (int) auth()->user()->can('cities.delete') }}">
        <div class="card-body pb-0">
            <div class="row">
                <div class="col-md-4">
                    <label for="filter-province" class="form-label">Filter Provinsi</label>
                    <select class="form-select" id="filter-province">
                        <option value="">Semua provinsi</option>
                        @foreach ($provinces as $province)
                            <option value="{{ $province->id }}">{{ $province->name }} ({{ $province->code }})</option>
                        @endforeach
                    </select>
                </div>
            </div>
        </div>
        <div class="card-datatable table-responsive">
            <table class="table" id="cities-table">
                <thead>
                    <tr>
                        <th>Kode</th>
                        <th>Nama</th>
                        <th>Provinsi</th>
                        <th class="text-end">Logistic Fee DPL</th>
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
    <div class="modal fade" id="city-modal" tabindex="-1" aria-labelledby="city-modal-title" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered">
            <form class="modal-content" id="city-form" novalidate>
                <div class="modal-header">
                    <h5 class="modal-title" id="city-modal-title">Tambah Kota</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Tutup"></button>
                </div>
                <div class="modal-body">
                    <div class="mb-4">
                        <label for="city-code" class="form-label">Kode <span class="text-danger">*</span></label>
                        <input type="text" class="form-control" id="city-code" name="code" maxlength="10" required />
                    </div>
                    <div class="mb-4">
                        <label for="city-name" class="form-label">Nama <span class="text-danger">*</span></label>
                        <input type="text" class="form-control" id="city-name" name="name" maxlength="100" required />
                    </div>
                    <div class="mb-4">
                        <label for="city-province" class="form-label">Provinsi <span class="text-danger">*</span></label>
                        <select class="form-select" id="city-province" name="province_id" required>
                            <option value="">Pilih provinsi</option>
                            @foreach ($provinces as $province)
                                <option value="{{ $province->id }}">
                                    {{ $province->name }} ({{ $province->code }}){{ $province->is_active ? '' : ' - nonaktif' }}
                                </option>
                            @endforeach
                        </select>
                    </div>
                    <div class="mb-4">
                        <label for="city-logistic-fee" class="form-label">Logistic Fee DPL (%)</label>
                        <input type="number" class="form-control" id="city-logistic-fee" name="logistic_fee" min="0" step="0.0001" />
                        <div class="form-text">Dasar logistic fee item DPL Direct untuk distributor di kota ini.</div>
                    </div>
                    <input type="hidden" name="is_active" value="0" />
                    <div class="form-check form-switch">
                        <input class="form-check-input" type="checkbox" id="city-active" name="is_active" value="1" checked />
                        <label class="form-check-label" for="city-active">Aktif</label>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-label-secondary" data-bs-dismiss="modal">Batal</button>
                    <button type="submit" class="btn btn-primary">Simpan</button>
                </div>
            </form>
        </div>
    </div>
    @if (auth()->user()->can('cities.create') && auth()->user()->can('cities.update'))
        <x-excel-import-modal title="Import Kota dari Excel" upload-path="api/cities/import"
            :template-url="route('cities.import-template')" columns="kode, nama, kode_provinsi, aktif" />
    @endif
@endpush

@push('scripts')
    <script src="{{ asset_v('js/excel-import.js') }}"></script>
    <script src="{{ asset_v('js/pages/cities.js') }}"></script>
@endpush
