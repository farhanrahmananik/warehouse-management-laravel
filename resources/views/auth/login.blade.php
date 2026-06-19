@extends('layouts.auth')

@section('title', 'Login - ' . config('app.name'))

@section('content')
    <div class="row justify-content-center">
        <div class="col-12 col-sm-10 col-md-7 col-lg-5 col-xl-4">
            <div class="card shadow-sm border-0">
                <div class="card-body p-4">
                    <div class="mb-4 text-center">
                        <h1 class="h4 mb-1">{{ config('app.name') }}</h1>
                        <p class="text-muted mb-0">Sign in to continue</p>
                    </div>

                    <form method="POST" action="{{ route('login.store') }}" novalidate>
                        @csrf

                        <div class="mb-3">
                            <label for="email" class="form-label">Email address</label>
                            <input
                                id="email"
                                type="email"
                                name="email"
                                value="{{ old('email') }}"
                                class="form-control @error('email') is-invalid @enderror"
                                autocomplete="email"
                                autofocus
                                required
                            >
                            @error('email')
                                <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                        </div>

                        <div class="mb-3">
                            <label for="password" class="form-label">Password</label>
                            <input
                                id="password"
                                type="password"
                                name="password"
                                class="form-control @error('password') is-invalid @enderror"
                                autocomplete="current-password"
                                required
                            >
                            @error('password')
                                <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                        </div>

                        <div class="form-check mb-4">
                            <input
                                id="remember"
                                type="checkbox"
                                name="remember"
                                value="1"
                                class="form-check-input @error('remember') is-invalid @enderror"
                                @checked(old('remember'))
                            >
                            <label for="remember" class="form-check-label">Remember me</label>
                            @error('remember')
                                <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                        </div>

                        <button type="submit" class="btn btn-primary w-100">Log in</button>
                    </form>
                </div>
            </div>
        </div>
    </div>
@endsection
