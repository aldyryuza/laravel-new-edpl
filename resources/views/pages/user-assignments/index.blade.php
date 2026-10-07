@extends('layouts.app', ['vendors' => ['datatables', 'select2', 'sweetalert2']])

@section('title', 'Wilayah User')

@section('content')
    <x-page-header title="Wilayah User" :breadcrumbs="[['label' => 'Master Organisasi']]">
        <x-slot:actions>
            @can('user-assignments.create')
                <button type="button" class="btn btn-primary" id="btn-add-assignment">
                    <i class="icon-base bx bx-plus me-1"></i> Tambah Wilayah
                </button>
            @endcan
        </x-slot:actions>
    </x-page-header>

    <div class="alert alert-info d-flex align-items-start" role="alert">
        <i class="icon-base bx bx-info-circle me-2 mt-1"></i>
        <div>
            Wilayah menentukan outlet dan distributor yang terlihat user: <strong>Nasional</strong> melihat semua,
            <strong>Zona/Region/Area</strong> melihat data di wilayah tersebut (beserta turunannya). User boleh punya lebih
            dari satu wilayah. User tanpa wilayah aktif <strong>tidak melihat data</strong> outlet maupun distributor.
        </div>
    </div>

    {{-- Flag permission hanya untuk UX (tampil/sembunyi tombol); server tetap mengecek ulang. --}}
    <div class="card" id="assignments-page" data-can-update="{{ (int) auth()->user()->can('user-assignments.update') }}"
        data-can-delete="{{ (int) auth()->user()->can('user-assignments.delete') }}">
        <div class="card-body pb-0">
            <div class="row">
                <div class="col-md-4">
                    <label for="filter-team" class="form-label">Filter Team</label>
                    <select class="form-select" id="filter-team">
                        <option value="">Semua team</option>
                        @foreach ($teams as $team)
                            <option value="{{ $team->id }}">{{ $team->name }} ({{ $team->code }})</option>
                        @endforeach
                    </select>
                </div>
            </div>
        </div>
        <div class="card-datatable table-responsive">
            <table class="table" id="assignments-table">
                <thead>
                    <tr>
                        <th>User</th>
                        <th>Wilayah</th>
                        <th>Team</th>
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
                    <h5 class="modal-title" id="assignment-modal-title">Tambah Wilayah</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Tutup"></button>
                </div>
                <div class="modal-body">
                    <div class="mb-4">
                        <label for="assignment-user" class="form-label">User <span class="text-danger">*</span></label>
                        <select class="form-select" id="assignment-user" name="user_id" required>
                            <option value="">Pilih user</option>
                            @foreach ($users as $user)
                                <option value="{{ $user->id }}">{{ $user->name }} ({{ $user->nik }}){{ $user->is_active ? '' : ' - nonaktif' }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="mb-4">
                        <label class="form-label d-block">Wilayah <span class="text-danger">*</span></label>
                        @foreach (['national' => 'Nasional', 'zone' => 'Zona', 'region' => 'Region', 'area' => 'Area'] as $value => $label)
                            <div class="form-check form-check-inline">
                                <input class="form-check-input" type="radio" name="scope_type" id="scope-{{ $value }}" value="{{ $value }}"
                                    @checked($value === 'area') />
                                <label class="form-check-label" for="scope-{{ $value }}">{{ $label }}</label>
                            </div>
                        @endforeach
                    </div>
                    <div class="mb-4 d-none" data-scope="zone">
                        <label for="assignment-zone" class="form-label">Zona <span class="text-danger">*</span></label>
                        <select class="form-select" id="assignment-zone" name="zone_id">
                            <option value="">Pilih zona</option>
                            @foreach ($zones as $zone)
                                <option value="{{ $zone->id }}">{{ $zone->name }} ({{ $zone->code }}){{ $zone->is_active ? '' : ' - nonaktif' }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="mb-4 d-none" data-scope="region">
                        <label for="assignment-region" class="form-label">Region <span class="text-danger">*</span></label>
                        <select class="form-select" id="assignment-region" name="region_id">
                            <option value="">Pilih region</option>
                            @foreach ($regions as $region)
                                <option value="{{ $region->id }}">
                                    {{ $region->name }} ({{ $region->code }}) - {{ $region->zone?->name }}{{ $region->is_active ? '' : ' - nonaktif' }}
                                </option>
                            @endforeach
                        </select>
                    </div>
                    <div class="mb-4" data-scope="area">
                        <label for="assignment-area" class="form-label">Area <span class="text-danger">*</span></label>
                        <select class="form-select" id="assignment-area" name="area_id">
                            <option value="">Pilih area</option>
                            @foreach ($areas as $area)
                                <option value="{{ $area->id }}">
                                    {{ $area->name }} ({{ $area->code }}) - {{ $area->region?->name }}{{ $area->is_active ? '' : ' - nonaktif' }}
                                </option>
                            @endforeach
                        </select>
                    </div>
                    <div class="mb-4">
                        <label for="assignment-team" class="form-label">Team</label>
                        <select class="form-select" id="assignment-team" name="team_id">
                            <option value="">Tanpa team</option>
                            @foreach ($teams as $team)
                                <option value="{{ $team->id }}">{{ $team->name }} ({{ $team->code }}){{ $team->is_active ? '' : ' - nonaktif' }}</option>
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
    <script src="{{ asset_v('js/pages/user-assignments.js') }}"></script>
@endpush
