@extends('layouts.app', ['vendors' => ['datatables', 'sweetalert2']])

@section('title', 'Team')

@section('content')
    <x-page-header title="Team" subtitle="Team sales. Penempatan user ke team diatur dari halaman Wilayah User."
        :breadcrumbs="[['label' => 'Master Organisasi']]">
        <x-slot:actions>
            @can('teams.create')
                <button type="button" class="btn btn-primary" id="btn-add-team">
                    <i class="icon-base bx bx-plus me-1"></i> Tambah Team
                </button>
            @endcan
        </x-slot:actions>
    </x-page-header>

    {{-- Flag permission hanya untuk UX (tampil/sembunyi tombol); server tetap mengecek ulang. --}}
    <div class="card" id="teams-page" data-can-update="{{ (int) auth()->user()->can('teams.update') }}"
        data-can-delete="{{ (int) auth()->user()->can('teams.delete') }}">
        <div class="card-datatable table-responsive">
            <table class="table" id="teams-table">
                <thead>
                    <tr>
                        <th>Kode</th>
                        <th>Nama</th>
                        <th>Penugasan</th>
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
    <div class="modal fade" id="team-modal" tabindex="-1" aria-labelledby="team-modal-title" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered">
            <form class="modal-content" id="team-form" novalidate>
                <div class="modal-header">
                    <h5 class="modal-title" id="team-modal-title">Tambah Team</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Tutup"></button>
                </div>
                <div class="modal-body">
                    <div class="mb-4">
                        <label for="team-code" class="form-label">Kode <span class="text-danger">*</span></label>
                        <input type="text" class="form-control" id="team-code" name="code" maxlength="20" required />
                    </div>
                    <div class="mb-4">
                        <label for="team-name" class="form-label">Nama <span class="text-danger">*</span></label>
                        <input type="text" class="form-control" id="team-name" name="name" maxlength="100" required />
                    </div>
                    <input type="hidden" name="is_active" value="0" />
                    <div class="form-check form-switch">
                        <input class="form-check-input" type="checkbox" id="team-active" name="is_active" value="1" checked />
                        <label class="form-check-label" for="team-active">Aktif</label>
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
    <script src="{{ asset_v('js/pages/teams.js') }}"></script>
@endpush
