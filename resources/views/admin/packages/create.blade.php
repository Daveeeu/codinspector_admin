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

                <!-- Max Queries Section - Only for Monthly and Yearly -->
                <div class="row mb-3 max-queries-container" style="{{ old('billing_type') == 'monthly' || old('billing_type') == 'yearly' ? '' : 'display: none;' }}">
                    <div class="col-md-6">
                        <label for="max_queries" class="form-label">Maximum Queries</label>
                        <input type="number" min="1" class="form-control @error('max_queries') is-invalid @enderror" id="max_queries" name="max_queries" value="{{ old('max_queries') }}">
                        <small class="text-muted">Maximum number of queries allowed for this package</small>
                        @error('max_queries')
                        <div class="invalid-feedback">{{ $message }}</div>
                        @enderror
                    </div>
                </div>

                <!-- Premium Flag -->
                <div class="form-check mb-3">
                    <input class="form-check-input" type="checkbox" id="is_premium" name="is_premium" value="1" {{ old('is_premium') ? 'checked' : '' }}>
                    <label class="form-check-label" for="is_premium">
                        Premium Package
                    </label>
                    <small class="d-block text-muted">Mark this package as premium to give users additional benefits</small>
                </div>

                <!-- Features Section -->
                <div class="card mt-4 mb-4">
                    <div class="card-header d-flex justify-content-between align-items-center">
                        <span>Package Features</span>
                        <button type="button" class="btn btn-sm btn-primary" id="add-feature">Add Feature</button>
                    </div>
                    <div class="card-body">
                        <div id="features-container">
                            @if (old('features'))
                                @foreach (old('features') as $index => $feature)
                                    <div class="feature-item row mb-3">
                                        <div class="col-md-6">
                                            <input type="text" class="form-control @error('features.'.$index.'.name') is-invalid @enderror"
                                                   name="features[{{ $index }}][name]"
                                                   placeholder="Feature name"
                                                   value="{{ $feature['name'] ?? '' }}">
                                            @error('features.'.$index.'.name')
                                            <div class="invalid-feedback">{{ $message }}</div>
                                            @enderror
                                        </div>
                                        <div class="col-md-4">
                                            <div class="form-check">
                                                <input class="form-check-input" type="checkbox"
                                                       name="features[{{ $index }}][included]"
                                                       id="feature-included-{{ $index }}"
                                                       value="1"
                                                    {{ isset($feature['included']) ? 'checked' : '' }}>
                                                <label class="form-check-label" for="feature-included-{{ $index }}">
                                                    Included
                                                </label>
                                            </div>
                                        </div>
                                        <div class="col-md-2">
                                            <button type="button" class="btn btn-sm btn-danger remove-feature">Remove</button>
                                        </div>
                                    </div>
                                @endforeach
                            @endif
                        </div>
                        <div id="no-features-message" class="{{ old('features') ? 'd-none' : '' }}">
                            <p class="text-muted">No features added yet. Click the "Add Feature" button to add package features.</p>
                        </div>
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
            const maxQueriesContainer = document.querySelector('.max-queries-container');

            billingTypeSelect.addEventListener('change', function() {
                // Hide all price containers first
                monthlyPriceContainer.style.display = 'none';
                yearlyPriceContainer.style.display = 'none';
                unitPriceContainer.style.display = 'none';
                maxQueriesContainer.style.display = 'none';

                // Show the relevant price container based on the selected billing type
                if (this.value === 'monthly') {
                    monthlyPriceContainer.style.display = '';
                    maxQueriesContainer.style.display = '';
                } else if (this.value === 'yearly') {
                    yearlyPriceContainer.style.display = '';
                    maxQueriesContainer.style.display = '';
                } else if (this.value === 'unit') {
                    monthlyPriceContainer.style.display = '';
                    unitPriceContainer.style.display = '';
                }
            });

            // Features functionality
            const addFeatureBtn = document.getElementById('add-feature');
            const featuresContainer = document.getElementById('features-container');
            const noFeaturesMessage = document.getElementById('no-features-message');
            let featureIndex = {{ old('features') ? count(old('features')) : 0 }};

            addFeatureBtn.addEventListener('click', function() {
                addFeature();
            });

            function addFeature() {
                // Hide the "no features" message
                noFeaturesMessage.classList.add('d-none');

                // Create a new feature row
                const featureRow = document.createElement('div');
                featureRow.className = 'feature-item row mb-3';
                featureRow.innerHTML = `
                    <div class="col-md-6">
                        <input type="text" class="form-control" name="features[${featureIndex}][name]" placeholder="Feature name">
                    </div>
                    <div class="col-md-4">
                        <div class="form-check">
                            <input class="form-check-input" type="checkbox" name="features[${featureIndex}][included]" id="feature-included-${featureIndex}" value="1" checked>
                            <label class="form-check-label" for="feature-included-${featureIndex}">
                                Included
                            </label>
                        </div>
                    </div>
                    <div class="col-md-2">
                        <button type="button" class="btn btn-sm btn-danger remove-feature">Remove</button>
                    </div>
                `;

                featuresContainer.appendChild(featureRow);
                featureIndex++;

                // Add event listener to the new remove button
                const removeButton = featureRow.querySelector('.remove-feature');
                removeButton.addEventListener('click', function() {
                    featureRow.remove();

                    // If no features are left, show the "no features" message
                    if (featuresContainer.children.length === 0) {
                        noFeaturesMessage.classList.remove('d-none');
                    }
                });
            }

            // Add event listeners to existing remove buttons
            document.querySelectorAll('.remove-feature').forEach(button => {
                button.addEventListener('click', function() {
                    this.closest('.feature-item').remove();

                    // If no features are left, show the "no features" message
                    if (featuresContainer.children.length === 0) {
                        noFeaturesMessage.classList.remove('d-none');
                    }
                });
            });
        });
    </script>
@endsection
