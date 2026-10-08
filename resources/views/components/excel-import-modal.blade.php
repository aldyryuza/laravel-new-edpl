{{--
    Modal upload Excel (import massal). Perilaku ada di public/js/excel-import.js; setelah sukses
    memicu event `excel-import:done` di document (mis. untuk reload tabel).

        <x-excel-import-modal title="Import Area" upload-path="api/sales-areas/import"
            :template-url="route('sales-areas.import-template')" columns="kode, nama, kode_region, aktif" />

    Tombol pembuka: data-bs-toggle="modal" data-bs-target="#excel-import-modal".
    Lebih dari satu import di satu halaman: beri `id` berbeda (mis. id="margin-import" -> target "#margin-import-modal").
    `update-only`: import hanya memperbarui data yang sudah ada (kode tidak terdaftar ditolak).
--}}
@props(['title', 'uploadPath', 'templateUrl', 'columns', 'id' => 'excel-import', 'updateOnly' => false])

<div class="modal fade" id="{{ $id }}-modal" tabindex="-1" aria-labelledby="{{ $id }}-title" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered modal-lg">
        <form class="modal-content excel-import-form" id="{{ $id }}-form" data-upload-path="{{ $uploadPath }}" novalidate>
            <div class="modal-header">
                <h5 class="modal-title" id="{{ $id }}-title">{{ $title }}</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Tutup"></button>
            </div>
            <div class="modal-body">
                <ol class="ps-4 mb-4">
                    <li>
                        Download <a href="{{ $templateUrl }}">template Excel</a>, isi sheet <strong>Data</strong>
                        (kolom: <code>{{ $columns }}</code>). Petunjuk dan kode referensi ada di sheet <strong>Petunjuk</strong>.
                    </li>
                    @if ($updateOnly)
                        <li>Import hanya <strong>memperbarui</strong> data yang sudah ada; kode yang tidak terdaftar akan ditolak.</li>
                    @else
                        <li>Kode yang sudah ada akan <strong>diperbarui</strong>; kode baru akan <strong>dibuat</strong>.</li>
                    @endif
                    <li>Bila ada baris yang salah, <strong>tidak ada data yang disimpan</strong>. Perbaiki file lalu upload ulang.</li>
                </ol>
                <label for="{{ $id }}-file" class="form-label">File Excel (.xlsx/.xls, maks. 5 MB) <span class="text-danger">*</span></label>
                <input type="file" class="form-control" id="{{ $id }}-file" name="file" accept=".xlsx,.xls" required />

                <div class="alert alert-danger mt-4 mb-0 d-none excel-import-errors" id="{{ $id }}-errors" role="alert">
                    <div class="fw-medium mb-2">Import dibatalkan, tidak ada data yang disimpan:</div>
                    <ul class="mb-0 ps-4" style="max-height: 240px; overflow-y: auto;"></ul>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-label-secondary" data-bs-dismiss="modal">Batal</button>
                <button type="submit" class="btn btn-primary">
                    <i class="icon-base bx bx-upload me-1"></i> Upload & Import
                </button>
            </div>
        </form>
    </div>
</div>
