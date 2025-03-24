<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Models\ActivityLog;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Validator;
use Spatie\Permission\Models\Role;
use Spatie\Permission\Models\Permission;

class UserController extends Controller
{
    public function __construct()
    {
        $this->middleware('permission:manage users');
    }

    public function index()
    {
        $users = User::with('roles')->get();
        return view('admin.users.index', compact('users'));
    }

    public function create()
    {
        $roles = Role::all();
        return view('admin.users.create', compact('roles'));
    }

    public function store(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'name' => 'required|string|max:255',
            'email' => 'required|string|email|max:255|unique:users',
            'password' => 'required|string|min:8|confirmed',
            'roles' => 'required|array',
            'roles.*' => 'exists:roles,id',
        ]);

        if ($validator->fails()) {
            return redirect()->back()->withErrors($validator)->withInput();
        }

        // Create the user
        $user = User::create([
            'name' => $request->name,
            'email' => $request->email,
            'password' => Hash::make($request->password),
        ]);

        // Assign roles
        $roles = Role::whereIn('id', $request->roles)->get();
        $user->assignRole($roles);

        // Log the activity
        ActivityLog::create([
            'user_id' => Auth::id(),
            'domain_id' => Auth::user()->current_domain_id,
            'action' => 'create',
            'description' => 'Created new user: ' . $user->name,
            'model_type' => 'User',
            'model_id' => $user->id,
            'new_values' => json_encode(array_merge(
                $user->toArray(),
                ['roles' => $user->getRoleNames()]
            )),
            'ip_address' => $request->ip(),
            'user_agent' => $request->userAgent(),
        ]);

        return redirect()->route('admin.users.index')
            ->with('success', 'User created successfully.');
    }

    public function edit(User $user)
    {
        $roles = Role::all();
        $userRoles = $user->roles()->pluck('id')->toArray();

        return view('admin.users.edit', compact('user', 'roles', 'userRoles'));
    }

    public function update(Request $request, User $user)
    {
        $validator = Validator::make($request->all(), [
            'name' => 'required|string|max:255',
            'email' => 'required|string|email|max:255|unique:users,email,' . $user->id,
            'password' => 'nullable|string|min:8|confirmed',
            'roles' => 'required|array',
            'roles.*' => 'exists:roles,id',
        ]);

        if ($validator->fails()) {
            return redirect()->back()->withErrors($validator)->withInput();
        }

        $oldValues = array_merge(
            $user->toArray(),
            ['roles' => $user->getRoleNames()]
        );

        // Update the user
        $user->name = $request->name;
        $user->email = $request->email;

        if ($request->filled('password')) {
            $user->password = Hash::make($request->password);
        }

        $user->save();

        // Sync roles
        $roles = Role::whereIn('id', $request->roles)->get();
        $user->syncRoles($roles);

        // Log the activity
        ActivityLog::create([
            'user_id' => Auth::id(),
            'domain_id' => Auth::user()->current_domain_id,
            'action' => 'update',
            'description' => 'Updated user: ' . $user->name,
            'model_type' => 'User',
            'model_id' => $user->id,
            'old_values' => json_encode($oldValues),
            'new_values' => json_encode(array_merge(
                $user->toArray(),
                ['roles' => $user->getRoleNames()]
            )),
            'ip_address' => $request->ip(),
            'user_agent' => $request->userAgent(),
        ]);

        return redirect()->route('admin.users.index')
            ->with('success', 'User updated successfully.');
    }

    public function destroy(Request $request, User $user)
    {
        // Prevent deleting yourself
        if ($user->id === Auth::id()) {
            return redirect()->route('admin.users.index')
                ->with('error', 'You cannot delete your own account.');
        }

        // Save user info for activity log
        $userName = $user->name;
        $userId = $user->id;
        $userDetails = array_merge(
            $user->toArray(),
            ['roles' => $user->getRoleNames()]
        );

        // Delete the user
        $user->delete();

        // Log the activity
        ActivityLog::create([
            'user_id' => Auth::id(),
            'domain_id' => Auth::user()->current_domain_id,
            'action' => 'delete',
            'description' => 'Deleted user: ' . $userName,
            'model_type' => 'User',
            'model_id' => $userId,
            'old_values' => json_encode($userDetails),
            'ip_address' => $request->ip(),
            'user_agent' => $request->userAgent(),
        ]);

        return redirect()->route('admin.users.index')
            ->with('success', 'User deleted successfully.');
    }
}



