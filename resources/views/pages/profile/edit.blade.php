@extends('layouts.app', ['vendors' => ['sweetalert2']])

@section('title', 'Profil Saya')

@section('content')
    <x-page-header title="Profil Saya" subtitle="NIK dan role dikelola oleh administrator." />

    <x-alert />

    <div class="row g-6">
        <div class="col-lg-6">
            <div class="card h-100">
                <h5 class="card-header">Data Profil</h5>
                <div class="card-body">
                    <form id="profile-form" novalidate>
                        <div class="mb-4">
                            <label for="profile-nik" class="form-label">NIK</label>
                            <input type="text" class="form-control" id="profile-nik" value="{{ $user->nik }}" readonly
                                disabled />
                        </div>
                        <div class="mb-4">
                            <label for="profile-name" class="form-label">Nama <span class="text-danger">*</span></label>
                            <input type="text" class="form-control" id="profile-name" name="name"
                                value="{{ $user->name }}" maxlength="255" required />
                        </div>
                        <div class="mb-4">
                            <label for="profile-email" class="form-label">Email <span class="text-danger">*</span></label>
                            <input type="email" class="form-control" id="profile-email" name="email"
                                value="{{ $user->email }}" maxlength="255" required />
                        </div>
                        <div class="mb-6">
                            <span class="form-label d-block">Role</span>
                            @forelse ($user->roles as $role)
                                <span class="badge {{ $user->activeRole()?->is($role) ? 'bg-primary' : 'bg-label-primary' }} me-1">
                                    {{ $role->name }}
                                    @if ($user->activeRole()?->is($role))
                                        (aktif)
                                    @endif
                                </span>
                            @empty
                                <span class="text-body-secondary">Belum memiliki role.</span>
                            @endforelse
                        </div>
                        <button type="submit" class="btn btn-primary">Simpan Profil</button>
                    </form>
                </div>
            </div>
        </div>

        <div class="col-lg-6">
            <div class="card h-100">
                <h5 class="card-header">Ganti Password</h5>
                <div class="card-body">
                    <form id="password-form" novalidate>
                        <div class="mb-4">
                            <label for="current-password" class="form-label">Password Saat Ini <span
                                    class="text-danger">*</span></label>
                            <input type="password" class="form-control" id="current-password" name="current_password"
                                autocomplete="current-password" required />
                        </div>
                        <div class="mb-4">
                            <label for="new-password" class="form-label">Password Baru <span
                                    class="text-danger">*</span></label>
                            <input type="password" class="form-control" id="new-password" name="password"
                                autocomplete="new-password" required />
                            <div class="form-text">Minimal 8 karakter.</div>
                        </div>
                        <div class="mb-6">
                            <label for="new-password-confirmation" class="form-label">Konfirmasi Password Baru <span
                                    class="text-danger">*</span></label>
                            <input type="password" class="form-control" id="new-password-confirmation"
                                name="password_confirmation" autocomplete="new-password" required />
                        </div>
                        <button type="submit" class="btn btn-primary">Ganti Password</button>
                    </form>
                </div>
            </div>
        </div>
    </div>
@endsection

@push('scripts')
    <script src="{{ asset_v('js/pages/profile.js') }}"></script>
@endpush
