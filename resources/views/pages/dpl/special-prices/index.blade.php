@extends('layouts.app', ['vendors' => ['datatables', 'select2', 'sweetalert2']])

@section('title', 'Harga Khusus')

@section('content')
    <x-page-header title="Harga Khusus" subtitle="Harga khusus per jenis (mis. JKN, PADMA), opsional untuk group RS tertentu."
        :breadcrumbs="[['label' => 'Komersial DPL']]">
        <x-slot:actions>
            {{-- Import membuat sekaligus memperbarui data: butuh hak create + update. --}}
            @if (auth()->user()->can('special-prices.create') && auth()->user()->can('special-prices.update'))
                <button type="button" class="btn btn-label-success" data-bs-toggle="modal" data-bs-target="#excel-import-modal">
                    <i class="icon-base bx bx-upload me-1"></i> Import Excel
                </button>
            @endif
            @can('special-prices.create')
                <button type="button" class="btn btn-primary" id="btn-add-special">
                    <i class="icon-base bx bx-plus me-1"></i> Tambah Harga Khusus
                </button>
            @endcan
        </x-slot:actions>
    </x-page-header>

    {{-- Flag permission hanya untuk UX (tampil/sembunyi tombol); server tetap mengecek ulang. --}}
    <div class="card" id="special-page" data-can-update="{{ (int) auth()->user()->can('special-prices.update') }}"
        data-can-delete="{{ (int) auth()->user()->can('special-prices.delete') }}">
        <div class="card-body pb-0">
            <div class="row g-4 align-items-end">
                <div class="col-md-3">
                    <label for="filter-type" class="form-label">Jenis Harga</label>
                    <select class="form-select" id="filter-type" data-filter="price_type">
                        <option value="">Semua jenis</option>
                        @foreach ($priceTypes as $priceType)
                            <option value="{{ $priceType }}">{{ $priceType }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="col-md-3">
                    <label for="filter-group" class="form-label">Group RS</label>
                    <select class="form-select" id="filter-group" data-filter="outlet_group_id">
                        <option value="">Semua group</option>
                        @foreach ($groups as $group)
                            <option value="{{ $group->id }}">{{ $group->name }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="col-md-3">
                    <label for="filter-status" class="form-label">Status</label>
                    <select class="form-select" id="filter-status" data-filter="is_active">
                        <option value="">Semua status</option>
                        <option value="1">Aktif</option>
                        <option value="0">Nonaktif</option>
                    </select>
                </div>
                <div class="col-md-3">
                    <div class="form-check form-switch mb-2">
                        <input class="form-check-input" type="checkbox" id="filter-effective" />
                        <label class="form-check-label" for="filter-effective">Hanya yang berlaku hari ini</label>
                    </div>
                </div>
            </div>
        </div>
        <div class="card-datatable table-responsive">
            <table class="table" id="special-table">
                <thead>
                    <tr>
                        <th>Jenis</th>
                        <th>Product</th>
                        <th>Group RS</th>
                        <th class="text-end">Harga</th>
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
    <div class="modal fade" id="special-modal" tabindex="-1" aria-labelledby="special-modal-title" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered modal-lg">
            <form class="modal-content" id="special-form" novalidate>
                <div class="modal-header">
                    <h5 class="modal-title" id="special-modal-title">Tambah Harga Khusus</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Tutup"></button>
                </div>
                <div class="modal-body">
                    <div class="row g-4">
                        <div class="col-md-4">
                            <label for="special-type" class="form-label">Jenis Harga <span class="text-danger">*</span></label>
                            <input type="text" class="form-control text-uppercase" id="special-type" name="price_type" maxlength="50"
                                list="special-type-options" placeholder="mis. JKN" required />
                            <datalist id="special-type-options">
                                @foreach ($priceTypes as $priceType)
                                    <option value="{{ $priceType }}"></option>
                                @endforeach
                            </datalist>
                        </div>
                        <div class="col-md-8">
                            <label for="special-product" class="form-label">Product <span class="text-danger">*</span></label>
                            <select class="form-select" id="special-product" name="product_id" required>
                                <option value="">Pilih product</option>
                                @foreach ($products as $product)
                                    <option value="{{ $product->id }}">{{ $product->name }} ({{ $product->code }}){{ $product->is_active ? '' : ' - nonaktif' }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div class="col-md-6">
                            <label for="special-group" class="form-label">Group RS</label>
                            <select class="form-select" id="special-group" name="outlet_group_id">
                                <option value="">Tanpa group</option>
                                @foreach ($groups as $group)
                                    <option value="{{ $group->id }}">{{ $group->name }}{{ $group->is_active ? '' : ' - nonaktif' }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div class="col-md-6">
                            <label for="special-price" class="form-label">Harga <span class="text-danger">*</span></label>
                            <input type="number" class="form-control" id="special-price" name="price" min="0" step="0.0001" required />
                        </div>
                        <div class="col-md-6">
                            <label for="special-from" class="form-label">Berlaku Mulai</label>
                            <input type="date" class="form-control" id="special-from" name="valid_from" />
                        </div>
                        <div class="col-md-6">
                            <label for="special-until" class="form-label">Berlaku Sampai</label>
                            <input type="date" class="form-control" id="special-until" name="valid_until" />
                        </div>
                        <div class="col-12">
                            <label for="special-note" class="form-label">Catatan</label>
                            <textarea class="form-control" id="special-note" name="note" rows="2" maxlength="2000"></textarea>
                        </div>
                        <div class="col-12">
                            <input type="hidden" name="is_active" value="0" />
                            <div class="form-check form-switch">
                                <input class="form-check-input" type="checkbox" id="special-active" name="is_active" value="1" checked />
                                <label class="form-check-label" for="special-active">Aktif</label>
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

    @if (auth()->user()->can('special-prices.create') && auth()->user()->can('special-prices.update'))
        <x-excel-import-modal title="Import Harga Khusus dari Excel" upload-path="api/dpl/special-prices/import"
            :template-url="route('dpl.special-prices.import-template')"
            columns="jenis_harga, kode_grup, kode_product, harga, berlaku_mulai, berlaku_sampai, aktif, catatan" />
    @endif
@endpush

@push('scripts')
    <script src="{{ asset_v('js/excel-import.js') }}"></script>
    <script src="{{ asset_v('js/pages/dpl/special-prices.js') }}"></script>
@endpush
