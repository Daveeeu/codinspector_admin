{{-- resources/views/admin/packages/edit.blade.php --}}
@extends('layouts.admin')

@section('title', 'Edit Package')

@section('content')
    <div class="card">
        <div class="card-header">Edit Package Information</div>
        <div class="card-body">
            <form action="{{ route('admin.packages.update', $package) }}" method="POST">
                @csrf
                @method('PUT')

                <div class="row mb-3">
                    <div class="col-md-12">
                        <label for="name" class="form-label">Package Name</label>
                        <input type="text" class="form-control @error('name') is-invalid @enderror" id="name" name="name" value="{{ old('name', $package->name) }}" required>
                        @error('name')
                        <div class="invalid-feedback">{{ $message }}</div>
                        @enderror
                    </div>
                </div>

                <div class="row mb-3">
                    <div class="col-md-12">
                        <label for="description" class="form-label">Description</label>
                        <textarea class="form-control @error('description') is-invalid @enderror" id="description" name="description" rows="3">{{ old('description', $package->description) }}</textarea>
                        @error('description')
                        <div class="invalid-feedback">{{ $message }}</div>
                        @enderror
                    </div>
                </div>

                <div class="row mb-3">
                    <div class="col-md-6">
                        <label class="form-label">Billing Type</label>
                        <input type="text" class="form-control" value="{{ ucfirst($package->billing_type) }}" disabled>
                        <small class="text-muted">Billing type cannot be changed after creation.</small>
                    </div>
                </div>

                @if($package->billing_type == 'monthly' || $package->billing_type == 'unit')
                    <div class="row mb-3">
                        <div class="col-md-6">
                            <label class="form-label">Monthly Price ({{ $package->domain->currency }})</label>
                            <input type="text" class="form-control" value="{{ $package->monthly_price }}" disabled>
                        </div>
                    </div>
                @endif

                @if($package->billing_type == 'yearly')
                    <div class="row mb-3">
                        <div class="col-md-6">
                            <label class="form-label">Yearly Price ({{ $package->domain->currency }})</label>
                            <input type="text" class="form-control" value="{{ $package->yearly_price }}" disabled>
                        </div>
                    </div>
                @endif

                @if($package->billing_type == 'unit')
                    <div class="row mb-3">
                        <div class="col-md-6">
                            <label class="form-label">Unit Price ({{ $package->domain->currency }})</label>
                            <input type="text" class="form-control" value="{{ $package->unit_price }}" disabled>
                        </div>
                    </div>
                @endif

                <div class="form-check mb-3">
                    <input class="form-check-input" type="checkbox" id="is_active" name="is_active" value="1" {{ $package->is_active ? 'checked' : '' }}>
                    <label class="form-check-label" for="is_active">
                        Active Package
                    </label>
                </div>

                <div class="d-grid gap-2 d-md-flex justify-content-md-end">
                    <a href="{{ route('admin.packages.index') }}" class="btn btn-secondary">Cancel</a>
                    <button type="submit" class="btn btn-primary">Update Package</button>
                </div>
            </form>
        </div>
    </div>
@endsection
