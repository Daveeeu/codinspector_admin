{{-- resources/views/admin/packages/index.blade.php --}}
@extends('layouts.admin')

@section('title', 'Manage Packages')

@section('actions')
    @can('manage packages')
        <a href="{{ route('admin.packages.create') }}" class="btn btn-sm btn-success">
            <i class="bi bi-plus-circle"></i> Create Package
        </a>
    @endcan
@endsection

@section('content')
    <div class="card">
        <div class="card-body">
            <div class="table-responsive">
                <table class="table table-striped table-hover">
                    <thead>
                    <tr>
                        <th>Name</th>
                        <th>Billing Type</th>
                        <th>Monthly Price</th>
                        <th>Yearly Price</th>
                        <th>Unit Price</th>
                        <th>Status</th>
                        <th>Actions</th>
                    </tr>
                    </thead>
                    <tbody>
                    @forelse($packages as $package)
                        <tr>
                            <td>{{ $package->name }}</td>
                            <td>{{ ucfirst($package->billing_type) }}</td>
                            <td>{{ $package->monthly_price ? $package->domain->currency . ' ' . number_format($package->monthly_price, 2) : '-' }}</td>
                            <td>{{ $package->yearly_price ? $package->domain->currency . ' ' . number_format($package->yearly_price, 2) : '-' }}</td>
                            <td>{{ $package->unit_price ? $package->domain->currency . ' ' . number_format($package->unit_price, 2) : '-' }}</td>
                            <td>
                                @if($package->is_active)
                                    <span class="badge bg-success">Active</span>
                                @else
                                    <span class="badge bg-danger">Inactive</span>
                                @endif
                            </td>
                            <td>
                                @can('manage packages')
                                    <div class="btn-group" role="group">
                                        <a href="{{ route('admin.packages.edit', $package) }}" class="btn btn-sm btn-info" title="Edit Package">
                                            <i class="bi bi-pencil"></i>
                                        </a>

                                        <form action="{{ route('admin.packages.destroy', $package) }}" method="POST" class="d-inline delete-form">
                                            @csrf
                                            @method('DELETE')
                                            <button type="submit" class="btn btn-sm btn-danger" title="Delete Package" onclick="return confirm('Are you sure you want to delete this package?')">
                                                <i class="bi bi-trash"></i>
                                            </button>
                                        </form>
                                    </div>
                                @endcan
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="7" class="text-center">No packages found.</td>
                        </tr>
                    @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>
@endsection
