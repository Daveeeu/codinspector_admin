@extends('layouts.admin')

@section('title', 'Előfizető adatai: ' . $customer->name)

@section('actions')
    <a href="{{ route('admin.subscribers.index') }}" class="btn btn-sm btn-secondary">
        <i class="bi bi-arrow-left"></i> Vissza
    </a>
    <a href="{{ route('admin.subscribers.edit', $customer->id) }}" class="btn btn-sm btn-info">
        <i class="bi bi-pencil"></i> Szerkesztés
    </a>
@endsection

@section('content')
    <div class="row">
        <div class="col-md-6">
            <div class="card mb-4">
                <div class="card-header">
                    <h5 class="card-title mb-0">Előfizető adatai</h5>
                </div>
                <div class="card-body">
                    <dl class="row">
                        <dt class="col-sm-4">Cégnév:</dt>
                        <dd class="col-sm-8">{{ $customer->name }}</dd>

                        <dt class="col-sm-4">Email:</dt>
                        <dd class="col-sm-8">{{ $customer->email }}</dd>

                        <dt class="col-sm-4">Telefonszám:</dt>
                        <dd class="col-sm-8">{{ $customer->phone ?: 'Nincs megadva' }}</dd>

                        <dt class="col-sm-4">Létrehozva:</dt>
                        <dd class="col-sm-8">{{ \Carbon\Carbon::createFromTimestamp($customer->created)->format('Y-m-d H:i:s') }}</dd>

                        @if($customer->address)
                            <dt class="col-sm-4">Cím:</dt>
                            <dd class="col-sm-8">
                                {{ $customer->address->line1 ?? '' }}<br>
                                {{ $customer->address->city ?? '' }} {{ $customer->address->postal_code ?? '' }}<br>
                                {{ $customer->address->country ?? '' }}
                            </dd>
                        @endif

                        @if(isset($customer->tax_ids) && count($customer->tax_ids->data) > 0)
                            <dt class="col-sm-4">Adószám:</dt>
                            <dd class="col-sm-8">{{ $customer->tax_ids->data[0]->value }}</dd>
                        @endif
                    </dl>
                </div>
            </div>
        </div>

        <div class="col-md-6">
            <div class="card mb-4">
                <div class="card-header">
                    <h5 class="card-title mb-0">Fizetési információk</h5>
                </div>
                <div class="card-body">
                    <dl class="row">
                        <dt class="col-sm-4">Ügyfél ID:</dt>
                        <dd class="col-sm-8"><code>{{ $customer->id }}</code></dd>

                        <dt class="col-sm-4">Alapértelmezett fizetési mód:</dt>
                        <dd class="col-sm-8">
                            @if($customer->invoice_settings && $customer->invoice_settings->default_payment_method)
                                <span class="badge bg-success">Beállítva</span>
                            @else
                                <span class="badge bg-warning">Nincs beállítva</span>
                            @endif
                        </dd>

                        <dt class="col-sm-4">Egyenleg:</dt>
                        <dd class="col-sm-8">
                            @if($customer->balance < 0)
                                <span class="text-danger">{{ number_format(abs($customer->balance) / 100, 2) }} {{ strtoupper($customer->currency) }}</span>
                            @elseif($customer->balance > 0)
                                <span class="text-success">{{ number_format($customer->balance / 100, 2) }} {{ strtoupper($customer->currency) }}</span>
                            @else
                                0.00
                            @endif
                        </dd>
                    </dl>
                </div>
            </div>
        </div>
    </div>

    <div class="card mb-4">
        <div class="card-header d-flex justify-content-between align-items-center">
            <h5 class="card-title mb-0">Aktív előfizetések</h5>
        </div>
        <div class="card-body">
            @if(count($subscriptions) > 0)
                <div class="table-responsive">
                    <table class="table table-bordered">
                        <thead>
                        <tr>
                            <th>Előfizetés azonosító</th>
                            <th>Csomag</th>
                            <th>Időszak</th>
                            <th>Státusz</th>
                            <th>Összeg</th>
                            <th>Műveletek</th>
                        </tr>
                        </thead>
                        <tbody>
                        @foreach($subscriptions as $subscription)
                            @php
                                $productId = $subscription->plan->product->id ?? null;
                                $package = $productId && isset($packages[$productId]) ? $packages[$productId] : null;
                                $currentPeriodStart = \Carbon\Carbon::createFromTimestamp($subscription->current_period_start)->format('Y-m-d');
                                $currentPeriodEnd = \Carbon\Carbon::createFromTimestamp($subscription->current_period_end)->format('Y-m-d');
                            @endphp
                            <tr>
                                <td><code>{{ $subscription->id }}</code></td>
                                <td>
                                    @if($package)
                                        {{ $package->name }}
                                    @else
                                        {{ $subscription->plan->product->name ?? 'Ismeretlen csomag' }}
                                    @endif
                                </td>
                                <td>{{ $currentPeriodStart }} - {{ $currentPeriodEnd }}</td>
                                <td>
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
                                </td>
                                <td>
                                    {{ number_format($subscription->plan->amount / 100, 2) }} {{ strtoupper($subscription->plan->currency) }}
                                    /
                                    @if($subscription->plan->interval === 'month')
                                        hónap
                                    @elseif($subscription->plan->interval === 'year')
                                        év
                                    @else
                                        {{ $subscription->plan->interval }}
                                    @endif
                                </td>
                                <td>
                                    @if($subscription->status === 'active' || $subscription->status === 'trialing')
                                        @if($subscription->cancel_at_period_end)
                                            <form action="{{ route('admin.subscribers.cancelSubscription', $subscription->id) }}" method="POST" class="d-inline">
                                                @csrf
                                                <button type="submit" class="btn btn-sm btn-success" title="Lemondás visszavonása">
                                                    <i class="bi bi-arrow-counterclockwise"></i> Lemondás visszavonása
                                                </button>
                                            </form>
                                        @else
                                            <div class="btn-group" role="group">
                                                <form action="{{ route('admin.subscribers.cancelSubscription', $subscription->id) }}" method="POST" class="d-inline">
                                                    @csrf
                                                    <input type="hidden" name="cancel_immediately" value="0">
                                                    <button type="submit" class="btn btn-sm btn-warning" title="Lemondás az időszak végén">
                                                        <i class="bi bi-calendar"></i> Lemondás az időszak végén
                                                    </button>
                                                </form>

                                                <form action="{{ route('admin.subscribers.cancelSubscription', $subscription->id) }}" method="POST" class="d-inline">
                                                    @csrf
                                                    <input type="hidden" name="cancel_immediately" value="1">
                                                    <button type="submit" class="btn btn-sm btn-danger" title="Azonnali lemondás" onclick="return confirm('Biztosan le szeretné mondani ezt az előfizetést azonnal?')">
                                                        <i class="bi bi-x-circle"></i> Azonnali lemondás
                                                    </button>
                                                </form>
                                            </div>
                                        @endif
                                    @elseif($subscription->status === 'canceled')
                                        <button class="btn btn-sm btn-secondary" disabled>Lemondva</button>
                                    @endif
                                </td>
                            </tr>
                        @endforeach
                        </tbody>
                    </table>
                </div>
            @else
                <div class="alert alert-info">
                    Nincsenek aktív előfizetések.
                </div>
            @endif
        </div>
    </div>

    <div class="card mb-4">
        <div class="card-header">
            <h5 class="card-title mb-0">Számlák</h5>
        </div>
        <div class="card-body">
            @if(count($invoices) > 0)
                <div class="table-responsive">
                    <table class="table table-striped">
                        <thead>
                        <tr>
                            <th>Számla szám</th>
                            <th>Dátum</th>
                            <th>Összeg</th>
                            <th>Státusz</th>
                            <th>PDF</th>
                        </tr>
                        </thead>
                        <tbody>
                        @foreach($invoices as $invoice)
                            <tr>
                                <td><code>{{ $invoice->number ?? $invoice->id }}</code></td>
                                <td>{{ \Carbon\Carbon::createFromTimestamp($invoice->created)->format('Y-m-d') }}</td>
                                <td>{{ number_format($invoice->total / 100, 2) }} {{ strtoupper($invoice->currency) }}</td>
                                <td>
                                    @if($invoice->status === 'paid')
                                        <span class="badge bg-success">Kifizetve</span>
                                    @elseif($invoice->status === 'open')
                                        <span class="badge bg-warning">Nyitott</span>
                                    @elseif($invoice->status === 'draft')
                                        <span class="badge bg-secondary">Piszkozat</span>
                                    @elseif($invoice->status === 'uncollectible')
                                        <span class="badge bg-danger">Behajthatatlan</span>
                                    @elseif($invoice->status === 'void')
                                        <span class="badge bg-danger">Érvénytelen</span>
                                    @else
                                        <span class="badge bg-info">{{ ucfirst($invoice->status) }}</span>
                                    @endif

                                    @if($invoice->paid)
                                        <span class="badge bg-success">Kifizetve: {{ \Carbon\Carbon::createFromTimestamp($invoice->status_transitions->paid_at)->format('Y-m-d') }}</span>
                                    @endif
                                </td>
                                <td>
                                    @if($invoice->invoice_pdf)
                                        <a href="{{ $invoice->invoice_pdf }}" target="_blank" class="btn btn-sm btn-primary">
                                            <i class="bi bi-file-pdf"></i> PDF
                                        </a>
                                    @else
                                        <button class="btn btn-sm btn-secondary" disabled>Nincs PDF</button>
                                    @endif
                                </td>
                            </tr>
                        @endforeach
                        </tbody>
                    </table>
                </div>
            @else
                <div class="alert alert-info">
                    Nincsenek számlák.
                </div>
            @endif
        </div>
    </div>
@endsection
