@extends('layouts.app', ['vendors' => ['datatables', 'sweetalert2']])

@section('title', 'Skema Bonus')

@section('content')
    <x-page-header title="Skema Bonus" subtitle="Program bonus, mis. beli 5 bonus 1. Semua nilai diisi manual."
        :breadcrumbs="[['label' => 'Komersial DPL']]">
        <x-slot:actions>
            @can('bonus-schemes.create')
                <button type="button" class="btn btn-primary" id="btn-add-bonus">
                    <i class="icon-base bx bx-plus me-1"></i> Tambah Skema Bonus
                </button>
            @endcan
        </x-slot:actions>
    </x-page-header>

    {{-- Flag permission hanya untuk UX (tampil/sembunyi tombol); server tetap mengecek ulang. --}}
    <div class="card" id="bonus-page" data-can-update="{{ (int) auth()->user()->can('bonus-schemes.update') }}"
        data-can-delete="{{ (int) auth()->user()->can('bonus-schemes.delete') }}">
        <div class="card-datatable table-responsive">
            <table class="table" id="bonus-table">
                <thead>
                    <tr>
                        <th>Kode</th>
                        <th>Nama</th>
                        <th class="text-end">Beli</th>
                        <th class="text-end">Bonus</th>
                        <th class="text-end">Disc. Invoice (%)</th>
                        <th class="text-end">Disc. Bonus (%)</th>
                        <th class="text-end">Disc. Efektif (%)</th>
                        <th>Dipakai Item</th>
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
    <div class="modal fade" id="bonus-modal" tabindex="-1" aria-labelledby="bonus-modal-title" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered modal-lg">
            <form class="modal-content" id="bonus-form" novalidate>
                <div class="modal-header">
                    <h5 class="modal-title" id="bonus-modal-title">Tambah Skema Bonus</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Tutup"></button>
                </div>
                <div class="modal-body">
                    <div class="row g-4">
                        <div class="col-md-4">
                            <label for="bonus-code" class="form-label">Kode</label>
                            <input type="text" class="form-control" id="bonus-code" name="code" maxlength="30" />
                        </div>
                        <div class="col-md-8">
                            <label for="bonus-name" class="form-label">Nama <span class="text-danger">*</span></label>
                            <input type="text" class="form-control" id="bonus-name" name="name" maxlength="255" placeholder="mis. Beli 5 Bonus 1" required />
                        </div>
                        <div class="col-md-6">
                            <label for="bonus-buy" class="form-label">Jumlah Beli</label>
                            <input type="number" class="form-control" id="bonus-buy" name="buy_quantity" min="0" step="0.0001" />
                        </div>
                        <div class="col-md-6">
                            <label for="bonus-bonus" class="form-label">Jumlah Bonus</label>
                            <input type="number" class="form-control" id="bonus-bonus" name="bonus_quantity" min="0" step="0.0001" />
                        </div>
                        <div class="col-md-4">
                            <label for="bonus-discount-invoice" class="form-label">Diskon Invoice (%)</label>
                            <input type="number" class="form-control" id="bonus-discount-invoice" name="discount_invoice" min="0" step="0.0001" />
                        </div>
                        <div class="col-md-4">
                            <label for="bonus-discount-bonus" class="form-label">Diskon Bonus (%)</label>
                            <input type="number" class="form-control" id="bonus-discount-bonus" name="discount_bonus" min="0" step="0.0001" />
                        </div>
                        <div class="col-md-4">
                            <label for="bonus-discount-effective" class="form-label">Diskon Efektif (%)</label>
                            <input type="number" class="form-control" id="bonus-discount-effective" name="effective_discount" min="0" step="0.0001" />
                        </div>
                        <div class="col-12">
                            <div class="form-text mb-3">Diskon efektif diisi manual; rumus perhitungannya belum dikonfirmasi bisnis.</div>
                            <input type="hidden" name="is_active" value="0" />
                            <div class="form-check form-switch">
                                <input class="form-check-input" type="checkbox" id="bonus-active" name="is_active" value="1" checked />
                                <label class="form-check-label" for="bonus-active">Aktif</label>
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
    <script src="{{ asset_v('js/pages/dpl/bonus-schemes.js') }}"></script>
@endpush
