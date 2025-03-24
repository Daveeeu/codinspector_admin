{{-- resources/views/admin/packages/create.blade.php --}}
@extends('layouts.admin')

@section('title', 'Create Package')

@section('content')
    <div class="card">
        <div class="card-header">Package Information</div>
        <div class="card-body">
            <form action="{{ route('admin.packages.store') }}" method="POST">
                @csrf

                <div class="row mb-3">
                    <div class="col-md-12">
                        <label for="name" class="form-label">Package Name</label>
                        <input type="text" class="form-control @error('name') is-invalid @enderror" id="name" name="name" value="{{ old('name') }}" required>
                        @error('name')
                        <div class="invalid-feedback">{{ $message }}</div>
                        @enderror
                    </div>
                </div>

                <div class="row mb-3">
                    <div class="col-md-12">
                        <label for="description" class="form-label">Description</label>
                        <textarea class="form-control @error('description') is-invalid @enderror" id="description" name="description" rows="3">{{ old('description') }}</textarea>
                        @error('description')
                        <div class="invalid-feedback">{{ $message }}</div>
                        @enderror
                    </div>
                </div>

                <div class="row mb-3">
                    <div class="col-md-12">
                        <label for="billing_type" class="form-label">Billing Type</label>
                        <select class="form-select @error('billing_type') is-invalid @enderror" id="billing_type" name="billing_type" required>
                            <option value="">Select billing type</option>
                            <option value="monthly" {{ old('billing_type') == 'monthly' ? 'selected' : '' }}>Monthly</option>
                            <option value="yearly" {{ old('billing_type') == 'yearly' ? 'selected' : '' }}>Yearly</option>
                            <option value="unit" {{ old('billing_type') == 'unit' ? 'selected' : '' }}>Unit-based</option>
                        </select>
                        @error('billing_type')
                        <div class="invalid-feedback">{{ $message }}</div>
                        @enderror
                    </div>
                </div>

                <div class="row mb-3 monthly-price-container" style="{{ old('billing_type') == 'monthly' || old('billing_type') == 'unit' ? '' : 'display: none;' }}">
                    <div class="col-md-6">
                        <label for="monthly_price" class="form-label">Monthly Price ({{ Auth::user()->currentDomain->currency }})</label>
                        <input type="number" step="0.01" class="form-control @error('monthly_price') is-invalid @enderror" id="monthly_price" name="monthly_price" value="{{ old('monthly_price') }}">
                        @error('monthly_price')
                        <div class="invalid-feedback">{{ $message }}</div>
                        @enderror
                    </div>
                </div>

                <div class="row mb-3 yearly-price-container" style="{{ old('billing_type') == 'yearly' ? '' : 'display: none;' }}">
                    <div class="col-md-6">
                        <label for="yearly_price" class="form-label">Yearly Price ({{ Auth::user()->currentDomain->currency }})</label>
                        <input type="number" step="0.01" class="form-control @error('yearly_price') is-invalid @enderror" id="yearly_price" name="yearly_price" value="{{ old('yearly_price') }}">
                        @error('yearly_price')
                        <div class="invalid-feedback">{{ $message }}</div>
                        @enderror
                    </div>
                </div>

                <div class="row mb-3 unit-price-container" style="{{ old('billing_type') == 'unit' ? '' : 'display: none;' }}">
                    <div class="col-md-6">
                        <label for="unit_price" class="form-label">Unit Price ({{ Auth::user()->currentDomain->currency }})</label>
                        <input type="number" step="0.01" class="form-control @error('unit_price') is-invalid @enderror" id="unit_price" name="unit_price" value="{{ old('unit_price') }}">
                        @error('unit_price')
                        <div class="invalid-feedback">{{ $message }}</div>
                        @enderror
                    </div>
                </div>

                <div class="form-check mb-3">
                    <input class="form-check-input" type="checkbox" id="is_active" name="is_active" value="1" checked>
                    <label class="form-check-label" for="is_active">
                        Active Package
                    </label>
                </div>

                <div class="d-grid gap-2 d-md-flex justify-content-md-end">
                    <a href="{{ route('admin.packages.index') }}" class="btn btn-secondary">Cancel</a>
                    <button type="submit" class="btn btn-primary">Create Package</button>
                </div>
            </form>
        </div>
    </div>
@endsection

@section('scripts')
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            const billingTypeSelect = document.getElementById('billing_type');
            const monthlyPriceContainer = document.querySelector('.monthly-price-container');
            const yearlyPriceContainer = document.querySelector('.yearly-price-container');
            const unitPriceContainer = document.querySelector('.unit-price-container');

            billingTypeSelect.addEventListener('change', function() {
                // Hide all price containers first
                monthlyPriceContainer.style.display = 'none';
                yearlyPriceContainer.style.display = 'none';
                unitPriceContainer.style.display = 'none';

                // Show the relevant price container based on the selected billing type
                if (this.value === 'monthly') {
                    monthlyPriceContainer.style.display = '';
                } else if (this.value === 'yearly') {
                    yearlyPriceContainer.style.display = '';
                } else if (this.value === 'unit') {
                    monthlyPriceContainer.style.display = '';
                    unitPriceContainer.style.display = '';
                }
            });
        });
    </script>
@endsection
