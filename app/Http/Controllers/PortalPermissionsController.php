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
        $access->requirePermission($request, 'beheer.permissions.manage');
        $modules = config('portal_permissions', []);
        $roles = LibraryAccess::LABELS + LibraryAccess::EXTERNAL_ROLES;
        $stored = DB::table('portal_role_permissions')->get()->groupBy('role')
            ->map(fn ($rows) => $rows->pluck('permission')->all())->all();
        return view('beheer.permissions', compact('modules', 'roles', 'stored'));
    }

    public function update(Request $request, LibraryAccess $access): RedirectResponse
    {
        $access->requireIctManager($request);
        abort_unless($access->role($request)==='ict_coordinator',403);
        $data = $request->validate([
            'role' => ['required', Rule::in(array_keys(LibraryAccess::ROLES + LibraryAccess::EXTERNAL_ROLES))],
            'permissions' => ['sometimes', 'array'],
            'permissions.*' => ['string', 'max:100'],
        ]);

        $role = $data['role'];
        abort_if($role === 'ict_coordinator', 403, 'ICT-beheerrechten staan vast.');
        $valid = collect(config('portal_permissions', []))
            ->flatMap(fn ($module) => array_keys($module['permissions'] ?? []))->all();
        $requested = array_values(array_unique($data['permissions'] ?? []));
        $external=array_key_exists($role,LibraryAccess::EXTERNAL_ROLES);
        $valid=array_values(array_filter($valid,fn($p)=>$external ? str_starts_with($p,$role.'.') : !str_starts_with($p,'leerling.') && !str_starts_with($p,'coaccount.')));
        abort_unless(count(array_diff($requested, $valid)) === 0, 422, 'Onbekend recht.');

        DB::transaction(function () use ($role, $requested, $request, $access) {
            $before=DB::table('portal_role_permissions')->where('role',$role)->pluck('permission')->all();
            DB::table('portal_permission_audit')->insert(['actor'=>$access->username($request),'target'=>'rol:'.$role,'changes'=>json_encode(['before'=>$before,'after'=>$requested]),'created_at'=>now(),'updated_at'=>now()]);
            DB::table('portal_role_permissions')->where('role', $role)->delete();
            foreach ($requested as $permission) {
                DB::table('portal_role_permissions')->insert([
                    'role' => $role, 'permission' => $permission,
                    'created_at' => now(), 'updated_at' => now(),
                ]);
            }
        });
        return back()->with('success', 'Rechten voor '.(LibraryAccess::LABELS + LibraryAccess::EXTERNAL_ROLES)[$role].' opgeslagen.');
    }

    public function individual(Request $request, LibraryAccess $access): View
    {
        $access->requirePermission($request,'beheer.permissions.manage');
        $q=trim((string)$request->query('q',''));
        $users=DB::table('users as u')->leftJoin('groups as g','g.id','=','u.groupId')
            ->where(fn($q)=>$q->whereNull('u.smartschool_active')->orWhere('u.smartschool_active',1))
            ->when($q!=='',fn($x)=>$x->where(fn($x)=>$x->where('u.name','like','%'.$q.'%')->orWhere('u.voornaam','like','%'.$q.'%')->orWhere('u.smartschool_gebruikersnaam','like','%'.$q.'%')))
            ->select('u.id','u.name','u.voornaam','u.smartschool_gebruikersnaam','g.groupname')->orderBy('u.name')->paginate(30)->withQueryString();
        $selected=DB::table('users as u')->leftJoin('groups as g','g.id','=','u.groupId')->where('u.id',(int)$request->query('user_id'))->select('u.id','u.name','u.voornaam','g.groupname')->first();
        $role=$selected ? collect(LibraryAccess::ROLES)->search(fn($name)=>mb_strtolower($name)===mb_strtolower((string)$selected->groupname)) : false;
        $overrides=$selected?DB::table('portal_user_permissions')->where('user_id',$selected->id)->pluck('allowed','permission')->all():[];
        $inherited=$role?DB::table('portal_role_permissions')->where('role',$role)->pluck('permission')->all():[];
        $modules=collect(config('portal_permissions'))->except(['leerling','coaccount'])->all();
        return view('beheer.individual-permissions',compact('users','selected','role','overrides','inherited','modules','q'));
    }
    public function saveIndividual(Request $request,LibraryAccess $access,int $user): RedirectResponse
    {
        $access->requirePermission($request,'beheer.permissions.manage');
        // Rights administrators cannot elevate themselves or grant privileges they lack.
        abort_unless($access->role($request)==='ict_coordinator',403);
        $data=$request->validate(['rights'=>'required|array','rights.*'=>'required|in:inherit,allow,deny']);
        $target=DB::table('users as u')->join('groups as g','g.id','=','u.groupId')->where('u.id',$user)->select('u.id','g.groupname')->first();
        abort_unless($target && mb_strtolower($target->groupname)!==mb_strtolower(LibraryAccess::ROLES['ict_coordinator']),403);
        $valid=collect(config('portal_permissions'))->except(['leerling','coaccount'])->flatMap(fn($m)=>array_keys($m['permissions']))->all();
        abort_if(array_diff(array_keys($data['rights']),$valid),422);
        DB::transaction(function()use($data,$user,$access,$request){
            $before=DB::table('portal_user_permissions')->where('user_id',$user)->pluck('allowed','permission')->all();
            foreach($data['rights'] as $key=>$value){
                $query=DB::table('portal_user_permissions')->where('user_id',$user)->where('permission',$key);
                if($value==='inherit') $query->delete();
                else DB::table('portal_user_permissions')->updateOrInsert(['user_id'=>$user,'permission'=>$key],['allowed'=>$value==='allow','updated_at'=>now()]);
            }
            DB::table('portal_permission_audit')->insert(['actor'=>$access->username($request),'target'=>'user:'.$user,'changes'=>json_encode(['before'=>$before,'after'=>DB::table('portal_user_permissions')->where('user_id',$user)->pluck('allowed','permission')->all()]),'created_at'=>now(),'updated_at'=>now()]);
        });
        return redirect()->route('beheer.permissions.individual',['user_id'=>$user])->with('success','Individuele rechten opgeslagen.');
    }

    public function users(Request $request, LibraryAccess $access): View
    {
        $access->requirePermission($request, 'beheer.staff.manage');
        // Always read from the existing LVS tables; no second personnel database.
        $search = trim((string) $request->query('zoek', ''));
        $sortColumns = [
            'medewerker' => 'users.name',
            'account' => 'users.smartschool_gebruikersnaam',
            'groep' => 'groups.groupname',
            'aanmelden' => 'users.allowed_login',
        ];
        $sort = (string) $request->query('sort', 'groep');
        if (! array_key_exists($sort, $sortColumns)) $sort = 'groep';
        $direction = strtolower((string) $request->query('richting', 'asc')) === 'desc' ? 'desc' : 'asc';
        $users = DB::table('users')->leftJoin('groups', 'groups.id', '=', 'users.groupId')
            // Smartschool-inactieve medewerkers blijven altijd buiten de beheerlijst.
            ->where(function ($q) {
                $q->whereNull('users.smartschool_active')->orWhere('users.smartschool_active', 1);
            })
            ->when(! $request->boolean('toon_geblokkeerd'), fn ($q) => $q->where('users.allowed_login', 1))
            ->when($search !== '', function ($query) use ($search) {
                $term = '%'.addcslashes(mb_substr($search, 0, 100), '%_\\').'%';
                $query->where(function ($q) use ($term) {
                    $q->where('users.voornaam', 'like', $term)
                        ->orWhere('users.name', 'like', $term)
                        ->orWhere('users.smartschool_gebruikersnaam', 'like', $term)
                        ->orWhere('groups.groupname', 'like', $term);
                });
            })
            ->select('users.id', 'users.gebruikersnaam', 'users.voornaam', 'users.name',
                'users.groupId', 'users.allowed_login', 'users.smartschool_active', 'users.smartschool_gebruikersnaam', 'groups.groupname')
            ->when($sort === 'groep', function ($query) use ($direction) {
                $query->orderByRaw("CASE groups.groupname
                    WHEN 'ICT-coördinator' THEN 0 WHEN 'directeur' THEN 1
                    WHEN 'secretariaat' THEN 2 WHEN 'leerlingbegeleider' THEN 3
                    WHEN 'boekhouding' THEN 4 WHEN 'TAC' THEN 5
                    WHEN 'administratie' THEN 6 WHEN 'leerkracht' THEN 7
                    ELSE 8 END ".($direction === 'desc' ? 'DESC' : 'ASC'));
            }, fn ($query) => $query->orderBy($sortColumns[$sort], $direction))
            ->orderBy('users.name')->orderBy('users.voornaam')->orderBy('users.id')
            ->paginate(40)->withQueryString();
        $roles = LibraryAccess::LABELS;
        $groups = DB::table('groups')->pluck('id', 'groupname');
        return view('beheer.users', compact('users', 'roles', 'groups'));
    }

    public function searchUsers(Request $request, LibraryAccess $access): \Illuminate\Http\JsonResponse
    {
        $access->requirePermission($request, 'beheer.staff.manage');

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
        $access->requirePermission($request, 'beheer.staff.manage');
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
        $access->requirePermission($request, 'beheer.staff.manage');
        $data = $request->validate([
            'role' => ['required', Rule::in(array_keys(LibraryAccess::ROLES))],
        ]);
        return $this->changeGroup($request, $access, $staff, $data['role']);
    }

    public function updateLogin(Request $request, LibraryAccess $access, int $staff): RedirectResponse
    {
        $access->requirePermission($request, 'beheer.staff.manage');
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

    public function bulkUpdate(Request $request, LibraryAccess $access): RedirectResponse
    {
        $access->requirePermission($request, 'beheer.staff.manage');
        $data = $request->validate([
            'ids' => ['required', 'array', 'min:1', 'max:100'],
            'ids.*' => ['required', 'integer', 'distinct', 'min:1'],
            'action' => ['required', Rule::in(['role', 'allow', 'block'])],
            'role' => ['required_if:action,role', 'nullable', Rule::in(array_keys(LibraryAccess::ROLES))],
        ]);

        $ids = array_map('intval', $data['ids']);
        $action = $data['action'];
        DB::transaction(function () use ($request, $access, $ids, $action, $data) {
            $staff = DB::table('users')->whereIn('id', $ids)->lockForUpdate()->get();
            if ($staff->count() !== count($ids)) {
                throw \Illuminate\Validation\ValidationException::withMessages(['ids' => 'Een geselecteerd personeelsaccount bestaat niet meer.']);
            }

            $self = $access->username($request);
            $targetRole = $action === 'role' ? $data['role'] : null;
            $targetGroupId = $action === 'role' ? $this->groupId($targetRole) : null;
            $ictId = $this->groupId('ict_coordinator');
            $activeIct = DB::table('users')->where('groupId', $ictId)->where('allowed_login', 1)->count();

            $removedIct = 0;
            foreach ($staff as $user) {
                if ($user->smartschool_active !== null && (int) $user->smartschool_active === 0) {
                    throw \Illuminate\Validation\ValidationException::withMessages(['ids' => 'Een geselecteerd account is niet meer actief in Smartschool.']);
                }
                $isSelf = mb_strtolower((string) $user->smartschool_gebruikersnaam) === $self;
                if ($isSelf && ($action === 'block' || ($action === 'role' && $targetRole !== 'ict_coordinator'))) {
                    throw \Illuminate\Validation\ValidationException::withMessages(['ids' => 'Je kunt je eigen ICT-beheerrechten of aanmeldtoegang niet verwijderen.']);
                }
                if ((int) $user->groupId === $ictId && (int) $user->allowed_login === 1
                    && ($action === 'block' || ($action === 'role' && $targetGroupId !== $ictId))) {
                    $removedIct++;
                }
            }
            if ($activeIct - $removedIct < 1) {
                throw \Illuminate\Validation\ValidationException::withMessages(['ids' => 'Er moet minstens één actieve ICT-coördinator overblijven.']);
            }

            if ($action === 'role') {
                DB::table('users')->whereIn('id', $ids)->update(['groupId' => $targetGroupId]);
            } else {
                DB::table('users')->whereIn('id', $ids)->update(['allowed_login' => $action === 'allow' ? 1 : 0]);
            }
        });

        return back()->with('success', count($ids).' personeelsaccounts in één keer bijgewerkt.');
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
