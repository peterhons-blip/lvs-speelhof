<?php

namespace App\Http\Controllers;

use App\Services\LibraryAccess;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class PortalPermissionsController extends Controller
{
    public function index(Request $request, LibraryAccess $access): View
    {
        $access->requirePermission($request, 'beheer.permissions.manage');
        $modules = config('portal_permissions', []);
        $roles = LibraryAccess::ROLES;
        $stored = DB::table('portal_role_permissions')->get()->groupBy('role')
            ->map(fn ($rows) => $rows->pluck('permission')->all())->all();

        return view('beheer.permissions', compact('modules', 'roles', 'stored'));
    }

    public function update(Request $request, LibraryAccess $access): RedirectResponse
    {
        $access->requirePermission($request, 'beheer.permissions.manage');
        $data = $request->validate([
            'role' => ['required', Rule::in(array_keys(LibraryAccess::ROLES))],
            'permissions' => ['sometimes', 'array'],
            'permissions.*' => ['string', 'max:100'],
        ]);

        $role = $data['role'];
        abort_if($role === 'ict_coordinator', 403, 'ICT-beheerrechten staan vast.');
        $valid = collect(config('portal_permissions', []))
            ->flatMap(fn ($module) => array_keys($module['permissions'] ?? []))->all();
        $requested = array_values(array_unique($data['permissions'] ?? []));
        abort_unless(count(array_diff($requested, $valid)) === 0, 422, 'Onbekend recht.');

        DB::transaction(function () use ($role, $requested) {
            DB::table('portal_role_permissions')->where('role', $role)->delete();
            foreach ($requested as $permission) {
                DB::table('portal_role_permissions')->insert([
                    'role' => $role, 'permission' => $permission,
                    'created_at' => now(), 'updated_at' => now(),
                ]);
            }
        });

        return back()->with('success', 'Rechten voor '.LibraryAccess::ROLES[$role].' opgeslagen.');
    }

    public function users(Request $request, LibraryAccess $access): View
    {
        $access->requirePermission($request, 'beheer.users.manage');
        $users = DB::table('portal_staff')->orderBy('role')->orderBy('smartschool_username')->get();
        $roles = LibraryAccess::ROLES;
        return view('beheer.users', compact('users', 'roles'));
    }

    public function storeUser(Request $request, LibraryAccess $access): RedirectResponse
    {
        $access->requirePermission($request, 'beheer.users.manage');
        $data = $request->validate([
            'smartschool_username' => ['required', 'string', 'max:120', 'regex:/^[a-zA-Z0-9][a-zA-Z0-9._-]+$/'],
            'display_name' => ['nullable', 'string', 'max:160'],
            'role' => ['required', Rule::in(array_keys(LibraryAccess::ROLES))],
        ]);
        $username = mb_strtolower(trim($data['smartschool_username']));

        $exists = DB::table('portal_staff')->where('smartschool_username', $username)->exists();
        if ($exists) return back()->withErrors(['smartschool_username' => 'Deze gebruiker bestaat al. Gebruik Bewerken.'])->withInput();

        DB::table('portal_staff')->insert([
            'smartschool_username' => $username,
            'display_name' => trim($data['display_name'] ?? '') ?: null,
            'role' => $data['role'],
            'is_active' => 1,
            'changed_by' => $access->username($request),
            'created_at' => now(), 'updated_at' => now(),
        ]);
        return back()->with('success', 'Medewerker toegevoegd.');
    }

    public function updateUser(Request $request, LibraryAccess $access, int $staff): RedirectResponse
    {
        $access->requirePermission($request, 'beheer.users.manage');
        $data = $request->validate([
            'display_name' => ['nullable', 'string', 'max:160'],
            'role' => ['required', Rule::in(array_keys(LibraryAccess::ROLES))],
            'is_active' => ['required', 'in:0,1'],
        ]);
        $person = DB::table('portal_staff')->where('id', $staff)->first();
        abort_unless($person, 404);

        return DB::transaction(function () use ($request, $access, $staff, $person, $data) {
            // Keep at least one active ICT coordinator; prevent own privilege removal.
            $self = $person->smartschool_username === $access->username($request);
            if ($self && ($data['role'] !== 'ict_coordinator' || $data['is_active'] !== '1')) {
                return back()->withErrors(['role' => 'Je kunt je eigen ICT-beheerrechten niet intrekken.']);
            }
            if ($person->role === 'ict_coordinator' && (int) $person->is_active === 1
                && ($data['role'] !== 'ict_coordinator' || $data['is_active'] !== '1')
                && DB::table('portal_staff')->where('role', 'ict_coordinator')->where('is_active', 1)->count() <= 1) {
                return back()->withErrors(['role' => 'Er moet minstens één actieve ICT-coördinator blijven.']);
            }
            DB::table('portal_staff')->where('id', $staff)->update([
                'display_name' => trim($data['display_name'] ?? '') ?: null,
                'role' => $data['role'],
                'is_active' => (int) $data['is_active'],
                'changed_by' => $access->username($request),
                'updated_at' => now(),
            ]);
            return back()->with('success', 'Gebruiker aangepast.');
        });
    }
}
