@extends('layouts.app', ['vendors' => ['select2', 'sweetalert2']])

@section('title', $proposal ? "DPL {$proposal->dpl_number}" : 'Buat DPL')

@section('content')
    <x-page-header :title="$proposal ? 'DPL ' . $proposal->dpl_number : 'Buat DPL'"
        :breadcrumbs="[['label' => 'DPL'], ['label' => 'Pengajuan DPL', 'url' => route('dpl.proposals.index')]]">
        <x-slot:actions>
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
                <span class="badge bg-label-secondary" id="proposal-status">{{ $proposal?->proposal_status ?? 'DRAFT BARU' }}</span>
            </div>
            <div class="card-body">
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
                            <th class="text-end">Min. Order</th>
                            <th class="text-end">Principle On/Off (%)</th>
                            <th class="text-end">Distributor On/Off (%)</th>
                            <th>Jenis Harga</th>
                            <th class="text-end">HNA - Disc On</th>
                            <th class="text-end">HNA+PPN - Disc On</th>
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
        </div>
    </form>
@endsection

@push('modals')
    <div class="modal fade" id="item-modal" tabindex="-1" aria-labelledby="item-modal-title" aria-hidden="true">
        <div class="modal-dialog modal-lg modal-dialog-centered">
            <form class="modal-content" id="item-form" novalidate>
                <div class="modal-header">
                    <h5 class="modal-title" id="item-modal-title">Tambah Item</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Tutup"></button>
                </div>
                <div class="modal-body">
                    <div class="row g-4">
                        <div class="col-md-6">
                            <label for="item-product" class="form-label">Product <span class="text-danger">*</span></label>
                            <select class="form-select" id="item-product" name="product_id" required>
                                <option value="">Pilih product</option>
                                @foreach ($products as $product)
                                    <option value="{{ $product->id }}">{{ $product->name }} ({{ $product->code }})</option>
                                @endforeach
                            </select>
                        </div>
                        <div class="col-md-6">
                            <label for="item-distributor" class="form-label">Distributor <span class="text-danger">*</span></label>
                            <select class="form-select" id="item-distributor" name="distributor_id" required>
                                <option value="">Pilih distributor</option>
                                @foreach ($distributors as $distributor)
                                    <option value="{{ $distributor->id }}">{{ $distributor->name }} ({{ $distributor->code }})</option>
                                @endforeach
                            </select>
                        </div>
                        <div class="col-md-4">
                            <label for="item-price-type" class="form-label">Jenis Harga <span class="text-danger">*</span></label>
                            <select class="form-select" id="item-price-type" name="price_type">
                                @foreach ($priceTypes as $priceType)
                                    <option value="{{ $priceType }}">{{ $priceType }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div class="col-md-4">
                            <label for="item-hna-disc" class="form-label">HNA - Discount On</label>
                            <input type="text" class="form-control text-end" id="item-hna-disc" readonly placeholder="Pilih product" />
                        </div>
                        <div class="col-md-4">
                            <label for="item-hna-ppn-disc" class="form-label">HNA+PPN - Discount On</label>
                            <input type="text" class="form-control text-end" id="item-hna-ppn-disc" readonly placeholder="Pilih product" />
                            <div class="form-text" id="item-price-info"></div>
                        </div>
                        <div class="col-md-3">
                            <label for="item-principle-on" class="form-label">Discount Share Principle ON (%)</label>
                            <input type="number" class="form-control" id="item-principle-on" name="principle_discount_on" min="0" step="0.0001" />
                        </div>
                        <div class="col-md-3">
                            <label for="item-principle-off" class="form-label">Discount Share Principle OFF (%)</label>
                            <input type="number" class="form-control" id="item-principle-off" name="principle_discount_off" min="0" step="0.0001" />
                        </div>
                        <div class="col-md-3">
                            <label for="item-distributor-on" class="form-label">Discount Share Distributor ON (%)</label>
                            <input type="number" class="form-control" id="item-distributor-on" name="distributor_discount_on" min="0" step="0.0001" />
                        </div>
                        <div class="col-md-3">
                            <label for="item-distributor-off" class="form-label">Discount Share Distributor OFF (%)</label>
                            <input type="number" class="form-control" id="item-distributor-off" name="distributor_discount_off" min="0" step="0.0001" />
                        </div>
                        <div class="col-md-3">
                            <label for="item-margin" class="form-label">Margin (%)</label>
                            <input type="number" class="form-control" id="item-margin" name="margin" min="0" step="0.0001" />
                        </div>
                        <div class="col-md-3">
                            <label for="item-logistic" class="form-label">Logistic Support</label>
                            <input type="number" class="form-control" id="item-logistic" name="logistic_support" min="0" step="0.0001" />
                        </div>
                        <div class="col-md-3">
                            <label for="item-minimum" class="form-label">Minimum Order <span class="text-danger">*</span></label>
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
                        <div class="col-md-6 d-none" id="item-logistic-fee-group">
                            <label for="item-logistic-fee" class="form-label">Logistic Fee <span class="text-danger">*</span></label>
                            <input type="number" class="form-control" id="item-logistic-fee" name="logistic_fee" min="0" step="0.0001" />
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
    <script src="{{ asset_v('js/pages/dpl/proposal-form.js') }}"></script>
@endpush
