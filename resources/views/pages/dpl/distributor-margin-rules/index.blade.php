@extends('layouts.app', ['vendors' => ['datatables', 'select2', 'sweetalert2']])

@section('title', 'Margin Khusus Product')

@section('content')
    <x-page-header title="Margin Khusus Product" subtitle="Margin khusus product per grup distributor, opsional per kota (legacy dpl_distributor_margin)."
        :breadcrumbs="[['label' => 'Komersial DPL']]">
        <x-slot:actions>
            @can('distributor-margin-rules.create')
                <button type="button" class="btn btn-primary" id="btn-add-rule">
                    <i class="icon-base bx bx-plus me-1"></i> Tambah Margin
                </button>
            @endcan
        </x-slot:actions>
    </x-page-header>

    {{-- Flag permission hanya untuk UX (tampil/sembunyi tombol); server tetap mengecek ulang. --}}
    <div class="card" id="rules-page" data-can-update="{{ (int) auth()->user()->can('distributor-margin-rules.update') }}"
        data-can-delete="{{ (int) auth()->user()->can('distributor-margin-rules.delete') }}">
        <div class="card-datatable table-responsive">
            <table class="table" id="rules-table">
                <thead>
                    <tr>
                        <th>Grup Distributor</th>
                        <th>Product</th>
                        <th>Kota</th>
                        <th class="text-end">Margin (%)</th>
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
    <div class="modal fade" id="rule-modal" tabindex="-1" aria-labelledby="rule-modal-title" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered modal-lg">
            <form class="modal-content" id="rule-form" novalidate>
                <div class="modal-header">
                    <h5 class="modal-title" id="rule-modal-title">Tambah Margin</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Tutup"></button>
                </div>
                <div class="modal-body">
                    <div class="row g-4">
                        <div class="col-md-6">
                            <label for="rule-group" class="form-label">Grup Distributor <span class="text-danger">*</span></label>
                            <input type="text" class="form-control text-uppercase" id="rule-group" name="distributor_group_code" maxlength="50"
                                list="rule-group-options" placeholder="Kode grup distributor" required />
                            <datalist id="rule-group-options">
                                @foreach ($groupCodes as $groupCode)
                                    <option value="{{ $groupCode }}"></option>
                                @endforeach
                            </datalist>
                        </div>
                        <div class="col-md-6">
                            <label for="rule-city" class="form-label">Kota</label>
                            <select class="form-select" id="rule-city" name="city_id">
                                <option value="">Semua kota</option>
                                @foreach ($cities as $city)
                                    <option value="{{ $city->id }}">{{ $city->name }} ({{ $city->code }})</option>
                                @endforeach
                            </select>
                        </div>
                        <div class="col-12">
                            <label for="rule-product" class="form-label">Product <span class="text-danger">*</span></label>
                            <select class="form-select" id="rule-product" name="product_id" required>
                                <option value="">Pilih product</option>
                                @foreach ($products as $product)
                                    <option value="{{ $product->id }}">{{ $product->name }} ({{ $product->code }})</option>
                                @endforeach
                            </select>
                        </div>
                        <div class="col-md-6">
                            <label for="rule-margin" class="form-label">Margin (%) <span class="text-danger">*</span></label>
                            <input type="number" class="form-control" id="rule-margin" name="margin" min="0" step="0.0001" required />
                        </div>
                        <div class="col-12">
                            <label for="rule-note" class="form-label">Catatan</label>
                            <textarea class="form-control" id="rule-note" name="note" rows="2" maxlength="2000"></textarea>
                        </div>
                        <div class="col-12">
                            <input type="hidden" name="is_active" value="0" />
                            <div class="form-check form-switch">
                                <input class="form-check-input" type="checkbox" id="rule-active" name="is_active" value="1" checked />
                                <label class="form-check-label" for="rule-active">Aktif</label>
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
    <script src="{{ asset_v('js/pages/dpl/distributor-margin-rules.js') }}"></script>
@endpush
