@extends('layouts.blank')

@section('title', 'Login')

@push('styles')
    <link rel="stylesheet" href="{{ theme_asset('vendor/css/pages/page-auth.css') }}" />
@endpush

@section('content')
    <div class="card px-sm-6 px-0">
        <div class="card-body">
            <div class="app-brand justify-content-center mb-6">
                <img src="{{ theme_asset(config('sneat.layout.logo')) }}" alt="{{ config('sneat.layout.brand') }}"
                    class="app-brand-img" />
            </div>

            <h4 class="mb-1">Selamat datang</h4>
            <p class="mb-6">Masuk menggunakan NIK dan password Anda.</p>

            <form method="POST" action="{{ route('login.store') }}" class="mb-6">
                @csrf

                <div class="mb-6">
                    <label for="nik" class="form-label">NIK <span class="text-danger">*</span></label>
                    <input type="text" id="nik" name="nik" value="{{ old('nik') }}"
                        class="form-control @error('nik') is-invalid @enderror" autocomplete="username" autofocus required />
                    @error('nik')
                        <div class="invalid-feedback">{{ $message }}</div>
                    @enderror
                </div>

                <div class="mb-6">
                    <label for="password" class="form-label">Password <span class="text-danger">*</span></label>
                    <input type="password" id="password" name="password"
                        class="form-control @error('password') is-invalid @enderror" autocomplete="current-password" required />
                    @error('password')
                        <div class="invalid-feedback">{{ $message }}</div>
                    @enderror
                </div>

                <div class="mb-6">
                    <div class="form-check">
                        <input class="form-check-input" type="checkbox" id="remember" name="remember" value="1" />
                        <label class="form-check-label" for="remember">Ingat saya</label>
                    </div>
                </div>

                <button type="submit" class="btn btn-primary d-grid w-100">Masuk</button>
            </form>
        </div>
    </div>
@endsection
