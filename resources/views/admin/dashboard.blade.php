{{-- resources/views/admin/dashboard.blade.php --}}
@extends('layouts.admin')

@section('title', 'Dashboard')

@section('content')
    <div class="card mb-4">
        <div class="card-body">
            <h5 class="card-title">Welcome to the Admin Panel</h5>
            <p class="card-text">
                @if(Auth::user()->currentDomain)
                    You are currently managing the <strong>{{ Auth::user()->currentDomain->name }}</strong> domain.
                @else
                    Please select a domain to manage from the Domains menu.
                @endif
            </p>
        </div>
    </div>

    @if(Auth::user()->currentDomain)
        <div class="row">
            <div class="col-md-4 mb-4">
                <div class="card text-white bg-primary h-100">
                    <div class="card-body">
                        <h5 class="card-title">Packages</h5>
                        <p class="card-text display-4">
                            {{ App\Models\Package::where('domain_id', Auth::user()->current_domain_id)->count() }}
                        </p>
                    </div>
                    <div class="card-footer">
                        <a href="{{ route('admin.packages.index') }}" class="text-white text-decoration-none">Manage Packages</a>
                    </div>
                </div>
            </div>

            <div class="col-md-4 mb-4">
                <div class="card text-white bg-success h-100">
                    <div class="card-body">
                        <h5 class="card-title">Subscribers</h5>
                        <p class="card-text display-4">
                            {{ App\Models\Subscriber::where('domain_id', Auth::user()->current_domain_id)->count() }}
                        </p>
                    </div>
                    <div class="card-footer">
                        <a href="{{ route('admin.subscribers.index') }}" class="text-white text-decoration-none">Manage Subscribers</a>
                    </div>
                </div>
            </div>

            <div class="col-md-4 mb-4">
                <div class="card text-white bg-info h-100">
                    <div class="card-body">
                        <h5 class="card-title">Active Subscribers</h5>
                        <p class="card-text display-4">
                            {{ App\Models\Subscriber::where('domain_id', Auth::user()->current_domain_id)->where('status', 'active')->count() }}
                        </p>
                    </div>
                    <div class="card-footer">
                        <a href="{{ route('admin.subscribers.index') }}" class="text-white text-decoration-none">View Details</a>
                    </div>
                </div>
            </div>
        </div>

        <div class="row">
            <div class="col-12">
                <div class="card">
                    <div class="card-header">
                        Recent Activity
                    </div>
                    <div class="card-body">
                        <table class="table">
                            <thead>
                            <tr>
                                <th>Action</th>
                                <th>Description</th>
                                <th>User</th>
                                <th>Date</th>
                            </tr>
                            </thead>
                            <tbody>
                            @foreach(App\Models\ActivityLog::where('domain_id', Auth::user()->current_domain_id)->with('user')->orderBy('created_at', 'desc')->take(5)->get() as $log)
                                <tr>
                                    <td>{{ ucfirst($log->action) }}</td>
                                    <td>{{ $log->description }}</td>
                                    <td>{{ $log->user ? $log->user->name : 'System' }}</td>
                                    <td>{{ $log->created_at->diffForHumans() }}</td>
                                </tr>
                            @endforeach
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    @endif
@endsection
