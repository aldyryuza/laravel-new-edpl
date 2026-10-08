@extends('layouts.app', ['vendors' => ['datatables', 'select2', 'sweetalert2']])

@section('title', 'Aturan Approval')

@section('content')
    <x-page-header title="Aturan Approval" subtitle="Jalur approval item per rentang Total Calculator dan provinsi outlet (legacy DPL_ITEM_APPROVAL_RULE)."
        :breadcrumbs="[['label' => 'DPL']]">
        <x-slot:actions>
            @can('approval-rules.create')
                <button type="button" class="btn btn-primary" id="btn-add-rule">
                    <i class="icon-base bx bx-plus me-1"></i> Tambah Aturan
                </button>
            @endcan
        </x-slot:actions>
    </x-page-header>

    <div class="alert alert-info d-flex align-items-start" role="alert">
        <i class="icon-base bx bx-info-circle me-2 mt-1"></i>
        <div>
            Aturan dicek berurutan; yang pertama cocok (Total Calculator item &amp; provinsi kota outlet) menentukan step approval item.
            Tidak ada yang cocok = semua step (RM → NSM → GM → SMD → DMD).
        </div>
    </div>

    {{-- Flag permission hanya untuk UX (tampil/sembunyi tombol); server tetap mengecek ulang. --}}
    <div class="card" id="rules-page" data-can-update="{{ (int) auth()->user()->can('approval-rules.update') }}"
        data-can-delete="{{ (int) auth()->user()->can('approval-rules.delete') }}">
        <div class="card-datatable table-responsive">
            <table class="table" id="rules-table">
                <thead>
                    <tr>
                        <th>Urutan</th>
                        <th>Nama</th>
                        <th>Rentang Total Calculator (%)</th>
                        <th>Step</th>
                        <th>Provinsi</th>
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
                    <h5 class="modal-title" id="rule-modal-title">Tambah Aturan</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Tutup"></button>
                </div>
                <div class="modal-body">
                    @if ($steps->isEmpty())
                        <div class="alert alert-warning" role="alert">
                            Belum ada step workflow DPL standard. Jalankan ApprovalReferenceSeeder.
                        </div>
                    @endif
                    <div class="row g-4">
                        <div class="col-md-9">
                            <label for="rule-name" class="form-label">Nama <span class="text-danger">*</span></label>
                            <input type="text" class="form-control" id="rule-name" name="name" maxlength="100" required />
                        </div>
                        <div class="col-md-3">
                            <label for="rule-sort" class="form-label">Urutan</label>
                            <input type="number" class="form-control" id="rule-sort" name="sort_order" step="1" value="0" />
                        </div>
                        <div class="col-md-6">
                            <label for="rule-lower" class="form-label">Total Calculator lebih dari (%)</label>
                            <input type="number" class="form-control" id="rule-lower" name="discount_lower" step="0.0001" />
                            <div class="form-text">Kosong = tanpa batas bawah.</div>
                        </div>
                        <div class="col-md-6">
                            <label for="rule-upper" class="form-label">sampai dengan (%)</label>
                            <input type="number" class="form-control" id="rule-upper" name="discount_upper" step="0.0001" />
                            <div class="form-text">Kosong = tanpa batas atas.</div>
                        </div>
                        <div class="col-12">
                            <label for="rule-steps" class="form-label">Step wajib <span class="text-danger">*</span></label>
                            <select class="form-select" id="rule-steps" name="step_ids[]" multiple required>
                                @foreach ($steps as $step)
                                    <option value="{{ $step->id }}">{{ $step->role?->name ?? "Step {$step->sequence}" }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div class="col-12">
                            <label for="rule-provinces" class="form-label">Provinsi</label>
                            <select class="form-select" id="rule-provinces" name="province_ids[]" multiple>
                                @foreach ($provinces as $province)
                                    <option value="{{ $province->id }}">{{ $province->name }}</option>
                                @endforeach
                            </select>
                            <div class="form-text">Kosong = semua provinsi.</div>
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
    <script src="{{ asset_v('js/pages/dpl/approval-rules.js') }}"></script>
@endpush
