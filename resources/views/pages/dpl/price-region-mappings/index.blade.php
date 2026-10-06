{{-- Dipakai untuk region harga outlet dan distributor ($type = outlets | distributors). --}}
@extends('layouts.app', ['vendors' => ['datatables', 'select2']])

@section('title', 'Region Harga '.ucfirst($label))

@section('content')
    <x-page-header :title="'Region Harga '.ucfirst($label)" :breadcrumbs="[['label' => 'Komersial DPL']]">
        <x-slot:actions>
            @can($permission.'.update')
                <button type="button" class="btn btn-label-success" data-bs-toggle="modal" data-bs-target="#excel-import-modal">
                    <i class="icon-base bx bx-upload me-1"></i> Import Excel
                </button>
            @endcan
        </x-slot:actions>
    </x-page-header>

    {{-- Flag permission hanya untuk UX (tampil/sembunyi tombol); server tetap mengecek ulang. --}}
    <div class="card" id="mappings-page" data-type="{{ $type }}" data-table="{{ $table }}" data-label="{{ $label }}"
        data-can-update="{{ (int) auth()->user()->can($permission.'.update') }}">
        <div class="card-body pb-0">
            <div class="row">
                <div class="col-md-4">
                    <label for="filter-price-region" class="form-label">Region Harga</label>
                    <select class="form-select" id="filter-price-region">
                        <option value="">Semua</option>
                        <option value="none">Belum punya region harga</option>
                        @foreach ($priceRegions as $priceRegion)
                            <option value="{{ $priceRegion->id }}">{{ $priceRegion->name }} ({{ $priceRegion->code }})</option>
                        @endforeach
                    </select>
                </div>
            </div>
        </div>
        <div class="card-datatable table-responsive">
            <table class="table" id="mappings-table">
                <thead>
                    <tr>
                        <th>Kode</th>
                        <th>Nama {{ ucfirst($label) }}</th>
                        <th>Region Harga</th>
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
    <div class="modal fade" id="mapping-modal" tabindex="-1" aria-labelledby="mapping-modal-title" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered">
            <form class="modal-content" id="mapping-form" novalidate>
                <div class="modal-header">
                    <h5 class="modal-title" id="mapping-modal-title">Region Harga</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Tutup"></button>
                </div>
                <div class="modal-body">
                    <p class="mb-4" id="mapping-owner"></p>
                    <label for="mapping-price-region" class="form-label">Region Harga</label>
                    <select class="form-select" id="mapping-price-region" name="price_region_id">
                        <option value="">Tanpa region harga</option>
                        @foreach ($priceRegions as $priceRegion)
                            <option value="{{ $priceRegion->id }}">{{ $priceRegion->name }} ({{ $priceRegion->code }}){{ $priceRegion->is_active ? '' : ' - nonaktif' }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-label-secondary" data-bs-dismiss="modal">Batal</button>
                    <button type="submit" class="btn btn-primary">Simpan</button>
                </div>
            </form>
        </div>
    </div>

    @can($permission.'.update')
        <x-excel-import-modal :title="'Import Region Harga '.ucfirst($label).' dari Excel'" :upload-path="'api/dpl/'.$label.'-price-regions/import'"
            :template-url="route('dpl.'.$label.'-price-regions.import-template')" :columns="$codeColumn.', kode_region_harga'" />
    @endcan
@endpush

@push('scripts')
    <script src="{{ asset_v('js/excel-import.js') }}"></script>
    <script src="{{ asset_v('js/pages/dpl/price-region-mappings.js') }}"></script>
@endpush
