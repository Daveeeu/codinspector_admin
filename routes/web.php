<?php

// routes/web.php
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Admin\DomainController;
use App\Http\Controllers\Admin\PackageController;
use App\Http\Controllers\Admin\SubscriberController;
use App\Http\Controllers\Admin\UserController;

/*
|--------------------------------------------------------------------------
| Web Routes
|--------------------------------------------------------------------------
|
| Here is where you can register web routes for your application. These
| routes are loaded by the RouteServiceProvider within a group which
| contains the "web" middleware group.
|
*/

Route::get('/', function () {
    return redirect()->route('login');
});

// Authentication routes
\Illuminate\Support\Facades\Auth::routes(['register' => false]);

// Admin routes
Route::middleware(['auth'])->prefix('admin')->name('admin.')->group(function () {
    // Dashboard
    Route::get('/dashboard', function () {
        return view('admin.dashboard');
    })->name('dashboard')->middleware('domain');

    // Domain selection
    Route::get('/domains/select', [DomainController::class, 'select'])->name('domains.select');
    Route::post('/domains/set', [DomainController::class, 'setDomain'])->name('domains.set');

    // Domain management
    Route::resource('domains', DomainController::class);

    // Routes that require domain selection
    Route::middleware(['domain'])->group(function () {
        // Package management
        Route::resource('packages', PackageController::class);

        // Subscriber management
        // Az alábbi route definíciókat add hozzá a routes/web.php fájlhoz a megfelelő middleware csoportban

// Előfizetők kezelése
        Route::prefix('subscribers')->name('subscribers.')->group(function () {
            // Alap CRUD műveletek
            Route::get('/', [SubscriberController::class, 'index'])->name('index');
            Route::get('/create', [SubscriberController::class, 'create'])->name('create');
            Route::post('/', [SubscriberController::class, 'store'])->name('store');
            Route::get('/{customerId}', [SubscriberController::class, 'show'])->name('show');
            Route::get('/{customerId}/edit', [SubscriberController::class, 'edit'])->name('edit');
            Route::put('/{customerId}', [SubscriberController::class, 'update'])->name('update');
            Route::delete('/{customerId}', [SubscriberController::class, 'destroy'])->name('destroy');

            // Előfizetés lemondása
            Route::post('/subscription/{subscriptionId}/cancel', [SubscriberController::class, 'cancelSubscription'])->name('cancelSubscription');
        });

        // User management
        Route::resource('users', UserController::class);

        // Activity logs
        Route::get('/logs', function () {
            $logs = App\Models\ActivityLog::with('user')
                ->where('domain_id', Auth::user()->current_domain_id)
                ->orderBy('created_at', 'desc')
                ->paginate(20);

            return view('admin.logs.index', compact('logs'));
        })->name('logs.index')->middleware('permission:view logs');
    });
});
