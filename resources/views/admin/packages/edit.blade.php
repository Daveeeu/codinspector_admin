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

                <!-- Max Queries Section - Only for Monthly and Yearly -->
                @if($package->billing_type != 'unit')
                    <div class="row mb-3">
                        <div class="col-md-6">
                            <label for="max_queries" class="form-label">Maximum Queries</label>
                            <input type="number" min="1" class="form-control @error('max_queries') is-invalid @enderror"
                                   id="max_queries" name="max_queries" value="{{ old('max_queries', $package->max_queries) }}">
                            <small class="text-muted">Maximum number of queries allowed for this package</small>
                            @error('max_queries')
                            <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                        </div>
                    </div>
                @endif

                <!-- Premium Flag -->
                <div class="form-check mb-3">
                    <input class="form-check-input" type="checkbox" id="is_premium" name="is_premium" value="1"
                        {{ old('is_premium', $package->is_premium) ? 'checked' : '' }}>
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
                            @if(old('features'))
                                @foreach(old('features') as $index => $feature)
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
                            @elseif($package->features && count($package->features) > 0)
                                @foreach($package->features as $index => $feature)
                                    <div class="feature-item row mb-3">
                                        <div class="col-md-6">
                                            <input type="text" class="form-control"
                                                   name="features[{{ $index }}][name]"
                                                   placeholder="Feature name"
                                                   value="{{ $feature['name'] }}">
                                            <input type="hidden" name="features[{{ $index }}][id]" value="{{ $feature['id'] ?? '' }}">
                                        </div>
                                        <div class="col-md-4">
                                            <div class="form-check">
                                                <input class="form-check-input" type="checkbox"
                                                       name="features[{{ $index }}][included]"
                                                       id="feature-included-{{ $index }}"
                                                       value="1"
                                                    {{ isset($feature['included']) && $feature['included'] ? 'checked' : '' }}>
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
                        <div id="no-features-message" class="{{ (old('features') || ($package->features && count($package->features) > 0)) ? 'd-none' : '' }}">
                            <p class="text-muted">No features added yet. Click the "Add Feature" button to add package features.</p>
                        </div>
                    </div>
                </div>

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

@section('scripts')
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            // Features functionality
            const addFeatureBtn = document.getElementById('add-feature');
            const featuresContainer = document.getElementById('features-container');
            const noFeaturesMessage = document.getElementById('no-features-message');
            let featureIndex = {{
                old('features')
                ? count(old('features'))
                : ($package->features && count($package->features) > 0
                    ? count($package->features)
                    : 0)
            }};

            addFeatureBtn.addEventListener('click', function() {
                addFeature();
            });

            function addFeature() {
                // Hide the "no features" message
                noFeaturesMessage.classList.add('d-none');

                // Create a new feature row
                const featureRow = document.createElement('div');
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
