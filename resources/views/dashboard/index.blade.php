@extends('layouts.auth')

@section('title', 'Dashboard - ' . config('app.name'))

@section('content')
    <div class="row justify-content-center">
        <div class="col-12 col-lg-8">
            <div class="card shadow-sm border-0">
                <div class="card-body p-4">
                    <div class="d-flex flex-column flex-md-row justify-content-between gap-3">
                        <div>
                            <h1 class="h3 mb-2">Warehouse Management Dashboard</h1>
                            <p class="text-muted mb-0">This is a protected placeholder page.</p>
                        </div>

                        <form method="POST" action="{{ route('logout') }}">
                            @csrf
                            <button type="submit" class="btn btn-outline-danger">Log out</button>
                        </form>
                    </div>

                    <hr class="my-4">

                    <dl class="row mb-0">
                        <dt class="col-sm-3">Name</dt>
                        <dd class="col-sm-9">{{ auth()->user()->name }}</dd>

                        <dt class="col-sm-3">Email</dt>
                        <dd class="col-sm-9">{{ auth()->user()->email }}</dd>
                    </dl>
                </div>
            </div>
        </div>
    </div>
@endsection
