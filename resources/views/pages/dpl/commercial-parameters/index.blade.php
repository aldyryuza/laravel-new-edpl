@extends('layouts.app', ['vendors' => ['datatables', 'sweetalert2']])

@section('title', 'Parameter Komersial')

@section('content')
    <x-page-header title="Parameter Komersial" subtitle="Batas dan aturan bisnis yang bisa diubah tanpa mengubah program."
        :breadcrumbs="[['label' => 'Komersial DPL']]">
        <x-slot:actions>
            @can('commercial-parameters.create')
                <button type="button" class="btn btn-primary" id="btn-add-parameter">
                    <i class="icon-base bx bx-plus me-1"></i> Tambah Parameter
                </button>
            @endcan
        </x-slot:actions>
    </x-page-header>

    <div class="alert alert-info d-flex align-items-start" role="alert">
        <i class="icon-base bx bx-info-circle me-2 mt-1"></i>
        <div>
            Contoh kode dari dokumen (isi nilainya setelah dikonfirmasi bisnis):
            <ul class="mb-0 mt-1">
                @foreach ($suggestedCodes as $code => $description)
                    <li><code>{{ $code }}</code> &mdash; {{ $description }}</li>
                @endforeach
            </ul>
        </div>
    </div>

    {{-- Flag permission hanya untuk UX (tampil/sembunyi tombol); server tetap mengecek ulang. --}}
    <div class="card" id="parameters-page" data-can-update="{{ (int) auth()->user()->can('commercial-parameters.update') }}"
        data-can-delete="{{ (int) auth()->user()->can('commercial-parameters.delete') }}">
        <div class="card-datatable table-responsive">
            <table class="table" id="parameters-table">
                <thead>
                    <tr>
                        <th>Kode</th>
                        <th>Tipe</th>
                        <th>Nilai</th>
                        <th>Keterangan</th>
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
    <div class="modal fade" id="parameter-modal" tabindex="-1" aria-labelledby="parameter-modal-title" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered">
            <form class="modal-content" id="parameter-form" novalidate>
                <div class="modal-header">
                    <h5 class="modal-title" id="parameter-modal-title">Tambah Parameter</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Tutup"></button>
                </div>
                <div class="modal-body">
                    <div class="mb-4">
                        <label for="parameter-code" class="form-label">Kode <span class="text-danger">*</span></label>
                        <input type="text" class="form-control text-uppercase" id="parameter-code" name="code" maxlength="100"
                            list="parameter-code-options" placeholder="mis. MAX_DPL_DISCOUNT" required />
                        <datalist id="parameter-code-options">
                            @foreach (array_keys($suggestedCodes) as $code)
                                <option value="{{ $code }}"></option>
                            @endforeach
                        </datalist>
                    </div>
                    <div class="row g-4 mb-4">
                        <div class="col-sm-5">
                            <label for="parameter-type" class="form-label">Tipe Nilai <span class="text-danger">*</span></label>
                            <select class="form-select" id="parameter-type" name="value_type" required>
                                <option value="number">Angka</option>
                                <option value="text">Teks</option>
                                <option value="boolean">Ya / Tidak</option>
                            </select>
                        </div>
                        <div class="col-sm-7">
                            <label for="parameter-value" class="form-label">Nilai <span class="text-danger">*</span></label>
                            <input type="number" class="form-control" id="parameter-value" name="value" step="0.0001" required data-kind="number" />
                            <input type="text" class="form-control d-none" id="parameter-value-text" maxlength="1000" data-kind="text" disabled />
                            <select class="form-select d-none" id="parameter-value-boolean" data-kind="boolean" disabled>
                                <option value="1">Ya</option>
                                <option value="0">Tidak</option>
                            </select>
                        </div>
                    </div>
                    <div class="mb-4">
                        <label for="parameter-description" class="form-label">Keterangan</label>
                        <textarea class="form-control" id="parameter-description" name="description" rows="2" maxlength="2000"></textarea>
                    </div>
                    <input type="hidden" name="is_active" value="0" />
                    <div class="form-check form-switch">
                        <input class="form-check-input" type="checkbox" id="parameter-active" name="is_active" value="1" checked />
                        <label class="form-check-label" for="parameter-active">Aktif</label>
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
    <script src="{{ asset_v('js/pages/dpl/commercial-parameters.js') }}"></script>
@endpush
