@extends('layouts.app', ['vendors' => ['datatables', 'select2', 'sweetalert2']])

@section('title', $proposal ? "DPL {$proposal->dpl_number}" : 'Buat DPL')

@section('content')
    <x-page-header :title="$proposal ? 'DPL ' . $proposal->dpl_number : 'Buat DPL'"
        :breadcrumbs="[['label' => 'DPL'], ['label' => 'Pengajuan DPL', 'url' => route('dpl.proposals.index')]]">
        <x-slot:actions>
            {{-- Make a Copy / Duplicate: tampil via JS bila user pemilik DPL approved (can_amend / can_renew). --}}
            @if ($proposal)
                @can('dpl-proposals.create')
                    <button type="button" class="btn btn-label-primary d-none" id="btn-amend">
                        <i class="icon-base bx bx-copy me-1"></i> Make a Copy
                    </button>
                    <button type="button" class="btn btn-label-info d-none" id="btn-renew">
                        <i class="icon-base bx bx-duplicate me-1"></i> Duplicate
                    </button>
                @endcan
            @endif
            <a href="{{ route('dpl.proposals.index') }}" class="btn btn-label-secondary">
                <i class="icon-base bx bx-arrow-back me-1"></i> Kembali
            </a>
        </x-slot:actions>
    </x-page-header>

    <form id="proposal-form" novalidate data-proposal-id="{{ $proposal?->id }}" data-show-url="{{ url('dpl/proposals') }}"
        data-can-save="{{ (int) auth()->user()->can($proposal ? 'dpl-proposals.update' : 'dpl-proposals.create') }}">
        <div class="card mb-6">
            <div class="card-header d-flex justify-content-between align-items-center">
                <h5 class="mb-0">Header</h5>
                <div class="d-flex gap-2">
                    <span class="badge bg-label-secondary" id="proposal-status">{{ $proposal?->proposal_status ?? 'DRAFT BARU' }}</span>
                    <span class="badge d-none" id="proposal-program-status"></span>
                </div>
            </div>
            <div class="card-body">
                {{-- Asal-usul (amendment / renewal dari DPL lain) dan DPL turunan. --}}
                <div class="alert alert-info d-none" id="proposal-relation" role="alert"></div>
                <div class="row g-4">
                    <div class="col-md-6">
                        <label for="proposal-outlet" class="form-label">Outlet <span class="text-danger">*</span></label>
                        @if ($proposal)
                            <input type="text" class="form-control" id="proposal-outlet-text" readonly />
                        @else
                            <select class="form-select" id="proposal-outlet" name="outlet_id" required>
                                <option value="">Cari outlet</option>
                            </select>
                            <div class="form-text">Hanya outlet aktif di wilayah Anda. Zona outlet menentukan nomor DPL.</div>
                        @endif
                    </div>
                    <div class="col-md-3">
                        <label for="proposal-team" class="form-label">Team <span class="text-danger">*</span></label>
                        @if ($proposal)
                            <input type="text" class="form-control" id="proposal-team-text" readonly />
                        @elseif ($teams->isEmpty())
                            <input type="text" class="form-control is-invalid" value="Belum ada team" readonly />
                            <div class="invalid-feedback d-block">Minta admin mengatur team Anda di halaman Wilayah User.</div>
                        @else
                            <select class="form-select" id="proposal-team" name="team_code" @disabled($teams->count() === 1)>
                                @if ($teams->count() > 1)
                                    <option value="">Pilih team</option>
                                @endif
                                @foreach ($teams as $team)
                                    <option value="{{ $team->code }}">{{ $team->name }} ({{ $team->code }})</option>
                                @endforeach
                            </select>
                        @endif
                    </div>
                    <div class="col-md-3">
                        <label for="proposal-type" class="form-label">Jenis <span class="text-danger">*</span></label>
                        <select class="form-select" id="proposal-type" name="proposal_type" required>
                            @foreach ($types as $value => $label)
                                <option value="{{ $value }}">{{ $label }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="col-md-3">
                        <label for="proposal-offer" class="form-label">Tanggal Penawaran <span class="text-danger">*</span></label>
                        <input type="date" class="form-control" id="proposal-offer" value="{{ $today }}" readonly />
                        <div class="form-text">Otomatis tanggal pembuatan.</div>
                    </div>
                    <div class="col-md-3">
                        <label for="proposal-start" class="form-label">Date Start <span class="text-danger">*</span></label>
                        <input type="date" class="form-control" id="proposal-start" name="date_start" min="{{ $today }}" required />
                    </div>
                    <div class="col-md-3">
                        <label for="proposal-end" class="form-label">Date End <span class="text-danger">*</span></label>
                        <input type="date" class="form-control" id="proposal-end" name="date_end" min="{{ $today }}" required />
                        <div class="form-text">Maksimal 1 tahun dari Date Start.</div>
                    </div>
                    <div class="col-md-3 d-flex align-items-center">
                        <div class="form-check form-switch mt-md-6">
                            <input class="form-check-input" type="checkbox" id="proposal-contract" name="is_contract" value="1" />
                            <label class="form-check-label" for="proposal-contract">Kontrak</label>
                        </div>
                    </div>
                    {{-- 25% Bed = Bed × rasio × hari (parameter komersial); dihitung ulang server, manual bila bed outlet 0/kosong. --}}
                    <div class="col-md-3">
                        <label for="proposal-bed" class="form-label">Bed</label>
                        <input type="text" class="form-control" id="proposal-bed" readonly placeholder="Dari master outlet" />
                    </div>
                    <div class="col-md-3" id="proposal-bed-order-group" data-ratio="{{ $bedOrderRatio }}" data-days="{{ $bedOrderDays }}">
                        <label for="proposal-bed-order" class="form-label">25% Bed</label>
                        <input type="number" class="form-control" id="proposal-bed-order" name="bed_order_minimum" min="0" step="0.01" readonly />
                        <div class="form-text" id="proposal-bed-order-info">Bed × {{ $bedOrderRatio ?? '-' }} × {{ $bedOrderDays ?? '-' }}.</div>
                    </div>
                    <div class="col-12">
                        <label for="proposal-description" class="form-label">Keterangan</label>
                        <textarea class="form-control" id="proposal-description" name="description" rows="2" maxlength="5000"></textarea>
                    </div>
                </div>
            </div>
        </div>

        <div class="card mb-6">
            <div class="card-header d-flex justify-content-between align-items-center">
                <h5 class="mb-0">Item</h5>
                <button type="button" class="btn btn-sm btn-primary" id="btn-add-item" data-edit-only>
                    <i class="icon-base bx bx-plus me-1"></i> Tambah Item
                </button>
            </div>
            <div class="table-responsive">
                <table class="table" id="items-table">
                    <thead>
                        <tr>
                            <th>#</th>
                            <th>Product</th>
                            <th>Distributor</th>
                            <th class="text-end">Min. Order / Bulan</th>
                            <th class="text-end">Principle On/Off (%)</th>
                            <th class="text-end">Distributor On/Off (%)</th>
                            <th>Jenis Harga</th>
                            <th class="text-end">HNA - Disc On</th>
                            <th class="text-end">HNA+PPN - Disc On</th>
                            <th class="text-end">Margin</th>
                            <th class="text-end">Logistic Fee</th>
                            <th class="text-end">Total Calculator</th>
                            <th>Pengiriman</th>
                            <th>Bonus</th>
                            <th class="text-end">Aksi</th>
                        </tr>
                    </thead>
                    <tbody></tbody>
                </table>
            </div>
        </div>

        <div class="d-flex justify-content-end gap-2" data-edit-only>
            <button type="submit" class="btn btn-primary">
                <i class="icon-base bx bx-save me-1"></i> Simpan Draft
            </button>
            {{-- Tampil bila draft milik user dan sudah punya item tersimpan; perubahan belum tersimpan disimpan dulu. --}}
            @if ($proposal)
                <button type="button" class="btn btn-success d-none" id="btn-submit-proposal">
                    <i class="icon-base bx bx-send me-1"></i> Ajukan
                </button>
            @endif
        </div>
    </form>

    @if ($proposal)
        {{-- Panel approval (dimuat via api/dpl/proposals/{id}/approval bila DPL bukan DRAFT). Di luar form agar
             checkbox tidak ikut dinonaktifkan saat form read-only. Flag hanya UX; server tetap mengecek. --}}
        {{-- Progress approval (timeline per level) di kiri, matriks approval di kanan. --}}
        <div class="row g-6 mt-0">
            <div class="col-xl-4">
                <div class="card h-100 d-none" id="approval-progress-card">
                    <div class="card-header">
                        <h5 class="mb-0">Progress Approval</h5>
                    </div>
                    <div class="card-body">
                        <div class="d-flex justify-content-between align-items-center mb-2">
                            <span>Item × level approve</span>
                            <span class="fw-medium" id="approval-progress-percent">0%</span>
                        </div>
                        <div class="progress mb-6" style="height: 8px">
                            <div class="progress-bar" role="progressbar" id="approval-progress-bar" style="width: 0%" aria-valuenow="0"
                                aria-valuemin="0" aria-valuemax="100"></div>
                        </div>
                        <ul class="timeline mb-0" id="approval-timeline"></ul>
                    </div>
                </div>
            </div>
            <div class="col-xl-8">
        <div class="card h-100 d-none" id="approval-card" data-can-approve="{{ (int) auth()->user()->can('dpl-proposals.approve') }}">
            <div class="card-header d-flex flex-wrap justify-content-between align-items-center gap-2">
                <div>
                    <h5 class="mb-0">Approval</h5>
                    <div class="small text-body-secondary" id="approval-waiting"></div>
                </div>
                <div class="d-flex flex-wrap align-items-center gap-2">
                    <div class="d-flex flex-wrap gap-2 d-none" id="approval-actions">
                        <button type="button" class="btn btn-sm btn-success" data-approval-action="approve" data-approval-scope="selected">
                            <i class="icon-base bx bx-check me-1"></i> Approve Terpilih
                        </button>
                        <button type="button" class="btn btn-sm btn-danger" data-approval-action="reject" data-approval-scope="selected">
                            <i class="icon-base bx bx-x me-1"></i> Reject Terpilih
                        </button>
                        <button type="button" class="btn btn-sm btn-label-success" data-approval-action="approve" data-approval-scope="all">
                            <i class="icon-base bx bx-check-double me-1"></i> Approve Semua
                        </button>
                        <button type="button" class="btn btn-sm btn-label-danger" data-approval-action="reject" data-approval-scope="all">
                            <i class="icon-base bx bx-block me-1"></i> Reject Semua
                        </button>
                    </div>
                    <button type="button" class="btn btn-sm btn-icon btn-text-secondary" data-bs-toggle="collapse" data-bs-target="#approval-body"
                        aria-expanded="true" aria-controls="approval-body" title="Tampilkan / sembunyikan">
                        <i class="icon-base bx bx-chevron-down"></i>
                    </button>
                </div>
            </div>
            <div class="collapse show" id="approval-body">
                {{-- Item ditolak otomatis: pengaju bisa minta Open, reviewer (GM) bisa membuka / menolak permintaan. --}}
                <div class="alert alert-warning mx-4 d-none" id="approval-open" role="alert"></div>
                <div class="card-datatable table-responsive">
                    <table class="table table-sm table-bordered" id="approval-matrix">
                        <thead></thead>
                        <tbody></tbody>
                    </table>
                </div>
            </div>
        </div>

            </div>
        </div>

        {{-- Riwayat approval: default disembunyikan agar tidak memakan tempat. --}}
        <div class="card mt-6 d-none" id="approval-history-card">
            <div class="card-header d-flex justify-content-between align-items-center">
                <h5 class="mb-0">Riwayat Approval <span class="badge bg-label-secondary ms-1" id="approval-history-count">0</span></h5>
                <button type="button" class="btn btn-sm btn-label-secondary" data-bs-toggle="collapse" data-bs-target="#approval-history-body"
                    aria-expanded="false" aria-controls="approval-history-body">
                    <i class="icon-base bx bx-history me-1"></i> Tampilkan / sembunyikan
                </button>
            </div>
            <div class="collapse" id="approval-history-body">
                <div class="card-datatable table-responsive">
                    <table class="table table-sm" id="approval-history">
                        <thead>
                            <tr>
                                <th>Tanggal</th>
                                <th>Step</th>
                                <th>Aktor</th>
                                <th>Aksi</th>
                                <th>Item</th>
                                <th>Alasan</th>
                            </tr>
                        </thead>
                        <tbody></tbody>
                    </table>
                </div>
            </div>
        </div>
    @endif
@endsection

@push('modals')
    @if ($proposal)
        <div class="modal fade" id="renew-modal" tabindex="-1" aria-labelledby="renew-modal-title" aria-hidden="true">
            <div class="modal-dialog modal-dialog-centered">
                <form class="modal-content" id="renew-form" novalidate>
                    <div class="modal-header">
                        <h5 class="modal-title" id="renew-modal-title">Duplicate DPL</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Tutup"></button>
                    </div>
                    <div class="modal-body">
                        <p class="text-body-secondary">Membuat draft baru dari DPL ini dengan periode baru. Margin & logistic fee dihitung ulang dan approval dimulai dari awal.</p>
                        <div class="row g-4">
                            <div class="col-sm-6">
                                <label for="renew-start" class="form-label">Date Start <span class="text-danger">*</span></label>
                                <input type="date" class="form-control" id="renew-start" name="date_start" min="{{ $today }}" required />
                            </div>
                            <div class="col-sm-6">
                                <label for="renew-end" class="form-label">Date End <span class="text-danger">*</span></label>
                                <input type="date" class="form-control" id="renew-end" name="date_end" min="{{ $today }}" required />
                                <div class="form-text">Maksimal 1 tahun dari Date Start.</div>
                            </div>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-label-secondary" data-bs-dismiss="modal">Batal</button>
                        <button type="submit" class="btn btn-primary">Buat Draft</button>
                    </div>
                </form>
            </div>
        </div>
    @endif
    @if ($proposal)
        <div class="modal fade" id="approval-modal" tabindex="-1" aria-labelledby="approval-modal-title" aria-hidden="true">
            <div class="modal-dialog modal-dialog-centered">
                <form class="modal-content" id="approval-form" novalidate>
                    <div class="modal-header">
                        <h5 class="modal-title" id="approval-modal-title">Approve Item</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Tutup"></button>
                    </div>
                    <div class="modal-body">
                        <p class="mb-4" id="approval-modal-summary"></p>
                        <label for="approval-reason" class="form-label">Alasan <span class="text-danger d-none" id="approval-reason-required">*</span></label>
                        <textarea class="form-control" id="approval-reason" name="reason" rows="3" maxlength="2000"></textarea>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-label-secondary" data-bs-dismiss="modal">Batal</button>
                        <button type="submit" class="btn btn-primary" id="approval-modal-submit">Approve</button>
                    </div>
                </form>
            </div>
        </div>
    @endif
    <div class="modal fade" id="item-modal" tabindex="-1" aria-labelledby="item-modal-title" aria-hidden="true">
        <div class="modal-dialog modal-lg modal-dialog-centered">
            <form class="modal-content" id="item-form" novalidate>
                <div class="modal-header">
                    <h5 class="modal-title" id="item-modal-title">Tambah Item</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Tutup"></button>
                </div>
                <div class="modal-body">
                    <div class="row g-4">
                        <div class="col-12" id="item-mode-group">
                            <label class="form-label d-block">Pilih Product</label>
                            <div class="form-check form-check-inline">
                                <input class="form-check-input" type="radio" name="product_mode" id="item-mode-single" value="single" checked />
                                <label class="form-check-label" for="item-mode-single">Satuan</label>
                            </div>
                            <div class="form-check form-check-inline">
                                <input class="form-check-input" type="radio" name="product_mode" id="item-mode-group-radio" value="group" />
                                <label class="form-check-label" for="item-mode-group-radio">Group</label>
                            </div>
                        </div>

                        {{-- Mode Satuan: satu product, harga langsung tampil. --}}
                        <div class="col-md-8" data-product-mode="single">
                            <label for="item-product-single" class="form-label">Product <span class="text-danger">*</span></label>
                            <select class="form-select" id="item-product-single" name="product_single">
                                <option value="">Cari product</option>
                            </select>
                        </div>

                        {{-- Mode Group: semua product di group otomatis terpilih, harga tampil di tabel setelah disimpan. --}}
                        <div class="col-md-4 d-none" data-product-mode="group">
                            <label for="item-group" class="form-label">Group Product <span class="text-danger">*</span></label>
                            <select class="form-select" id="item-group" name="product_group">
                                <option value="">Pilih group</option>
                                @foreach ($productGroups as $group)
                                    <option value="{{ $group }}">{{ $group }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div class="col-md-8 d-none" data-product-mode="group">
                            <div class="d-flex justify-content-between align-items-center">
                                <label for="item-product" class="form-label">Product <span class="text-danger">*</span></label>
                                <div class="d-flex gap-1 mb-1">
                                    <button type="button" class="btn btn-xs btn-label-primary" id="btn-select-all-products">Pilih semua</button>
                                    <button type="button" class="btn btn-xs btn-label-secondary" id="btn-clear-products">Kosongkan</button>
                                </div>
                            </div>
                            <select class="form-select" id="item-product" name="product_id" multiple></select>
                            <div class="form-text">Semua product di group otomatis terpilih. Satu product = satu item; harga tampil di tabel setelah disimpan.</div>
                        </div>

                        <div class="col-md-4">
                            <label for="item-price-type" class="form-label">Jenis Harga <span class="text-danger">*</span></label>
                            <select class="form-select" id="item-price-type" name="price_type">
                                @foreach ($priceTypes as $priceType)
                                    {{-- data-volumes kosong = boleh untuk semua product. --}}
                                    <option value="{{ $priceType }}" data-volumes="{{ json_encode(config("dpl.price_type_volumes.{$priceType}")) }}">{{ $priceType }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div class="col-md-6" data-product-mode="single">
                            <label for="item-hna-disc" class="form-label">HNA - Discount On</label>
                            <input type="text" class="form-control text-end" id="item-hna-disc" inputmode="numeric" placeholder="Pilih product" />
                            <div class="form-text" id="item-hna-disc-info">Bisa diubah: Discount Share Principle ON menyesuaikan.</div>
                        </div>
                        <div class="col-md-6" data-product-mode="single">
                            <label for="item-hna-ppn-disc" class="form-label">HNA+PPN - Discount On</label>
                            <input type="text" class="form-control text-end" id="item-hna-ppn-disc" readonly placeholder="Pilih product" />
                            <div class="form-text" id="item-price-info"></div>
                        </div>
                        <div class="col-md-8">
                            <label for="item-distributor" class="form-label">Distributor <span class="text-danger">*</span></label>
                            <select class="form-select" id="item-distributor" name="distributor_id" required>
                                <option value="">Pilih distributor</option>
                                @foreach ($distributors as $distributor)
                                    <option value="{{ $distributor->id }}">{{ $distributor->name }} ({{ $distributor->code }})</option>
                                @endforeach
                            </select>
                        </div>
                        <div class="col-md-3">
                            <label for="item-principle-on" class="form-label">Discount Share Principle ON (%)</label>
                            <input type="number" class="form-control" id="item-principle-on" name="principle_discount_on" min="0" step="0.01" />
                        </div>
                        <div class="col-md-3">
                            <label for="item-principle-off" class="form-label">Discount Share Principle OFF (%)</label>
                            <input type="number" class="form-control" id="item-principle-off" name="principle_discount_off" min="0" step="0.01" />
                        </div>
                        <div class="col-md-3">
                            <label for="item-distributor-on" class="form-label">Discount Share Distributor ON (%)</label>
                            <input type="number" class="form-control" id="item-distributor-on" name="distributor_discount_on" min="0" step="0.01" />
                        </div>
                        <div class="col-md-3">
                            <label for="item-distributor-off" class="form-label">Discount Share Distributor OFF (%)</label>
                            <input type="number" class="form-control" id="item-distributor-off" name="distributor_discount_off" min="0" step="0.01" />
                        </div>
                        <div class="col-md-3">
                            <label for="item-margin" class="form-label">Margin (%)</label>
                            <input type="number" class="form-control" id="item-margin" name="margin" min="0" step="0.0001" />
                            <div class="form-text" id="item-margin-source"></div>
                        </div>
                        <div class="col-md-3">
                            <label for="item-logistic" class="form-label">Support Logistic Satoria (%)</label>
                            <input type="number" class="form-control" id="item-logistic" name="logistic_support" min="0" max="100" step="0.01" />
                        </div>
                        <div class="col-md-3">
                            <label for="item-minimum" class="form-label">Minimum Order per Bulan <span class="text-danger">*</span></label>
                            <input type="number" class="form-control" id="item-minimum" name="minimum_order" min="0" step="1" required />
                        </div>
                        <div class="col-md-6">
                            <label for="item-bonus" class="form-label">Skema Bonus</label>
                            <select class="form-select" id="item-bonus" name="bonus_scheme_id">
                                <option value="">Tanpa bonus</option>
                                @foreach ($bonusSchemes as $scheme)
                                    <option value="{{ $scheme->id }}">{{ $scheme->name }}{{ $scheme->code ? " ({$scheme->code})" : '' }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label d-block">Pengiriman</label>
                            <div class="form-check form-check-inline">
                                <input class="form-check-input" type="checkbox" id="item-is_direct_delivery" name="is_direct_delivery" value="1" />
                                <label class="form-check-label" for="item-is_direct_delivery">Direct</label>
                            </div>
                            <div class="form-check form-check-inline">
                                <input class="form-check-input" type="checkbox" id="item-is_factory_pickup" name="is_factory_pickup" value="1" />
                                <label class="form-check-label" for="item-is_factory_pickup">Ambil Dari Pabrik</label>
                            </div>
                            <div class="form-text">Pilih salah satu atau kosongkan (via distributor).</div>
                        </div>
                        <div class="col-md-6" id="item-logistic-fee-group">
                            <label for="item-logistic-fee" class="form-label">Logistic Fee (%)</label>
                            <input type="number" class="form-control" id="item-logistic-fee" name="logistic_fee" min="0" step="0.0001" readonly />
                            <div class="form-text" id="item-logistic-source"></div>
                        </div>
                        <div class="col-12">
                            <label class="form-label d-block">Opsi</label>
                            @foreach ([
                                'is_buyer_reward' => 'Buyer reward',
                                'is_support_satoria' => 'Support Satoria',
                                'is_returnable' => 'Bisa retur',
                            ] as $flag => $label)
                                <div class="form-check form-check-inline">
                                    <input class="form-check-input" type="checkbox" id="item-{{ $flag }}" name="{{ $flag }}" value="1" />
                                    <label class="form-check-label" for="item-{{ $flag }}">{{ $label }}</label>
                                </div>
                            @endforeach
                            <div class="form-text">Buyer reward otomatis dicentang bila Discount Share Principle OFF diisi.</div>
                        </div>
                        <div class="col-12">
                            {{-- Dihitung server (api/dpl/proposals/calculate), rumus sama dengan yang disimpan. --}}
                            <div class="alert alert-secondary d-flex justify-content-between align-items-center mb-0 py-2" id="item-calculator">
                                <span>Total Calculator</span>
                                <strong id="item-calculator-total">-</strong>
                            </div>
                            <div class="form-text" id="item-calculator-info"></div>
                        </div>
                        <div class="col-12">
                            <label for="item-remark" class="form-label">Catatan</label>
                            <textarea class="form-control" id="item-remark" name="remark" rows="2" maxlength="2000"></textarea>
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-label-secondary" data-bs-dismiss="modal">Batal</button>
                    <button type="submit" class="btn btn-primary">Simpan Item</button>
                </div>
            </form>
        </div>
    </div>
@endpush

@push('scripts')
    {{-- Product per group untuk pilihan item (id, label, group, volume). --}}
    <script type="application/json" id="dpl-products">@json($productOptions)</script>
    <script src="{{ asset_v('js/pages/dpl/proposal-form.js') }}"></script>
@endpush
