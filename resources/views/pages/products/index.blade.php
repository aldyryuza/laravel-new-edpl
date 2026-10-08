@extends('layouts.app', ['vendors' => ['datatables', 'sweetalert2']])

@section('title', 'Product')

@section('content')
    <x-page-header title="Product" :breadcrumbs="[['label' => 'Master Produk & Distributor']]">
        <x-slot:actions>
            {{-- Import membuat sekaligus memperbarui data: butuh hak create + update. --}}
            @if (auth()->user()->can('products.create') && auth()->user()->can('products.update'))
                <button type="button" class="btn btn-label-success" data-bs-toggle="modal" data-bs-target="#excel-import-modal">
                    <i class="icon-base bx bx-upload me-1"></i> Import Excel
                </button>
            @endif
            @can('products.create')
                <button type="button" class="btn btn-primary" id="btn-add-product">
                    <i class="icon-base bx bx-plus me-1"></i> Tambah Product
                </button>
            @endcan
        </x-slot:actions>
    </x-page-header>

    {{-- Flag permission hanya untuk UX (tampil/sembunyi tombol); server tetap mengecek ulang. --}}
    <div class="card" id="products-page" data-can-update="{{ (int) auth()->user()->can('products.update') }}"
        data-can-delete="{{ (int) auth()->user()->can('products.delete') }}">
        <div class="card-body pb-0">
            <div class="row g-4">
                <div class="col-md-4">
                    <label for="filter-volume" class="form-label">Volume</label>
                    <select class="form-select" id="filter-volume" data-filter="volume">
                        <option value="">Semua volume</option>
                        @foreach ($volumes as $volume)
                            <option value="{{ $volume }}">{{ rtrim(rtrim($volume, '0'), '.') }} ml</option>
                        @endforeach
                    </select>
                </div>
                <div class="col-md-4">
                    <label for="filter-branded" class="form-label">Branded</label>
                    <select class="form-select" id="filter-branded" data-filter="is_branded">
                        <option value="">Semua</option>
                        <option value="1">Branded</option>
                        <option value="0">Non-branded</option>
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
            <table class="table" id="products-table">
                <thead>
                    <tr>
                        <th>Kode</th>
                        <th>Nama</th>
                        <th>Volume</th>
                        <th>Group</th>
                        <th>Branded</th>
                        <th>NetSuite ID</th>
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
    <div class="modal fade" id="product-modal" tabindex="-1" aria-labelledby="product-modal-title" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered">
            <form class="modal-content" id="product-form" novalidate>
                <div class="modal-header">
                    <h5 class="modal-title" id="product-modal-title">Tambah Product</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Tutup"></button>
                </div>
                <div class="modal-body">
                    <div class="row g-4">
                        <div class="col-md-5">
                            <label for="product-code" class="form-label">Kode <span class="text-danger">*</span></label>
                            <input type="text" class="form-control" id="product-code" name="code" maxlength="20" required />
                        </div>
                        <div class="col-md-7">
                            <label for="product-volume" class="form-label">Volume (ml)</label>
                            <input type="number" class="form-control" id="product-volume" name="volume" min="0" step="0.0001" />
                        </div>
                        <div class="col-12">
                            <label for="product-group" class="form-label">Group Product</label>
                            <select class="form-select" id="product-group" name="product_group">
                                <option value="">Ikut volume (1L/500/250/100/25 ml)</option>
                                @foreach ($productGroups as $group)
                                    <option value="{{ $group }}">{{ $group }}</option>
                                @endforeach
                            </select>
                            <div class="form-text">Untuk product non-cairan (mis. IV Catheter). Menentukan group pilihan product di form DPL.</div>
                        </div>
                        <div class="col-12">
                            <label for="product-name" class="form-label">Nama <span class="text-danger">*</span></label>
                            <input type="text" class="form-control" id="product-name" name="name" maxlength="255" required />
                        </div>
                        <div class="col-12">
                            <label for="product-netsuite" class="form-label">NetSuite ID</label>
                            <input type="text" class="form-control" id="product-netsuite" name="netsuite_id" maxlength="255" />
                        </div>
                        <div class="col-md-6">
                            <label for="product-calculator-limit" class="form-label">Batas Kalkulator DPL Khusus (%)</label>
                            <input type="number" class="form-control" id="product-calculator-limit" name="calculator_limit" min="0" step="0.0001" />
                            <div class="form-text">Kosong = ikut batas team.</div>
                        </div>
                        <div class="col-md-6 d-flex align-items-center">
                            <input type="hidden" name="is_team_b_product" value="0" />
                            <div class="form-check form-switch">
                                <input class="form-check-input" type="checkbox" id="product-team-b" name="is_team_b_product" value="1" />
                                <label class="form-check-label" for="product-team-b">Produk tim B (dijual tim A memakai batas Tim B)</label>
                            </div>
                        </div>
                        <div class="col-12 d-flex gap-6">
                            <input type="hidden" name="is_branded" value="0" />
                            <div class="form-check form-switch">
                                <input class="form-check-input" type="checkbox" id="product-branded" name="is_branded" value="1" />
                                <label class="form-check-label" for="product-branded">Branded</label>
                            </div>
                            <input type="hidden" name="is_active" value="0" />
                            <div class="form-check form-switch">
                                <input class="form-check-input" type="checkbox" id="product-active" name="is_active" value="1" checked />
                                <label class="form-check-label" for="product-active">Aktif</label>
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

    @if (auth()->user()->can('products.create') && auth()->user()->can('products.update'))
        <x-excel-import-modal title="Import Product dari Excel" upload-path="api/products/import"
            :template-url="route('products.import-template')" columns="kode, nama, volume, group_product, branded, netsuite_id, aktif" />
    @endif
@endpush

@push('scripts')
    <script src="{{ asset_v('js/excel-import.js') }}"></script>
    <script src="{{ asset_v('js/pages/products.js') }}"></script>
@endpush
