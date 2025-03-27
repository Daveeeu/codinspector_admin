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

                <!-- Pricing Options -->
                <div class="card mb-4">
                    <div class="card-header">
                        <span>Pricing Options</span>
                    </div>
                    <div class="card-body">
                        <!-- Monthly Price -->
                        <div class="row mb-3">
                            <div class="col-md-6">
                                <label for="monthly_price" class="form-label">Monthly Price ({{ Auth::user()->currentDomain->currency }})</label>
                                <input type="number" step="0.01" class="form-control @error('monthly_price') is-invalid @enderror" id="monthly_price" name="monthly_price" value="{{ old('monthly_price') }}">
                                <small class="text-muted">Monthly subscription price (required for Monthly billing)</small>
                                @error('monthly_price')
                                <div class="invalid-feedback">{{ $message }}</div>
                                @enderror
                            </div>
                        </div>

                        <!-- Yearly Price -->
                        <div class="row mb-3">
                            <div class="col-md-6">
                                <label for="yearly_price" class="form-label">Yearly Price ({{ Auth::user()->currentDomain->currency }})</label>
                                <input type="number" step="0.01" class="form-control @error('yearly_price') is-invalid @enderror" id="yearly_price" name="yearly_price" value="{{ old('yearly_price') }}">
                                <small class="text-muted">Yearly subscription price (required for Yearly billing)</small>
                                @error('yearly_price')
                                <div class="invalid-feedback">{{ $message }}</div>
                                @enderror
                            </div>
                        </div>

                        <!-- Unit Price (for unit-based billing) -->
                        <div class="row mb-3 unit-price-container" style="{{ old('billing_type') == 'unit' ? '' : 'display: none;' }}">
                            <div class="col-md-6">
                                <label for="unit_price" class="form-label">Unit Price ({{ Auth::user()->currentDomain->currency }})</label>
                                <input type="number" step="0.01" class="form-control @error('unit_price') is-invalid @enderror" id="unit_price" name="unit_price" value="{{ old('unit_price') }}">
                                <small class="text-muted">Price per query (required for Unit-based billing)</small>
                                @error('unit_price')
                                <div class="invalid-feedback">{{ $message }}</div>
                                @enderror
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Max Queries Section -->
                <div class="row mb-3">
                    <div class="col-md-6">
                        <label for="max_queries" class="form-label">Maximum Queries</label>
                        <input type="number" min="1" class="form-control @error('max_queries') is-invalid @enderror" id="max_queries" name="max_queries" value="{{ old('max_queries') }}">
                        <small class="text-muted">Maximum number of queries allowed for this package (not applicable for Unit-based billing)</small>
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

                <!-- Permissions Section -->
                <div class="card mt-4 mb-4">
                    <div class="card-header">
                        <span>Package Permissions</span>
                    </div>
                    <div class="card-body">
                        <div class="mb-3">
                            <label for="role_name" class="form-label">Role Name</label>
                            <input type="text" class="form-control @error('role_name') is-invalid @enderror" id="role_name" name="role_name" value="{{ old('role_name') }}" placeholder="e.g. Package Premium User">
                            <small class="text-muted">A role will be created with this name and assigned to users who subscribe to this package</small>
                            @error('role_name')
                            <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                        </div>

                        <div class="mb-3">
                            <label class="form-label">Available Permissions</label>
                            <div class="permissions-list">
                                @if(isset($permissions) && count($permissions) > 0)
                                    <div class="row">
                                        @foreach($permissions as $permission)
                                            <div class="col-md-4 mb-2">
                                                <div class="form-check">
                                                    <input class="form-check-input" type="checkbox"
                                                           name="permissions[]"
                                                           id="permission-{{ $permission->id }}"
                                                           value="{{ $permission->id }}"
                                                        {{ in_array($permission->id, old('permissions', [])) ? 'checked' : '' }}>
                                                    <label class="form-check-label" for="permission-{{ $permission->id }}">
                                                        {{ $permission->name }}
                                                    </label>
                                                </div>
                                            </div>
                                        @endforeach
                                    </div>
                                @else
                                    <p class="text-muted">No permissions available in the system.</p>
                                @endif
                            </div>
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
           const unitPriceContainer = document.querySelector('.unit-price-container');
           const monthlyPriceContainer = document.querySelector('#monthly_price').closest('.row');
           const yearlyPriceContainer = document.querySelector('#yearly_price').closest('.row');
           const monthlyPrice = document.getElementById('monthly_price');
           const yearlyPrice = document.getElementById('yearly_price');
           const unitPrice = document.getElementById('unit_price');
           const maxQueriesField = document.getElementById('max_queries');
           const maxQueriesContainer = maxQueriesField.closest('.row');

           // Function to update required fields and visibility based on billing type
           function updateFieldsBasedOnBillingType(billingType) {
               // Reset all required states
               monthlyPrice.required = false;
               yearlyPrice.required = false;
               unitPrice.required = false;

               // Reset visibility (show all first)
               monthlyPriceContainer.style.display = '';
               yearlyPriceContainer.style.display = '';
               unitPriceContainer.style.display = '';

               // Set requirements and visibility based on billing type
               if (billingType === 'monthly') {
                   monthlyPrice.required = true;
                   yearlyPrice.required = false;
                   unitPriceContainer.style.display = 'none';
                   maxQueriesContainer.style.display = '';
               } else if (billingType === 'yearly') {
                   monthlyPrice.required = false;
                   yearlyPrice.required = true;
                   unitPriceContainer.style.display = 'none';
                   maxQueriesContainer.style.display = '';
               } else if (billingType === 'unit') {
                   monthlyPrice.required = false;
                   yearlyPrice.required = false;
                   unitPrice.required = true;
                   monthlyPriceContainer.style.display = 'none'
                   yearlyPriceContainer.style.display = 'none'
                   maxQueriesContainer.style.display = 'none';
               }
           }

           // Initialize fields based on current selection
           if (billingTypeSelect.value) {
               updateFieldsBasedOnBillingType(billingTypeSelect.value);
           }

           // Handle billing type changes
           billingTypeSelect.addEventListener('change', function() {
               updateFieldsBasedOnBillingType(this.value);
           });

           // Features functionality
           const addFeatureBtn = document.getElementById('add-feature');
           const featuresContainer = document.getElementById('features-container');
           const noFeaturesMessage = document.getElementById('no-features-message');
           let featureIndex = featuresContainer.children.length;

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
