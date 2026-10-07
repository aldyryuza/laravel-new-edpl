@extends('layouts.app', ['vendors' => ['datatables', 'select2', 'sweetalert2', 'leaflet']])

@section('title', 'Outlet')

@section('content')
    <x-page-header title="Outlet" :breadcrumbs="[['label' => 'Master Outlet']]">
        <x-slot:actions>
            {{-- Import membuat sekaligus memperbarui data: butuh hak create + update dan wilayah Nasional. --}}
            @if ($canImport && auth()->user()->can('outlets.create') && auth()->user()->can('outlets.update'))
                <button type="button" class="btn btn-label-success" data-bs-toggle="modal" data-bs-target="#excel-import-modal">
                    <i class="icon-base bx bx-upload me-1"></i> Import Excel
                </button>
            @endif
            @can('outlets.create')
                <button type="button" class="btn btn-primary" id="btn-add-outlet">
                    <i class="icon-base bx bx-plus me-1"></i> Tambah Outlet
                </button>
            @endcan
        </x-slot:actions>
    </x-page-header>

    {{-- Flag permission hanya untuk UX (tampil/sembunyi tombol); server tetap mengecek ulang. --}}
    <div class="card" id="outlets-page" data-can-update="{{ (int) auth()->user()->can('outlets.update') }}"
        data-can-delete="{{ (int) auth()->user()->can('outlets.delete') }}">
        <div class="card-body pb-0">
            <div class="row g-4">
                <div class="col-md-4 col-lg">
                    <label for="filter-region" class="form-label">Region</label>
                    <select class="form-select" id="filter-region" data-filter="region_id">
                        <option value="">Semua region</option>
                        @foreach ($regions as $region)
                            <option value="{{ $region->id }}">{{ $region->name }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="col-md-4 col-lg">
                    <label for="filter-category" class="form-label">Kategori</label>
                    <select class="form-select" id="filter-category" data-filter="outlet_category_id">
                        <option value="">Semua kategori</option>
                        @foreach ($categories as $category)
                            <option value="{{ $category->id }}">{{ $category->name }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="col-md-4 col-lg">
                    <label for="filter-ownership" class="form-label">Kepemilikan</label>
                    <select class="form-select" id="filter-ownership" data-filter="ownership_type_id">
                        <option value="">Semua kepemilikan</option>
                        @foreach ($ownershipTypes as $ownershipType)
                            <option value="{{ $ownershipType->id }}">{{ $ownershipType->name }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="col-md-6 col-lg">
                    <label for="filter-group" class="form-label">Group RS</label>
                    <select class="form-select" id="filter-group" data-filter="outlet_group_id">
                        <option value="">Semua group</option>
                        @foreach ($groups as $group)
                            <option value="{{ $group->id }}" @selected((int) request('outlet_group_id') === $group->id)>{{ $group->name }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="col-md-6 col-lg">
                    <label for="filter-status" class="form-label">Status</label>
                    <select class="form-select" id="filter-status" data-filter="is_active">
                        <option value="">Semua status</option>
                        <option value="1">Aktif</option>
                        <option value="0">Nonaktif</option>
                    </select>
                </div>
            </div>
        </div>
        <div class="card-datatable table-responsive">
            <table class="table" id="outlets-table">
                <thead>
                    <tr>
                        <th>Kode</th>
                        <th>Nama</th>
                        <th>Area / Region</th>
                        <th>Kategori</th>
                        <th>Kepemilikan</th>
                        <th>Group RS</th>
                        <th>Bed</th>
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
    <div class="modal fade" id="outlet-modal" tabindex="-1" aria-labelledby="outlet-modal-title" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered modal-lg">
            <form class="modal-content" id="outlet-form" novalidate>
                <div class="modal-header">
                    <h5 class="modal-title" id="outlet-modal-title">Tambah Outlet</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Tutup"></button>
                </div>
                <div class="modal-body">
                    <div class="row g-4">
                        <div class="col-md-4">
                            <label for="outlet-code" class="form-label">Kode <span class="text-danger">*</span></label>
                            <input type="text" class="form-control" id="outlet-code" name="code" maxlength="30" required />
                        </div>
                        <div class="col-md-8">
                            <label for="outlet-name" class="form-label">Nama <span class="text-danger">*</span></label>
                            <input type="text" class="form-control" id="outlet-name" name="name" maxlength="255" required />
                        </div>
                        <div class="col-12">
                            <label for="outlet-address" class="form-label">Alamat</label>
                            <textarea class="form-control" id="outlet-address" name="address" rows="2" maxlength="2000"></textarea>
                        </div>
                        <div class="col-md-6">
                            <label for="outlet-city" class="form-label">Kota/Kabupaten</label>
                            <select class="form-select" id="outlet-city" name="city_id">
                                <option value="">Tanpa kota</option>
                                @foreach ($cities as $city)
                                    <option value="{{ $city->id }}">{{ $city->name }} ({{ $city->code }}) - {{ $city->province?->name }}{{ $city->is_active ? '' : ' - nonaktif' }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div class="col-md-6">
                            <label for="outlet-area" class="form-label">Area</label>
                            <select class="form-select" id="outlet-area" name="area_id">
                                <option value="">Tanpa area</option>
                                @foreach ($areas as $area)
                                    <option value="{{ $area->id }}">
                                        {{ $area->name }} ({{ $area->code }}) - {{ $area->region?->name }}{{ $area->is_active ? '' : ' - nonaktif' }}
                                    </option>
                                @endforeach
                            </select>
                            <div class="form-text">Area penjualan: menentukan region/zona untuk resolusi approver RM/NSM.</div>
                        </div>
                        <div class="col-md-6">
                            <label for="outlet-group" class="form-label">Group RS</label>
                            <select class="form-select" id="outlet-group" name="outlet_group_id">
                                <option value="">Tanpa group</option>
                                @foreach ($groups as $group)
                                    <option value="{{ $group->id }}">{{ $group->name }}{{ $group->is_active ? '' : ' - nonaktif' }}</option>
                                @endforeach
                            </select>
                            <div class="form-text">Mengganti group menutup keanggotaan lama per hari ini (histori tersimpan).</div>
                        </div>
                        <div class="col-md-6">
                            <label for="outlet-category" class="form-label">Kategori</label>
                            <select class="form-select" id="outlet-category" name="outlet_category_id">
                                <option value="">Tanpa kategori</option>
                                @foreach ($categories as $category)
                                    <option value="{{ $category->id }}">{{ $category->name }}{{ $category->is_active ? '' : ' - nonaktif' }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div class="col-md-6">
                            <label for="outlet-ownership" class="form-label">Kepemilikan</label>
                            <select class="form-select" id="outlet-ownership" name="ownership_type_id">
                                <option value="">Tanpa kepemilikan</option>
                                @foreach ($ownershipTypes as $ownershipType)
                                    <option value="{{ $ownershipType->id }}">{{ $ownershipType->name }}{{ $ownershipType->is_active ? '' : ' - nonaktif' }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div class="col-md-3">
                            <label for="outlet-bed" class="form-label">Jumlah Bed</label>
                            <input type="number" class="form-control" id="outlet-bed" name="bed_count" min="0" step="1" />
                        </div>
                        <div class="col-md-3">
                            <label for="outlet-crm" class="form-label">CRM ID</label>
                            <input type="number" class="form-control" id="outlet-crm" name="crm_id" min="1" step="1" />
                        </div>
                        <div class="col-md-3">
                            <label for="outlet-latitude" class="form-label">Latitude</label>
                            <input type="number" class="form-control" id="outlet-latitude" name="latitude" step="0.0000001" min="-90" max="90" />
                        </div>
                        <div class="col-md-3">
                            <label for="outlet-longitude" class="form-label">Longitude</label>
                            <input type="number" class="form-control" id="outlet-longitude" name="longitude" step="0.0000001" min="-180" max="180" />
                        </div>
                        <div class="col-12">
                            <x-location-picker prefix="outlet" label="Posisi Outlet" />
                        </div>
                        <div class="col-12">
                            <input type="hidden" name="is_active" value="0" />
                            <div class="form-check form-switch">
                                <input class="form-check-input" type="checkbox" id="outlet-active" name="is_active" value="1" checked />
                                <label class="form-check-label" for="outlet-active">Aktif</label>
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

    @if ($canImport && auth()->user()->can('outlets.create') && auth()->user()->can('outlets.update'))
        <x-excel-import-modal title="Import Outlet dari Excel" upload-path="api/outlets/import"
            :template-url="route('outlets.import-template')"
            columns="kode, nama, alamat, kode_kota, kode_area, kode_kategori, kode_kepemilikan, jumlah_bed, kode_grup, crm_id, latitude, longitude, aktif" />
    @endif
@endpush

@push('scripts')
    <script src="{{ asset_v('js/excel-import.js') }}"></script>
    <script src="{{ asset_v('js/location-picker.js') }}"></script>
    <script src="{{ asset_v('js/pages/outlets.js') }}"></script>
@endpush
