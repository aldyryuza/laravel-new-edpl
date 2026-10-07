@extends('layouts.app', ['vendors' => ['datatables', 'sweetalert2']])

@section('title', 'Pengajuan DPL')

@section('content')
    <x-page-header title="Pengajuan DPL" :breadcrumbs="[['label' => 'DPL']]">
        <x-slot:actions>
            @can('dpl-proposals.create')
                <a href="{{ route('dpl.proposals.create') }}" class="btn btn-primary">
                    <i class="icon-base bx bx-plus me-1"></i> Buat DPL
                </a>
            @endcan
        </x-slot:actions>
    </x-page-header>

    {{-- Flag permission hanya untuk UX (tampil/sembunyi tombol); server tetap mengecek ulang. --}}
    <div class="card" id="proposals-page" data-can-delete="{{ (int) auth()->user()->can('dpl-proposals.delete') }}"
        data-show-url="{{ url('dpl/proposals') }}">
        <div class="card-body pb-0">
            <div class="row g-4">
                <div class="col-md-3">
                    <label for="filter-status" class="form-label">Status</label>
                    <select class="form-select" id="filter-status" data-filter="proposal_status">
                        <option value="">Semua status</option>
                        @foreach ($statuses as $status)
                            <option value="{{ $status }}">{{ $status }}</option>
                        @endforeach
                    </select>
                </div>
                @if ($teams->count() > 1)
                    <div class="col-md-3">
                        <label for="filter-team" class="form-label">Team</label>
                        <select class="form-select" id="filter-team" data-filter="team_code">
                            <option value="">Semua team</option>
                            @foreach ($teams as $team)
                                <option value="{{ $team->code }}">{{ $team->name }}</option>
                            @endforeach
                        </select>
                    </div>
                @endif
                <div class="col-md-3">
                    <label for="filter-mine" class="form-label">Pembuat</label>
                    <select class="form-select" id="filter-mine" data-filter="mine">
                        <option value="">Semua</option>
                        <option value="1">Buatan saya</option>
                    </select>
                </div>
            </div>
        </div>
        <div class="card-datatable table-responsive">
            <table class="table" id="proposals-table">
                <thead>
                    <tr>
                        <th>Nomor DPL</th>
                        <th>Outlet</th>
                        <th>Team</th>
                        <th>Periode</th>
                        <th>Item</th>
                        <th>Status</th>
                        <th>Pembuat</th>
                        <th class="text-end">Aksi</th>
                    </tr>
                </thead>
                <tbody></tbody>
            </table>
        </div>
    </div>
@endsection

@push('scripts')
    <script src="{{ asset_v('js/pages/dpl/proposals.js') }}"></script>
@endpush
