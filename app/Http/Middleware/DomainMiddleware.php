<?php
// app/Http/Middleware/DomainMiddleware.php
namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use App\Services\DomainConnectionService;

class DomainMiddleware
{
    protected $domainService;

    public function __construct(DomainConnectionService $domainService)
    {
        $this->domainService = $domainService;
    }

    public function handle(Request $request, Closure $next)
    {
        // Check if user is authenticated
        if (!Auth::check()) {
            return redirect()->route('login');
        }

        $user = Auth::user();

        // If user has not selected a domain yet, redirect to domain selection
        if (!$user->current_domain_id && !$request->routeIs('admin.domains.*')) {
            return redirect()->route('admin.domains.select')
                ->with('warning', 'Please select a domain to continue.');
        }

        // If user has selected a domain, set up the connection
        if ($user->current_domain_id) {
            $this->domainService->setConnection($user->currentDomain);
        }

        return $next($request);
    }
}
