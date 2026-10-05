@extends('layouts.app', ['vendors' => ['datatables']])

@section('title', 'Audit Log')

@section('content')
    <x-page-header title="Audit Log" :breadcrumbs="[['label' => 'Pengaturan']]"
        subtitle="Jejak login, perubahan data, dan kejadian keamanan. Hanya bisa dibaca." />

    <div class="card mb-6">
        <div class="card-body">
            <form id="audit-filter" class="row g-4 align-items-end">
                <div class="col-md-3">
                    <label for="filter-category" class="form-label">Kategori</label>
                    <select class="form-select" id="filter-category" name="category">
                        <option value="">Semua</option>
                        @foreach ($categories as $value => $label)
                            <option value="{{ $value }}">{{ $label }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="col-md-3">
                    <label for="filter-search" class="form-label">Cari</label>
                    <input type="search" class="form-control" id="filter-search" name="search" maxlength="100"
                        placeholder="Deskripsi, nama, atau NIK pelaku" />
                </div>
                <div class="col-md-2">
                    <label for="filter-from" class="form-label">Dari tanggal</label>
                    <input type="date" class="form-control" id="filter-from" name="date_from" />
                </div>
                <div class="col-md-2">
                    <label for="filter-to" class="form-label">Sampai tanggal</label>
                    <input type="date" class="form-control" id="filter-to" name="date_to" />
                </div>
                <div class="col-md-2 d-flex gap-2">
                    <button type="submit" class="btn btn-primary flex-grow-1">Terapkan</button>
                    <button type="reset" class="btn btn-label-secondary" title="Reset filter">
                        <i class="icon-base bx bx-reset"></i>
                    </button>
                </div>
            </form>
        </div>
    </div>

    <div class="card">
        <div class="card-datatable table-responsive">
            <table class="table" id="audit-table">
                <thead>
                    <tr>
                        <th>Waktu</th>
                        <th>Kategori</th>
                        <th>Aktivitas</th>
                        <th>Pelaku</th>
                        <th>IP</th>
                        <th class="text-end">Detail</th>
                    </tr>
                </thead>
                <tbody></tbody>
            </table>
        </div>
    </div>
@endsection

@push('modals')
    <div class="modal fade" id="audit-modal" tabindex="-1" aria-labelledby="audit-modal-title" aria-hidden="true">
        <div class="modal-dialog modal-lg modal-dialog-centered modal-dialog-scrollable">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="audit-modal-title">Detail Aktivitas</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Tutup"></button>
                </div>
                <div class="modal-body" id="audit-modal-body"></div>
            </div>
        </div>
    </div>
@endpush

@push('scripts')
    <script src="{{ asset_v('js/pages/audit-log.js') }}"></script>
@endpush
