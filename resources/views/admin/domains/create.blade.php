{{-- resources/views/admin/domains/create.blade.php --}}
@extends('layouts.admin')

@section('title', 'Create Domain')

@section('content')
    <div class="card">
        <div class="card-header">Domain Information</div>
        <div class="card-body">
            <form action="{{ route('admin.domains.store') }}" method="POST">
                @csrf

                <div class="row mb-3">
                    <div class="col-md-6">
                        <label for="name" class="form-label">Domain Name</label>
                        <input type="text" class="form-control @error('name') is-invalid @enderror" id="name" name="name" value="{{ old('name') }}" required>
                        @error('name')
                        <div class="invalid-feedback">{{ $message }}</div>
                        @enderror
                    </div>

                    <div class="col-md-6">
                        <label for="domain" class="form-label">Domain URL</label>
                        <input type="text" class="form-control @error('domain') is-invalid @enderror" id="domain" name="domain" value="{{ old('domain') }}" required>
                        @error('domain')
                        <div class="invalid-feedback">{{ $message }}</div>
                        @enderror
                    </div>
                </div>

                <div class="row mb-3">
                    <div class="col-md-4">
                        <label for="country_code" class="form-label">Country Code</label>
                        <input type="text" class="form-control @error('country_code') is-invalid @enderror" id="country_code" name="country_code" value="{{ old('country_code') }}" required>
                        @error('country_code')
                        <div class="invalid-feedback">{{ $message }}</div>
                        @enderror
                    </div>

                    <div class="col-md-4">
                        <label for="language_code" class="form-label">Language Code</label>
                        <input type="text" class="form-control @error('language_code') is-invalid @enderror" id="language_code" name="language_code" value="{{ old('language_code') }}" required>
                        @error('language_code')
                        <div class="invalid-feedback">{{ $message }}</div>
                        @enderror
                    </div>

                    <div class="col-md-4">
                        <label for="currency" class="form-label">Currency</label>
                        <input type="text" class="form-control @error('currency') is-invalid @enderror" id="currency" name="currency" value="{{ old('currency', 'EUR') }}" required>
                        @error('currency')
                        <div class="invalid-feedback">{{ $message }}</div>
                        @enderror
                    </div>
                </div>

                <hr>
                <h5>Database Connection</h5>

                <div class="row mb-3">
                    <div class="col-md-6">
                        <label for="database_name" class="form-label">Database Name</label>
                        <input type="text" class="form-control @error('database_name') is-invalid @enderror" id="database_name" name="database_name" value="{{ old('database_name') }}" required>
                        @error('database_name')
                        <div class="invalid-feedback">{{ $message }}</div>
                        @enderror
                    </div>

                    <div class="col-md-6">
                        <label for="database_host" class="form-label">Database Host</label>
                        <input type="text" class="form-control @error('database_host') is-invalid @enderror" id="database_host" name="database_host" value="{{ old('database_host', 'localhost') }}" required>
                        @error('database_host')
                        <div class="invalid-feedback">{{ $message }}</div>
                        @enderror
                    </div>
                </div>

                <div class="row mb-3">
                    <div class="col-md-6">
                        <label for="database_username" class="form-label">Database Username</label>
                        <input type="text" class="form-control @error('database_username') is-invalid @enderror" id="database_username" name="database_username" value="{{ old('database_username') }}" required>
                        @error('database_username')
                        <div class="invalid-feedback">{{ $message }}</div>
                        @enderror
                    </div>

                    <div class="col-md-6">
                        <label for="database_password" class="form-label">Database Password</label>
                        <input type="password" class="form-control @error('database_password') is-invalid @enderror" id="database_password" name="database_password" required>
                        @error('database_password')
                        <div class="invalid-feedback">{{ $message }}</div>
                        @enderror
                    </div>
                </div>

                <div class="form-check mb-3">
                    <input class="form-check-input" type="checkbox" id="is_active" name="is_active" value="1" checked>
                    <label class="form-check-label" for="is_active">
                        Active Domain
                    </label>
                </div>

                <div class="d-grid gap-2 d-md-flex justify-content-md-end">
                    <a href="{{ route('admin.domains.index') }}" class="btn btn-secondary">Cancel</a>
                    <button type="submit" class="btn btn-primary">Create Domain</button>
                </div>
            </form>
        </div>
    </div>
@endsection
