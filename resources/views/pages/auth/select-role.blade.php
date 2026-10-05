@extends('layouts.blank')

@section('title', 'Pilih Role')

@push('styles')
    <link rel="stylesheet" href="{{ theme_asset('vendor/css/pages/page-auth.css') }}" />
@endpush

@section('content')
    <div class="card px-sm-6 px-0">
        <div class="card-body">
            <h4 class="mb-1">Pilih role</h4>
            <p class="mb-6">
                Halo, {{ auth()->user()->name }}. Akun Anda memiliki beberapa role. Pilih role yang ingin digunakan;
                role dapat diganti kapan saja dari menu profil di pojok kanan atas.
            </p>

            <form method="POST" action="{{ route('active-role.store') }}">
                @csrf

                <div class="list-group mb-6">
                    @foreach ($roles as $role)
                        <label class="list-group-item list-group-item-action d-flex align-items-start gap-3">
                            <input class="form-check-input mt-1" type="radio" name="role_id" value="{{ $role->id }}"
                                @checked(old('role_id', $activeRoleId ?? $roles->first()->id) == $role->id) />
                            <span>
                                <span class="fw-medium d-block">{{ $role->name }}</span>
                                @if ($role->description)
                                    <small class="text-body-secondary">{{ $role->description }}</small>
                                @endif
                            </span>
                        </label>
                    @endforeach
                </div>

                @error('role_id')
                    <div class="alert alert-danger">{{ $message }}</div>
                @enderror

                <button type="submit" class="btn btn-primary d-grid w-100 mb-4">Lanjutkan</button>
            </form>

            <form method="POST" action="{{ route('logout') }}" class="text-center">
                @csrf
                <button type="submit" class="btn btn-link p-0">Keluar</button>
            </form>
        </div>
    </div>
@endsection
