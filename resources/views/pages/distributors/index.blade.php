@extends('layouts.app', ['vendors' => ['datatables', 'select2', 'sweetalert2', 'leaflet']])

@section('title', 'Distributor')

@section('content')
    <x-page-header title="Distributor" :breadcrumbs="[['label' => 'Master Produk & Distributor']]">
        <x-slot:actions>
            {{-- Import membuat sekaligus memperbarui data: butuh hak create + update dan wilayah Nasional. --}}
            @if ($canImport && auth()->user()->can('distributors.create') && auth()->user()->can('distributors.update'))
                <button type="button" class="btn btn-label-success" data-bs-toggle="modal" data-bs-target="#excel-import-modal">
                    <i class="icon-base bx bx-upload me-1"></i> Import Excel
                </button>
            @endif
            {{-- Import margin hanya memperbarui distributor yang sudah ada: butuh hak update dan wilayah Nasional. --}}
            @if ($canImport && auth()->user()->can('distributors.update'))
                <button type="button" class="btn btn-label-success" data-bs-toggle="modal" data-bs-target="#margin-import-modal">
                    <i class="icon-base bx bx-upload me-1"></i> Import Margin
                </button>
            @endif
            @can('distributors.create')
                <button type="button" class="btn btn-primary" id="btn-add-distributor">
                    <i class="icon-base bx bx-plus me-1"></i> Tambah Distributor
                </button>
            @endcan
        </x-slot:actions>
    </x-page-header>

    {{-- Flag permission hanya untuk UX (tampil/sembunyi tombol); server tetap mengecek ulang. --}}
    <div class="card" id="distributors-page" data-can-update="{{ (int) auth()->user()->can('distributors.update') }}"
        data-can-delete="{{ (int) auth()->user()->can('distributors.delete') }}">
        <div class="card-body pb-0">
            <div class="row g-4">
                <div class="col-md-4">
                    <label for="filter-region" class="form-label">Region</label>
                    <select class="form-select" id="filter-region" data-filter="region_id">
                        <option value="">Semua region</option>
                        @foreach ($regions as $region)
                            <option value="{{ $region->id }}">{{ $region->name }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="col-md-4">
                    <label for="filter-branch" class="form-label">Jenis</label>
                    <select class="form-select" id="filter-branch" data-filter="is_branch">
                        <option value="">Semua</option>
                        <option value="0">Pusat</option>
                        <option value="1">Cabang</option>
                    </select>
                </div>
                <div class="col-md-4">
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
            <table class="table" id="distributors-table">
                <thead>
                    <tr>
                        <th>Kode</th>
                        <th>Nama</th>
                        <th>Kota</th>
                        <th>Induk</th>
                        <th>Region</th>
                        <th>Margin A / B</th>
                        <th>Cabang</th>
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
    <div class="modal fade" id="distributor-modal" tabindex="-1" aria-labelledby="distributor-modal-title" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered modal-lg">
            <form class="modal-content" id="distributor-form" novalidate>
                <div class="modal-header">
                    <h5 class="modal-title" id="distributor-modal-title">Tambah Distributor</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Tutup"></button>
                </div>
                <div class="modal-body">
                    <div class="row g-4">
                        <div class="col-md-4">
                            <label for="distributor-code" class="form-label">Kode <span class="text-danger">*</span></label>
                            <input type="text" class="form-control" id="distributor-code" name="code" maxlength="30" required />
                        </div>
                        <div class="col-md-8">
                            <label for="distributor-name" class="form-label">Nama <span class="text-danger">*</span></label>
                            <input type="text" class="form-control" id="distributor-name" name="name" maxlength="255" required />
                        </div>
                        <div class="col-md-6">
                            <label for="distributor-city" class="form-label">Kota</label>
                            <select class="form-select" id="distributor-city" name="city_id">
                                <option value="">Tanpa kota</option>
                                @foreach ($cities as $city)
                                    <option value="{{ $city->id }}">{{ $city->name }} ({{ $city->code }}) - {{ $city->province?->name }}{{ $city->is_active ? '' : ' - nonaktif' }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div class="col-md-6">
                            <label for="distributor-group" class="form-label">Grup Distributor</label>
                            <input type="text" class="form-control" id="distributor-group" name="group_code" maxlength="50" />
                        </div>
                        <div class="col-md-6">
                            <label for="distributor-parent" class="form-label">Induk</label>
                            {{-- Opsi dimuat via AJAX (api/distributors/options) saat mengetik. --}}
                            <select class="form-select" id="distributor-parent" name="parent_distributor_id">
                                <option value="">Tanpa induk</option>
                            </select>
                            <div class="form-text">Isi untuk cabang; kosongkan untuk distributor pusat.</div>
                        </div>
                        <div class="col-md-6">
                            <label for="distributor-netsuite" class="form-label">NetSuite ID</label>
                            <input type="text" class="form-control" id="distributor-netsuite" name="netsuite_id" maxlength="255" />
                        </div>
                        <div class="col-12">
                            <label for="distributor-address" class="form-label">Alamat</label>
                            <textarea class="form-control" id="distributor-address" name="address" rows="2" maxlength="2000"></textarea>
                        </div>
                        <div class="col-md-4">
                            <label for="distributor-phone" class="form-label">Telepon</label>
                            <input type="text" class="form-control" id="distributor-phone" name="phone" maxlength="50" />
                        </div>
                        <div class="col-md-4">
                            <label for="distributor-fax" class="form-label">Fax</label>
                            <input type="text" class="form-control" id="distributor-fax" name="fax" maxlength="50" />
                        </div>
                        <div class="col-md-4">
                            <label for="distributor-npwp" class="form-label">NPWP</label>
                            <input type="text" class="form-control" id="distributor-npwp" name="npwp" maxlength="30" />
                        </div>
                        <div class="col-12">
                            <label for="distributor-email" class="form-label">Email</label>
                            <input type="email" class="form-control" id="distributor-email" name="email" maxlength="255" />
                        </div>
                        <div class="col-12">
                            <label for="distributor-regions" class="form-label">Region</label>
                            <select class="form-select" id="distributor-regions" name="region_ids[]" multiple>
                                @foreach ($regions as $region)
                                    <option value="{{ $region->id }}">{{ $region->name }} ({{ $region->code }}){{ $region->is_active ? '' : ' - nonaktif' }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div class="col-md-4">
                            <label for="distributor-latitude" class="form-label">Latitude</label>
                            <input type="number" class="form-control" id="distributor-latitude" name="latitude" step="0.0000001" min="-90" max="90" />
                        </div>
                        <div class="col-md-4">
                            <label for="distributor-longitude" class="form-label">Longitude</label>
                            <input type="number" class="form-control" id="distributor-longitude" name="longitude" step="0.0000001" min="-180" max="180" />
                        </div>
                        <div class="col-md-4">
                            <label for="distributor-radius" class="form-label">Radius Meeting (meter)</label>
                            <input type="number" class="form-control" id="distributor-radius" name="meeting_radius_meters" min="0" max="100000" step="1" />
                        </div>
                        <div class="col-md-4">
                            <label for="distributor-margin" class="form-label">Margin DPL (%)</label>
                            <input type="number" class="form-control" id="distributor-margin" name="margin" min="0" step="0.0001" />
                        </div>
                        <div class="col-md-4">
                            <label for="distributor-margin-team-b" class="form-label">Margin Tim B (%)</label>
                            <input type="number" class="form-control" id="distributor-margin-team-b" name="margin_team_b" min="0" step="0.0001" />
                            <div class="form-text">Kosong = pakai Margin DPL.</div>
                        </div>
                        <div class="col-md-4 d-flex align-items-center">
                            <input type="hidden" name="is_special_case_margin" value="0" />
                            <div class="form-check form-switch">
                                <input class="form-check-input" type="checkbox" id="distributor-special-margin" name="is_special_case_margin" value="1" />
                                <label class="form-check-label" for="distributor-special-margin">Punya margin khusus per outlet</label>
                            </div>
                        </div>
                        <div class="col-12">
                            <x-location-picker prefix="distributor" label="Lokasi (monthly meeting CRM)" height="260" />
                        </div>
                        <div class="col-12 d-flex gap-6">
                            <input type="hidden" name="is_branch" value="0" />
                            <div class="form-check form-switch">
                                <input class="form-check-input" type="checkbox" id="distributor-branch" name="is_branch" value="1" />
                                <label class="form-check-label" for="distributor-branch">Cabang</label>
                            </div>
                            <input type="hidden" name="is_active" value="0" />
                            <div class="form-check form-switch">
                                <input class="form-check-input" type="checkbox" id="distributor-active" name="is_active" value="1" checked />
                                <label class="form-check-label" for="distributor-active">Aktif</label>
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

    @if ($canImport && auth()->user()->can('distributors.create') && auth()->user()->can('distributors.update'))
        <x-excel-import-modal title="Import Distributor dari Excel" upload-path="api/distributors/import"
            :template-url="route('distributors.import-template')"
            columns="kode, nama, grup_distributor, kode_kota, alamat, telepon, fax, email, npwp, latitude, longitude, radius_meter, cabang, kode_induk, kode_region, netsuite_id, aktif" />
    @endif
    @if ($canImport && auth()->user()->can('distributors.update'))
        <x-excel-import-modal id="margin-import" title="Import Margin Distributor dari Excel" upload-path="api/distributors/import-margin"
            :template-url="route('distributors.import-margin-template')" :update-only="true"
            columns="kode_distributor, margin, margin_tim_b, margin_khusus_outlet" />
    @endif
@endpush

@push('scripts')
    <script src="{{ asset_v('js/excel-import.js') }}"></script>
    <script src="{{ asset_v('js/location-picker.js') }}"></script>
    <script src="{{ asset_v('js/pages/distributors.js') }}"></script>
@endpush
