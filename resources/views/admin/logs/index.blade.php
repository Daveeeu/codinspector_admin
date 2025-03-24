{{-- resources/views/admin/logs/index.blade.php --}}
@extends('layouts.admin')

@section('title', 'Activity Logs')

@section('content')
    <div class="card">
        <div class="card-body">
            <div class="table-responsive">
                <table class="table table-striped table-hover">
                    <thead>
                    <tr>
                        <th>Time</th>
                        <th>User</th>
                        <th>Action</th>
                        <th>Description</th>
                        <th>Model</th>
                        <th>IP Address</th>
                    </tr>
                    </thead>
                    <tbody>
                    @forelse($logs as $log)
                        <tr>
                            <td>{{ $log->created_at->format('Y-m-d H:i:s') }}</td>
                            <td>{{ $log->user ? $log->user->name : 'System' }}</td>
                            <td>{{ ucfirst($log->action) }}</td>
                            <td>{{ $log->description }}</td>
                            <td>{{ $log->model_type ? class_basename($log->model_type) : '-' }} #{{ $log->model_id ?: '-' }}</td>
                            <td>{{ $log->ip_address }}</td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="6" class="text-center">No activity logs found.</td>
                        </tr>
                    @endforelse
                    </tbody>
                </table>
            </div>

            <div class="d-flex justify-content-center mt-4">
                {{ $logs->links() }}
            </div>
        </div>
    </div>
@endsection
