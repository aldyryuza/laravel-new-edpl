@extends('layouts.app', ['vendors' => ['datatables', 'select2', 'sweetalert2']])

@section('title', 'Harga Product')

@section('content')
    <x-page-header title="Harga Product" subtitle="Satu harga per product (tidak dibedakan per region) beserta periode berlakunya."
        :breadcrumbs="[['label' => 'Komersial DPL']]">
        <x-slot:actions>
            {{-- Import membuat sekaligus memperbarui data: butuh hak create + update. --}}
            @if (auth()->user()->can('product-prices.create') && auth()->user()->can('product-prices.update'))
                <button type="button" class="btn btn-label-success" data-bs-toggle="modal" data-bs-target="#excel-import-modal">
                    <i class="icon-base bx bx-upload me-1"></i> Import Excel
                </button>
            @endif
            @can('product-prices.create')
                <button type="button" class="btn btn-primary" id="btn-add-price">
                    <i class="icon-base bx bx-plus me-1"></i> Tambah Harga
                </button>
            @endcan
        </x-slot:actions>
    </x-page-header>

    {{-- Flag permission hanya untuk UX (tampil/sembunyi tombol); server tetap mengecek ulang. --}}
    <div class="card" id="prices-page" data-can-update="{{ (int) auth()->user()->can('product-prices.update') }}"
        data-can-delete="{{ (int) auth()->user()->can('product-prices.delete') }}">
        <div class="card-body pb-0">
            <div class="row g-4 align-items-end">
                <div class="col-md-4">
                    <label for="filter-status" class="form-label">Status</label>
                    <select class="form-select" id="filter-status" data-filter="is_active">
                        <option value="">Semua status</option>
                        <option value="1">Aktif</option>
                        <option value="0">Nonaktif</option>
                    </select>
                </div>
                <div class="col-md-4">
                    <div class="form-check form-switch mb-2">
                        <input class="form-check-input" type="checkbox" id="filter-effective" />
                        <label class="form-check-label" for="filter-effective">Hanya yang berlaku hari ini</label>
                    </div>
                </div>
            </div>
        </div>
        <div class="card-datatable table-responsive">
            <table class="table" id="prices-table">
                <thead>
                    <tr>
                        <th>Product</th>
                        <th class="text-end">Harga</th>
                        <th class="text-end">Harga Reguler</th>
                        <th>Berlaku Mulai</th>
                        <th>Berlaku Sampai</th>
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
    <div class="modal fade" id="price-modal" tabindex="-1" aria-labelledby="price-modal-title" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered modal-lg">
            <form class="modal-content" id="price-form" novalidate>
                <div class="modal-header">
                    <h5 class="modal-title" id="price-modal-title">Tambah Harga</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Tutup"></button>
                </div>
                <div class="modal-body">
                    <div class="row g-4">
                        <div class="col-12">
                            <label for="price-product" class="form-label">Product <span class="text-danger">*</span></label>
                            <select class="form-select" id="price-product" name="product_id" required>
                                <option value="">Pilih product</option>
                                @foreach ($products as $product)
                                    <option value="{{ $product->id }}">{{ $product->name }} ({{ $product->code }}){{ $product->is_active ? '' : ' - nonaktif' }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div class="col-md-6">
                            <label for="price-amount" class="form-label">Harga <span class="text-danger">*</span></label>
                            <input type="number" class="form-control" id="price-amount" name="price" min="0" step="0.0001" required />
                        </div>
                        <div class="col-md-6">
                            <label for="price-regular" class="form-label">Harga Reguler</label>
                            <input type="number" class="form-control" id="price-regular" name="regular_price" min="0" step="0.0001" />
                        </div>
                        <div class="col-md-6">
                            <label for="price-from" class="form-label">Berlaku Mulai</label>
                            <input type="date" class="form-control" id="price-from" name="valid_from" />
                        </div>
                        <div class="col-md-6">
                            <label for="price-until" class="form-label">Berlaku Sampai</label>
                            <input type="date" class="form-control" id="price-until" name="valid_until" />
                        </div>
                        <div class="col-12">
                            <input type="hidden" name="is_active" value="0" />
                            <div class="form-check form-switch">
                                <input class="form-check-input" type="checkbox" id="price-active" name="is_active" value="1" checked />
                                <label class="form-check-label" for="price-active">Aktif</label>
                            </div>
                            <div class="form-text">Untuk perubahan harga, sebaiknya isi "Berlaku Sampai" pada harga lama dan buat harga baru agar histori tersimpan.</div>
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

    @if (auth()->user()->can('product-prices.create') && auth()->user()->can('product-prices.update'))
        <x-excel-import-modal title="Import Harga Product dari Excel" upload-path="api/dpl/product-prices/import"
            :template-url="route('dpl.product-prices.import-template')"
            columns="kode_product, harga, harga_reguler, berlaku_mulai, berlaku_sampai, aktif" />
    @endif
@endpush

@push('scripts')
    <script src="{{ asset_v('js/excel-import.js') }}"></script>
    <script src="{{ asset_v('js/pages/dpl/product-prices.js') }}"></script>
@endpush
