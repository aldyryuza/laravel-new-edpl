@extends('layouts.app', ['vendors' => ['datatables', 'select2', 'sweetalert2']])

@section('title', 'Approver DPL')

@section('content')
    <x-page-header title="Approver DPL" :breadcrumbs="[['label' => 'Master Organisasi']]">
        <x-slot:actions>
            @can('approver-assignments.create')
                <button type="button" class="btn btn-primary" id="btn-add-assignment">
                    <i class="icon-base bx bx-plus me-1"></i> Tambah Approver
                </button>
            @endcan
        </x-slot:actions>
    </x-page-header>

    <div class="alert alert-info d-flex align-items-start" role="alert">
        <i class="icon-base bx bx-info-circle me-2 mt-1"></i>
        <div>
            Approver di-resolve dari scope: <strong>Region</strong> (mis. RM), <strong>Zona</strong> (mis. NSM), atau
            <strong>Global</strong> (mis. GM/SMD/DMD). Satu role + scope boleh punya lebih dari satu approver aktif;
            cukup salah satu yang approve. Perubahan di sini tidak mengubah approval yang sudah berjalan.
        </div>
    </div>

    {{-- Flag permission hanya untuk UX (tampil/sembunyi tombol); server tetap mengecek ulang. --}}
    <div class="card" id="assignments-page" data-can-update="{{ (int) auth()->user()->can('approver-assignments.update') }}"
        data-can-delete="{{ (int) auth()->user()->can('approver-assignments.delete') }}">
        <div class="card-body pb-0">
            <div class="row">
                <div class="col-md-4">
                    <label for="filter-role" class="form-label">Filter Role</label>
                    <select class="form-select" id="filter-role">
                        <option value="">Semua role</option>
                        @foreach ($roles as $role)
                            <option value="{{ $role->id }}">{{ $role->name }}</option>
                        @endforeach
                    </select>
                </div>
            </div>
        </div>
        <div class="card-datatable table-responsive">
            <table class="table" id="assignments-table">
                <thead>
                    <tr>
                        <th>Role</th>
                        <th>Scope</th>
                        <th>User</th>
                        <th>Berlaku Mulai</th>
                        <th>Berlaku Sampai</th>
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
    <div class="modal fade" id="assignment-modal" tabindex="-1" aria-labelledby="assignment-modal-title" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered">
            <form class="modal-content" id="assignment-form" novalidate>
                <div class="modal-header">
                    <h5 class="modal-title" id="assignment-modal-title">Tambah Approver</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Tutup"></button>
                </div>
                <div class="modal-body">
                    @if ($roles->isEmpty())
                        <div class="alert alert-warning" role="alert">
                            Belum ada role pada workflow approval. Jalankan ApprovalReferenceSeeder.
                        </div>
                    @endif
                    <div class="mb-4">
                        <label for="assignment-role" class="form-label">Role <span class="text-danger">*</span></label>
                        <select class="form-select" id="assignment-role" name="role_id" required>
                            <option value="">Pilih role</option>
                            @foreach ($roles as $role)
                                <option value="{{ $role->id }}">{{ $role->name }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="mb-4">
                        <label class="form-label d-block">Scope <span class="text-danger">*</span></label>
                        <div class="form-check form-check-inline">
                            <input class="form-check-input" type="radio" name="scope_type" id="scope-region" value="region" checked />
                            <label class="form-check-label" for="scope-region">Region</label>
                        </div>
                        <div class="form-check form-check-inline">
                            <input class="form-check-input" type="radio" name="scope_type" id="scope-zone" value="zone" />
                            <label class="form-check-label" for="scope-zone">Zona</label>
                        </div>
                        <div class="form-check form-check-inline">
                            <input class="form-check-input" type="radio" name="scope_type" id="scope-global" value="global" />
                            <label class="form-check-label" for="scope-global">Global</label>
                        </div>
                    </div>
                    <div class="mb-4" data-scope="region">
                        <label for="assignment-region" class="form-label">Region <span class="text-danger">*</span></label>
                        <select class="form-select" id="assignment-region" name="region_id">
                            <option value="">Pilih region</option>
                            @foreach ($regions as $region)
                                <option value="{{ $region->id }}">{{ $region->name }} ({{ $region->code }})</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="mb-4 d-none" data-scope="zone">
                        <label for="assignment-zone" class="form-label">Zona <span class="text-danger">*</span></label>
                        <select class="form-select" id="assignment-zone" name="zone_id">
                            <option value="">Pilih zona</option>
                            @foreach ($zones as $zone)
                                <option value="{{ $zone->id }}">{{ $zone->name }} ({{ $zone->code }})</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="mb-4">
                        <label for="assignment-user" class="form-label">User <span class="text-danger">*</span></label>
                        <select class="form-select" id="assignment-user" name="user_id" required>
                            <option value="">Pilih user</option>
                            @foreach ($users as $user)
                                <option value="{{ $user->id }}">{{ $user->name }} ({{ $user->nik }})</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="row g-4 mb-4">
                        <div class="col-sm-6">
                            <label for="assignment-from" class="form-label">Berlaku Mulai</label>
                            <input type="date" class="form-control" id="assignment-from" name="valid_from" />
                        </div>
                        <div class="col-sm-6">
                            <label for="assignment-until" class="form-label">Berlaku Sampai</label>
                            <input type="date" class="form-control" id="assignment-until" name="valid_until" />
                        </div>
                    </div>
                    <input type="hidden" name="is_active" value="0" />
                    <div class="form-check form-switch">
                        <input class="form-check-input" type="checkbox" id="assignment-active" name="is_active" value="1" checked />
                        <label class="form-check-label" for="assignment-active">Aktif</label>
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
    <script src="{{ asset_v('js/pages/dpl/approver-assignments.js') }}"></script>
@endpush
