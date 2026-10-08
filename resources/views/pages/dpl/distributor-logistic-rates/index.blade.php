@extends('layouts.app', ['vendors' => ['datatables', 'select2', 'sweetalert2']])

@section('title', 'Tarif Logistic Distributor')

@section('content')
    <x-page-header title="Tarif Logistic Distributor" subtitle="Logistic fee (%) khusus per grup distributor dan kota."
        :breadcrumbs="[['label' => 'Komersial DPL']]">
        <x-slot:actions>
            @can('distributor-logistic-rates.create')
                <button type="button" class="btn btn-primary" id="btn-add-rate">
                    <i class="icon-base bx bx-plus me-1"></i> Tambah Tarif
                </button>
            @endcan
        </x-slot:actions>
    </x-page-header>

    <div class="alert alert-info d-flex align-items-start" role="alert">
        <i class="icon-base bx bx-info-circle me-2 mt-1"></i>
        <div>Menggantikan logistic fee kota untuk grup distributor ini. Distributor nasional tetap memakai tarif nasional.</div>
    </div>

    {{-- Flag permission hanya untuk UX (tampil/sembunyi tombol); server tetap mengecek ulang. --}}
    <div class="card" id="rates-page" data-can-update="{{ (int) auth()->user()->can('distributor-logistic-rates.update') }}"
        data-can-delete="{{ (int) auth()->user()->can('distributor-logistic-rates.delete') }}">
        <div class="card-datatable table-responsive">
            <table class="table" id="rates-table">
                <thead>
                    <tr>
                        <th>Grup Distributor</th>
                        <th>Kota</th>
                        <th class="text-end">Logistic Fee (%)</th>
                        <th>Catatan</th>
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
    <div class="modal fade" id="rate-modal" tabindex="-1" aria-labelledby="rate-modal-title" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered modal-lg">
            <form class="modal-content" id="rate-form" novalidate>
                <div class="modal-header">
                    <h5 class="modal-title" id="rate-modal-title">Tambah Tarif</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Tutup"></button>
                </div>
                <div class="modal-body">
                    <div class="row g-4">
                        <div class="col-md-6">
                            <label for="rate-group" class="form-label">Grup Distributor <span class="text-danger">*</span></label>
                            <input type="text" class="form-control text-uppercase" id="rate-group" name="distributor_group_code" maxlength="50"
                                list="rate-group-options" placeholder="Kode grup distributor" required />
                            <datalist id="rate-group-options">
                                @foreach ($groupCodes as $groupCode)
                                    <option value="{{ $groupCode }}"></option>
                                @endforeach
                            </datalist>
                        </div>
                        <div class="col-md-6">
                            <label for="rate-city" class="form-label">Kota <span class="text-danger">*</span></label>
                            <select class="form-select" id="rate-city" name="city_id" required>
                                <option value="">Pilih kota</option>
                                @foreach ($cities as $city)
                                    <option value="{{ $city->id }}">{{ $city->name }} ({{ $city->code }})</option>
                                @endforeach
                            </select>
                        </div>
                        <div class="col-md-6">
                            <label for="rate-fee" class="form-label">Logistic Fee (%) <span class="text-danger">*</span></label>
                            <input type="number" class="form-control" id="rate-fee" name="logistic_fee" min="0" step="0.0001" required />
                        </div>
                        <div class="col-12">
                            <label for="rate-note" class="form-label">Catatan</label>
                            <textarea class="form-control" id="rate-note" name="note" rows="2" maxlength="2000"></textarea>
                        </div>
                        <div class="col-12">
                            <input type="hidden" name="is_active" value="0" />
                            <div class="form-check form-switch">
                                <input class="form-check-input" type="checkbox" id="rate-active" name="is_active" value="1" checked />
                                <label class="form-check-label" for="rate-active">Aktif</label>
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
    <script src="{{ asset_v('js/pages/dpl/distributor-logistic-rates.js') }}"></script>
@endpush
