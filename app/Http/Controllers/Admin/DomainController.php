<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Domain;
use App\Models\ActivityLog;
use App\Services\DomainConnectionService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Validator;

class DomainController extends Controller
{
    protected $domainService;

    public function __construct(DomainConnectionService $domainService)
    {
        $this->domainService = $domainService;
        $this->middleware('permission:manage domains', ['except' => ['select', 'setDomain']]);
    }

    public function index()
    {
        $domains = Domain::all();
        return view('admin.domains.index', compact('domains'));
    }

    public function create()
    {
        return view('admin.domains.create');
    }

    public function store(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'name' => 'required|string|max:255',
            'domain' => 'required|string|max:255|unique:domains',
            'database_name' => 'required|string|max:255',
            'database_host' => 'required|string|max:255',
            'database_username' => 'required|string|max:255',
            'database_password' => 'required|string|max:255',
            'currency' => 'required|string|max:3',
            'country_code' => 'required|string|size:2',
            'language_code' => 'required|string|max:5',
        ]);

        if ($validator->fails()) {
            return redirect()->back()->withErrors($validator)->withInput();
        }

        $domain = Domain::create($request->all());

        // Log the activity
        ActivityLog::create([
            'user_id' => Auth::id(),
            'domain_id' => null,
            'action' => 'create',
            'description' => 'Created new domain: ' . $domain->name,
            'model_type' => 'Domain',
            'model_id' => $domain->id,
            'new_values' => json_encode($domain->toArray()),
            'ip_address' => $request->ip(),
            'user_agent' => $request->userAgent(),
        ]);

        return redirect()->route('admin.domains.index')
            ->with('success', 'Domain created successfully.');
    }

    public function edit(Domain $domain)
    {
        return view('admin.domains.edit', compact('domain'));
    }

    public function update(Request $request, Domain $domain)
    {
        $validator = Validator::make($request->all(), [
            'name' => 'required|string|max:255',
            'domain' => 'required|string|max:255|unique:domains,domain,' . $domain->id,
            'database_name' => 'required|string|max:255',
            'database_host' => 'required|string|max:255',
            'database_username' => 'required|string|max:255',
            'database_password' => 'required|string|max:255',
            'currency' => 'required|string|max:3',
            'country_code' => 'required|string|size:2',
            'language_code' => 'required|string|max:5',
            'is_active' => 'boolean',
        ]);

        if ($validator->fails()) {
            return redirect()->back()->withErrors($validator)->withInput();
        }

        $oldValues = $domain->toArray();
        $domain->update($request->all());

        // Log the activity
        ActivityLog::create([
            'user_id' => Auth::id(),
            'domain_id' => null,
            'action' => 'update',
            'description' => 'Updated domain: ' . $domain->name,
            'model_type' => 'Domain',
            'model_id' => $domain->id,
            'old_values' => json_encode($oldValues),
            'new_values' => json_encode($domain->toArray()),
            'ip_address' => $request->ip(),
            'user_agent' => $request->userAgent(),
        ]);

        return redirect()->route('admin.domains.index')
            ->with('success', 'Domain updated successfully.');
    }

    public function destroy(Request $request, Domain $domain)
    {
        // Check if domain is being used by users
        if ($domain->users()->count() > 0) {
            return redirect()->back()
                ->with('error', 'This domain cannot be deleted as it is currently being used by users.');
        }

        $domainName = $domain->name;
        $domain->delete();

        // Log the activity
        ActivityLog::create([
            'user_id' => Auth::id(),
            'domain_id' => null,
            'action' => 'delete',
            'description' => 'Deleted domain: ' . $domainName,
            'model_type' => 'Domain',
            'model_id' => $domain->id,
            'old_values' => json_encode($domain->toArray()),
            'ip_address' => $request->ip(),
            'user_agent' => $request->userAgent(),
        ]);

        return redirect()->route('admin.domains.index')
            ->with('success', 'Domain deleted successfully.');
    }

    public function select()
    {
        $domains = Domain::where('is_active', true)->get();
        return view('admin.domains.select', compact('domains'));
    }

    public function setDomain(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'domain_id' => 'required|exists:domains,id',
        ]);

        if ($validator->fails()) {
            return redirect()->back()->withErrors($validator);
        }

        $domain = Domain::findOrFail($request->domain_id);

        // Update user's current domain
        $user = Auth::user();
        $user->current_domain_id = $domain->id;
        $user->save();

        // Set up the domain connection
        $this->domainService->setConnection($domain);

        // Log the activity
        ActivityLog::create([
            'user_id' => Auth::id(),
            'domain_id' => $domain->id,
            'action' => 'select',
            'description' => 'Selected domain: ' . $domain->name,
            'model_type' => 'User',
            'model_id' => $user->id,
            'ip_address' => $request->ip(),
            'user_agent' => $request->userAgent(),
        ]);

        return redirect()->route('admin.dashboard')
            ->with('success', 'Domain selected: ' . $domain->name);
    }
}
