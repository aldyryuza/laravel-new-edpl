@extends('layouts.app', ['vendors' => ['sweetalert2']])

@section('title', 'Hak Akses '.$role->name)

@section('content')
    <x-page-header :title="'Hak Akses: '.$role->name"
        :breadcrumbs="[['label' => 'Pengaturan'], ['label' => 'Role & Hak Akses', 'url' => route('roles.index')]]"
        subtitle="Centang action yang boleh dilakukan role ini pada setiap menu.">
        <x-slot:actions>
            <a href="{{ route('roles.index') }}" class="btn btn-label-secondary">
                <i class="icon-base bx bx-arrow-back me-1"></i> Kembali
            </a>
            @can('roles.update')
                <button type="button" class="btn btn-primary" id="btn-save-access" disabled>
                    <i class="icon-base bx bx-save me-1"></i> Simpan
                </button>
            @endcan
        </x-slot:actions>
    </x-page-header>

    <div class="card" id="role-access-page" data-role-id="{{ $role->id }}"
        data-actions='@json(\App\Models\RoleMenuAccess::ACTIONS)'
        data-can-update="{{ (int) auth()->user()->can('roles.update') }}">
        <div class="table-responsive">
            <table class="table table-hover mb-0" id="role-access-table">
                <thead>
                    <tr>
                        <th>Menu</th>
                        @foreach (\App\Models\RoleMenuAccess::ACTIONS as $action)
                            <th class="text-center text-capitalize">
                                {{ $action }}
                                @can('roles.update')
                                    <div>
                                        <input type="checkbox" class="form-check-input check-column"
                                            data-action="{{ $action }}" aria-label="Centang semua {{ $action }}" />
                                    </div>
                                @endcan
                            </th>
                        @endforeach
                        @can('roles.update')
                            <th class="text-center">Semua</th>
                        @endcan
                    </tr>
                </thead>
                <tbody>
                    <tr>
                        <td colspan="8" class="text-center text-body-secondary py-6">Memuat...</td>
                    </tr>
                </tbody>
            </table>
        </div>
    </div>
@endsection

@push('scripts')
    <script src="{{ asset_v('js/pages/role-access.js') }}"></script>
@endpush
