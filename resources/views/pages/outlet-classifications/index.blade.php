@extends('layouts.app', ['vendors' => ['datatables', 'sweetalert2']])

@section('title', 'Klasifikasi Outlet')

@section('content')
    <x-page-header title="Klasifikasi Outlet" :breadcrumbs="[['label' => 'Master Outlet']]" />

    {{-- Flag permission hanya untuk UX (tampil/sembunyi tombol); server tetap mengecek ulang. --}}
    <div class="row g-6" id="classifications-page" data-can-create="{{ (int) auth()->user()->can('outlet-classifications.create') }}"
        data-can-update="{{ (int) auth()->user()->can('outlet-classifications.update') }}"
        data-can-delete="{{ (int) auth()->user()->can('outlet-classifications.delete') }}">
        @foreach ([
            ['key' => 'categories', 'title' => 'Kategori Outlet', 'hint' => 'Legacy GRUP1, mis. RUMAH SAKIT / INSTANSI'],
            ['key' => 'ownership', 'title' => 'Jenis Kepemilikan', 'hint' => 'Legacy GRUP2, mis. PEMERINTAH / BUKAN PEMERINTAH'],
        ] as $section)
            <div class="col-lg-6">
                <div class="card h-100">
                    <div class="card-header d-flex justify-content-between align-items-start">
                        <div>
                            <h5 class="mb-1">{{ $section['title'] }}</h5>
                            <small class="text-body-secondary">{{ $section['hint'] }}</small>
                        </div>
                        @can('outlet-classifications.create')
                            <button type="button" class="btn btn-sm btn-primary btn-add" data-kind="{{ $section['key'] }}">
                                <i class="icon-base bx bx-plus me-1"></i> Tambah
                            </button>
                        @endcan
                    </div>
                    <div class="card-datatable table-responsive">
                        <table class="table" id="{{ $section['key'] }}-table">
                            <thead>
                                <tr>
                                    <th>Kode</th>
                                    <th>Nama</th>
                                    <th>Outlet</th>
                                    <th>Status</th>
                                    <th class="text-end">Aksi</th>
                                </tr>
                            </thead>
                            <tbody></tbody>
                        </table>
                    </div>
                </div>
            </div>
        @endforeach
    </div>
@endsection

@push('modals')
    <div class="modal fade" id="classification-modal" tabindex="-1" aria-labelledby="classification-modal-title" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered">
            <form class="modal-content" id="classification-form" novalidate>
                <div class="modal-header">
                    <h5 class="modal-title" id="classification-modal-title">Tambah</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Tutup"></button>
                </div>
                <div class="modal-body">
                    <div class="mb-4">
                        <label for="classification-code" class="form-label">Kode <span class="text-danger">*</span></label>
                        <input type="text" class="form-control" id="classification-code" name="code" maxlength="30" required />
                    </div>
                    <div class="mb-4">
                        <label for="classification-name" class="form-label">Nama <span class="text-danger">*</span></label>
                        <input type="text" class="form-control" id="classification-name" name="name" maxlength="100" required />
                    </div>
                    <input type="hidden" name="is_active" value="0" />
                    <div class="form-check form-switch">
                        <input class="form-check-input" type="checkbox" id="classification-active" name="is_active" value="1" checked />
                        <label class="form-check-label" for="classification-active">Aktif</label>
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
    <script src="{{ asset_v('js/pages/outlet-classifications.js') }}"></script>
@endpush
