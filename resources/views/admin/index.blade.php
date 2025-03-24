{{-- resources/views/admin/domains/index.blade.php --}}
@extends('layouts.admin')

@section('title', 'Manage Domains')

@section('actions')
    @can('manage domains')
        <a href="{{ route('admin.domains.create') }}" class="btn btn-sm btn-success">
            <i class="bi bi-plus-circle"></i> Create Domain
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
                        <th>Domain</th>
                        <th>Country</th>
                        <th>Language</th>
                        <th>Currency</th>
                        <th>Status</th>
                        <th>Actions</th>
                    </tr>
                    </thead>
                    <tbody>
                    @forelse($domains as $domain)
                        <tr>
                            <td>{{ $domain->name }}</td>
                            <td>{{ $domain->domain }}</td>
                            <td>{{ $domain->country_code }}</td>
                            <td>{{ $domain->language_code }}</td>
                            <td>{{ $domain->currency }}</td>
                            <td>
                                @if($domain->is_active)
                                    <span class="badge bg-success">Active</span>
                                @else
                                    <span class="badge bg-danger">Inactive</span>
                                @endif
                            </td>
                            <td>
                                <div class="btn-group" role="group">
                                    <form action="{{ route('admin.domains.set') }}" method="POST" class="d-inline">
                                        @csrf
                                        <input type="hidden" name="domain_id" value="{{ $domain->id }}">
                                        <button type="submit" class="btn btn-sm btn-primary" title="Select Domain">
                                            <i class="bi bi-check-circle"></i>
                                        </button>
                                    </form>

                                    @can('manage domains')
                                        <a href="{{ route('admin.domains.edit', $domain) }}" class="btn btn-sm btn-info" title="Edit Domain">
                                            <i class="bi bi-pencil"></i>
                                        </a>

                                        <form action="{{ route('admin.domains.destroy', $domain) }}" method="POST" class="d-inline delete-form">
                                            @csrf
                                            @method('DELETE')
                                            <button type="submit" class="btn btn-sm btn-danger" title="Delete Domain" onclick="return confirm('Are you sure you want to delete this domain?')">
                                                <i class="bi bi-trash"></i>
                                            </button>
                                        </form>
                                    @endcan
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="7" class="text-center">No domains found.</td>
                        </tr>
                    @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>
@endsection
