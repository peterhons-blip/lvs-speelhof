<?php

namespace App\Http\Controllers;

use App\Services\LibraryAccess;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class PortalPermissionsController extends Controller
{
    private function groupId(string $role): int
    {
        $name = LibraryAccess::ROLES[$role] ?? null;
        abort_unless($name, 422);

        $id = DB::table('groups')->where('groupname', $name)->value('id');
        abort_unless($id, 422, "Schoolgroep {$name} bestaat niet. Voer de groepsmigratie uit.");
        return (int) $id;
    }

    public function index(Request $request, LibraryAccess $access): View
    {
        $access->requireManager($request);
        $modules = config('portal_permissions', []);
        $roles = LibraryAccess::LABELS;
        $stored = DB::table('portal_role_permissions')->get()->groupBy('role')
            ->map(fn ($rows) => $rows->pluck('permission')->all())->all();
        return view('beheer.permissions', compact('modules', 'roles', 'stored'));
    }

    public function update(Request $request, LibraryAccess $access): RedirectResponse
    {
        $access->requireManager($request);
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
        abort_if(in_array('beheer.users.manage', $requested, true)
            || in_array('beheer.permissions.manage', $requested, true), 422,
            'Gebruikers- en rechtenbeheer is uitsluitend voor ICT-coördinatoren.');
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
        return back()->with('success', 'Rechten voor '.LibraryAccess::LABELS[$role].' opgeslagen.');
    }

    public function users(Request $request, LibraryAccess $access): View
    {
        $access->requireManager($request);
        // Always read from the existing LVS tables; no second personnel database.
        $users = DB::table('users')->leftJoin('groups', 'groups.id', '=', 'users.groupId')
            ->when(! $request->boolean('toon_geblokkeerd'), fn ($query) => $query->where('users.allowed_login', 1)->where(function ($q) {
                $q->whereNull('users.smartschool_active')->orWhere('users.smartschool_active', 1);
            }))
            ->select('users.id', 'users.gebruikersnaam', 'users.voornaam', 'users.name',
                'users.groupId', 'users.allowed_login', 'users.smartschool_active', 'users.smartschool_gebruikersnaam', 'groups.groupname')
            ->orderByRaw("CASE groups.groupname
                WHEN 'ICT-coördinator' THEN 0
                WHEN 'directeur' THEN 1
                WHEN 'secretariaat' THEN 2
                WHEN 'leerlingbegeleider' THEN 3
                WHEN 'boekhouding' THEN 4
                WHEN 'TAC' THEN 5
                WHEN 'administratie' THEN 6
                WHEN 'leerkracht' THEN 7
                ELSE 7 END")
            ->orderBy('users.name')->orderBy('users.voornaam')->paginate(40)->withQueryString();
        $roles = LibraryAccess::LABELS;
        $groups = DB::table('groups')->pluck('id', 'groupname');
        return view('beheer.users', compact('users', 'roles', 'groups'));
    }

    public function searchUsers(Request $request, LibraryAccess $access): \Illuminate\Http\JsonResponse
    {
        $access->requireManager($request);

        $data = $request->validate(['q' => ['required', 'string', 'min:2', 'max:80']]);
        $term = trim($data['q']);
        if (mb_strlen($term) < 2) return response()->json([]);

        $users = DB::table('users')
            ->leftJoin('groups', 'groups.id', '=', 'users.groupId')
            ->where('users.allowed_login', 1)
            ->where(function ($q) { $q->whereNull('users.smartschool_active')->orWhere('users.smartschool_active', 1); })
            ->where(function ($query) use ($term) {
                $query->where('users.gebruikersnaam', 'like', '%'.$term.'%')
                    ->orWhere('users.smartschool_gebruikersnaam', 'like', '%'.$term.'%')
                    ->orWhere('users.voornaam', 'like', '%'.$term.'%')
                    ->orWhere('users.name', 'like', '%'.$term.'%');
            })
            ->select('users.gebruikersnaam', 'users.smartschool_gebruikersnaam', 'users.voornaam', 'users.name',
                'groups.groupname')
            ->orderBy('users.name')->orderBy('users.voornaam')
            ->limit(12)->get();

        return response()->json($users);
    }

    public function storeUser(Request $request, LibraryAccess $access): RedirectResponse
    {
        $access->requireManager($request);
        $data = $request->validate([
            'smartschool_username' => ['required', 'string', 'max:120'],
            'role' => ['required', Rule::in(array_keys(LibraryAccess::ROLES))],
        ]);
        // Only assign a role to an existing user, registered by Smartschool sync.
        $username = trim($data['smartschool_username']);
        $user = DB::table('users')->where('smartschool_gebruikersnaam', $username)->first();
        if (! $user) {
            return back()->withErrors(['smartschool_username' =>
                'Gebruiker niet gevonden in de bestaande users-tabel. Synchroniseer de medewerker eerst.'])->withInput();
        }

        return $this->changeGroup($request, $access, (int) $user->id, $data['role']);
    }

    public function updateUser(Request $request, LibraryAccess $access, int $staff): RedirectResponse
    {
        $access->requireManager($request);
        $data = $request->validate([
            'role' => ['required', Rule::in(array_keys(LibraryAccess::ROLES))],
        ]);
        return $this->changeGroup($request, $access, $staff, $data['role']);
    }

    public function updateLogin(Request $request, LibraryAccess $access, int $staff): RedirectResponse
    {
        $access->requireManager($request);
        $data = $request->validate(['allowed_login' => ['required', 'in:0,1']]);
        $allowed = (int) $data['allowed_login'];

        return DB::transaction(function () use ($request, $access, $staff, $allowed) {
            $user = DB::table('users')->where('id', $staff)->lockForUpdate()->first();
            abort_unless($user, 404);

            $self = mb_strtolower((string) $user->smartschool_gebruikersnaam) === $access->username($request);
            if ($self && $allowed === 0) {
                return back()->withErrors(['allowed_login' => 'Je kunt je eigen aanmeldtoegang niet uitschakelen.']);
            }

            if ($allowed === 0 && (int) $user->allowed_login === 1) {
                $ictGroupId = $this->groupId('ict_coordinator');
                if ((int) $user->groupId === $ictGroupId) {
                    $activeIct = DB::table('users')->where('groupId', $ictGroupId)
                        ->where('allowed_login', 1)->count();
                    if ($activeIct <= 1) {
                        return back()->withErrors(['allowed_login' =>
                            'De laatste actieve ICT-coördinator kan niet geblokkeerd worden.']);
                    }
                }
            }

            if ($allowed === 1 && $user->smartschool_active !== null && (int) $user->smartschool_active === 0) {
                return back()->withErrors(['allowed_login' => 'Dit account staat als niet actief in Smartschool. Activeer het eerst via de Smartschool-sync.']);
            }
            DB::table('users')->where('id', $staff)->update(['allowed_login' => $allowed]);
            return back()->with('success', 'Aanmeldtoegang voor '.$user->gebruikersnaam
                .($allowed ? ' toegestaan.' : ' geblokkeerd.'));
        });
    }

    private function changeGroup(Request $request, LibraryAccess $access, int $userId, string $role): RedirectResponse
    {
        $targetGroupId = $this->groupId($role);

        return DB::transaction(function () use ($request, $access, $userId, $role, $targetGroupId) {
            $user = DB::table('users')->where('id', $userId)->lockForUpdate()->first();
            abort_unless($user, 404);
            $self = mb_strtolower((string) $user->smartschool_gebruikersnaam) === $access->username($request);
            if ($self && $role !== 'ict_coordinator') {
                return back()->withErrors(['role' => 'Je kunt je eigen ICT-beheerrol niet intrekken.']);
            }

            $ictGroupId = $this->groupId('ict_coordinator');
            if ((int) $user->groupId === $ictGroupId && $role !== 'ict_coordinator') {
                $count = DB::table('users')->where('groupId', $ictGroupId)->where('allowed_login', 1)->count();
                if ((int) $user->allowed_login === 1 && $count <= 1) {
                    return back()->withErrors(['role' => 'Er moet minstens één actieve ICT-coördinator blijven.']);
                }
            }

            DB::table('users')->where('id', $userId)->update(['groupId' => $targetGroupId]);
            return back()->with('success', 'Schoolrol van '.($user->gebruikersnaam ?: 'gebruiker'). ' aangepast.');
        });
    }
}
