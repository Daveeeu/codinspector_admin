{{-- resources/views/admin/domains/select.blade.php --}}
@extends('layouts.admin')

@section('title', 'Select Domain')

@section('content')
    <div class="row">
        <div class="col-md-8 offset-md-2">
            <div class="card">
                <div class="card-header">Select a Domain to Manage</div>
                <div class="card-body">
                    <div class="row">
                        @forelse($domains as $domain)
                            <div class="col-md-6 mb-4">
                                <div class="card h-100">
                                    <div class="card-body">
                                        <h5 class="card-title">{{ $domain->name }}</h5>
                                        <p class="card-text">
                                            <strong>Domain:</strong> {{ $domain->domain }}<br>
                                            <strong>Country:</strong> {{ $domain->country_code }}<br>
                                            <strong>Language:</strong> {{ $domain->language_code }}<br>
                                            <strong>Currency:</strong> {{ $domain->currency }}
                                        </p>
                                    </div>
                                    <div class="card-footer">
                                        <form action="{{ route('admin.domains.set') }}" method="POST">
                                            @csrf
                                            <input type="hidden" name="domain_id" value="{{ $domain->id }}">
                                            <button type="submit" class="btn btn-primary w-100">Select</button>
                                        </form>
                                    </div>
                                </div>
                            </div>
                        @empty
                            <div class="col-12">
                                <div class="alert alert-info" role="alert">
                                    No domains available. Please create a domain first.
                                </div>
                            </div>
                        @endforelse
                    </div>
                </div>
            </div>

            @can('manage domains')
                <div class="mt-4 text-center">
                    <a href="{{ route('admin.domains.create') }}" class="btn btn-success">
                        <i class="bi bi-plus-circle"></i> Create New Domain
                    </a>
                </div>
            @endcan
        </div>
    </div>
@endsection
