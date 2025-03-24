{{-- resources/views/admin/domains/edit.blade.php --}}
@extends('layouts.admin')

@section('title', 'Edit Domain')

@section('content')
    <div class="card">
        <div class="card-header">Edit Domain Information</div>
        <div class="card-body">
            <form action="{{ route('admin.domains.update', $domain) }}" method="POST">
                @csrf
                @method('PUT')

                <div class="row mb-3">
                    <div class="col-md-6">
                        <label for="name" class="form-label">Domain Name</label>
                        <input type="text" class="form-control @error('name') is-invalid @enderror" id="name" name="name" value="{{ old('name', $domain->name) }}" required>
                        @error('name')
                        <div class="invalid-feedback">{{ $message }}</div>
                        @enderror
                    </div>

                    <div class="col-md-6">
                        <label for="domain" class="form-label">Domain URL</label>
                        <input type="text" class="form-control @error('domain') is-invalid @enderror" id="domain" name="domain" value="{{ old('domain', $domain->domain) }}" required>
                        @error('domain')
                        <div class="invalid-feedback">{{ $message }}</div>
                        @enderror
                    </div>
                </div>

                <div class="row mb-3">
                    <div class="col-md-4">
                        <label for="country_code" class="form-label">Country Code</label>
                        <input type="text" class="form-control @error('country_code') is-invalid @enderror" id="country_code" name="country_code" value="{{ old('country_code', $domain->country_code) }}" required>
                        @error('country_code')
                        <div class="invalid-feedback">{{ $message }}</div>
                        @enderror
                    </div>

                    <div class="col-md-4">
                        <label for="language_code" class="form-label">Language Code</label>
                        <input type="text" class="form-control @error('language_code') is-invalid @enderror" id="language_code" name="language_code" value="{{ old('language_code', $domain->language_code) }}" required>
                        @error('language_code')
                        <div class="invalid-feedback">{{ $message }}</div>
                        @enderror
                    </div>

                    <div class="col-md-4">
                        <label for="currency" class="form-label">Currency</label>
                        <input type="text" class="form-control @error('currency') is-invalid @enderror" id="currency" name="currency" value="{{ old('currency', $domain->currency) }}" required>
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
                        <input type="text" class="form-control @error('database_name') is-invalid @enderror" id="database_name" name="database_name" value="{{ old('database_name', $domain->database_name) }}" required>
                        @error('database_name')
                        <div class="invalid-feedback">{{ $message }}</div>
                        @enderror
                    </div>

                    <div class="col-md-6">
                        <label for="database_host" class="form-label">Database Host</label>
                        <input type="text" class="form-control @error('database_host') is-invalid @enderror" id="database_host" name="database_host" value="{{ old('database_host', $domain->database_host) }}" required>
                        @error('database_host')
                        <div class="invalid-feedback">{{ $message }}</div>
                        @enderror
                    </div>
                </div>

                <div class="row mb-3">
                    <div class="col-md-6">
                        <label for="database_username" class="form-label">Database Username</label>
                        <input type="text" class="form-control @error('database_username') is-invalid @enderror" id="database_username" name="database_username" value="{{ old('database_username', $domain->database_username) }}" required>
                        @error('database_username')
                        <div class="invalid-feedback">{{ $message }}</div>
                        @enderror
                    </div>

                    <div class="col-md-6">
                        <label for="database_password" class="form-label">Database Password</label>
                        <input type="password" class="form-control @error('database_password') is-invalid @enderror" id="database_password" name="database_password" placeholder="Leave blank to keep current password">
                        @error('database_password')
                        <div class="invalid-feedback">{{ $message }}</div>
                        @enderror
                    </div>
                </div>

                <div class="form-check mb-3">
                    <input class="form-check-input" type="checkbox" id="is_active" name="is_active" value="1" {{ $domain->is_active ? 'checked' : '' }}>
                    <label class="form-check-label" for="is_active">
                        Active Domain
                    </label>
                </div>

                <div class="d-grid gap-2 d-md-flex justify-content-md-end">
                    <a href="{{ route('admin.domains.index') }}" class="btn btn-secondary">Cancel</a>
                    <button type="submit" class="btn btn-primary">Update Domain</button>
                </div>
            </form>
        </div>
    </div>
@endsection
