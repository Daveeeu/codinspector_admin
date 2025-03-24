@extends('layouts.admin')

@section('title', 'Előfizetők kezelése')

@section('actions')
    @can('manage subscribers')
        <a href="{{ route('admin.subscribers.create') }}" class="btn btn-sm btn-success">
            <i class="bi bi-plus-circle"></i> Új előfizető
        </a>
    @endcan
@endsection

@section('content')
    @if(isset($error))
        <div class="alert alert-danger">
            Hiba történt a Stripe adatok lekérésekor: {{ $error }}
        </div>
    @endif

    <div class="card">
        <div class="card-body">
            <div class="table-responsive">
                <table class="table table-striped table-hover">
                    <thead>
                    <tr>
                        <th>Cégnév</th>
                        <th>Email</th>
                        <th>Csomag</th>
                        <th>Státusz</th>
                        <th>Létrehozva</th>
                        <th>Műveletek</th>
                    </tr>
                    </thead>
                    <tbody>
                    @forelse($customers as $customer)
                        <tr>
                            <td>{{ $customer->name }}</td>
                            <td>{{ $customer->email }}</td>
                            <td>
                                @if(isset($subscriptions[$customer->id]))
                                    @foreach($subscriptions[$customer->id] as $subscription)
                                        @php
                                            $productId = $subscription->plan->product->id ?? null;
                                            $package = $productId && isset($packages[$productId]) ? $packages[$productId] : null;
                                        @endphp

                                        @if($package)
                                            <span class="badge bg-info">{{ $package->name }}</span>
                                        @else
                                            <span class="badge bg-secondary">{{ $subscription->plan->product->name ?? 'Ismeretlen' }}</span>
                                        @endif
                                    @endforeach
                                @else
                                    <span class="badge bg-warning">Nincs előfizetés</span>
                                @endif
                            </td>
                            <td>
                                @if(isset($subscriptions[$customer->id]))
                                    @foreach($subscriptions[$customer->id] as $subscription)
                                        @if($subscription->status === 'active')
                                            <span class="badge bg-success">Aktív</span>
                                        @elseif($subscription->status === 'trialing')
                                            <span class="badge bg-info">Próbaidőszak</span>
                                        @elseif($subscription->status === 'past_due')
                                            <span class="badge bg-warning">Késedelmes</span>
                                        @elseif($subscription->status === 'canceled')
                                            <span class="badge bg-danger">Lemondva</span>
                                        @elseif($subscription->status === 'unpaid')
                                            <span class="badge bg-danger">Kifizetetlen</span>
                                        @else
                                            <span class="badge bg-secondary">{{ ucfirst($subscription->status) }}</span>
                                        @endif

                                        @if($subscription->cancel_at_period_end)
                                            <span class="badge bg-warning">Lemondva az időszak végén</span>
                                        @endif
                                    @endforeach
                                @else
                                    <span class="badge bg-secondary">Nincs előfizetés</span>
                                @endif
                            </td>
                            <td>{{ \Carbon\Carbon::createFromTimestamp($customer->created)->format('Y-m-d') }}</td>
                            <td>
                                <div class="btn-group" role="group">
                                    <a href="{{ route('admin.subscribers.show', $customer->id) }}" class="btn btn-sm btn-primary" title="Részletek">
                                        <i class="bi bi-eye"></i>
                                    </a>

                                    <a href="{{ route('admin.subscribers.edit', $customer->id) }}" class="btn btn-sm btn-info" title="Szerkesztés">
                                        <i class="bi bi-pencil"></i>
                                    </a>

                                    <form action="{{ route('admin.subscribers.destroy', $customer->id) }}" method="POST" class="d-inline delete-form">
                                        @csrf
                                        @method('DELETE')
                                        <button type="submit" class="btn btn-sm btn-danger" title="Törlés" onclick="return confirm('Biztosan törölni szeretné ezt az előfizetőt?')">
                                            <i class="bi bi-trash"></i>
                                        </button>
                                    </form>
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="6" class="text-center">Nincsenek előfizetők.</td>
                        </tr>
                    @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>
@endsection
