<?php

namespace App\Http\Controllers;

use App\Services\LibraryAccess;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

class StudentManagementController extends Controller
{
    public function index(Request $request, LibraryAccess $access): View
    {
        $access->requirePermission($request, 'beheer.students.manage');
        $search = mb_substr(trim((string) $request->query('zoek', '')), 0, 100);
        $showInactive = $request->boolean('toon_inactief');
        $classId=max(0,(int)$request->query('klas',0));
        $classes=DB::table('klassen')->whereIn('id',DB::table('leerlingen')->where('schoolid',1)->whereNotNull('klasid')->select('klasid'))->orderBy('klasnaam')->get(['id','klasnaam']);
        $sortColumns = [
            'leerling' => 'leerlingen.naam',
            'klas' => 'klassen.klasnaam',
            'account' => 'leerlingen.gebruikersnaam',
            'status' => 'leerlingen.active',
            'stamnummer'=>'leerlingen.stamnummer',
            'internnummer'=>'leerlingen.InformatInternNr',
        ];
        $sort = (string) $request->query('sort', 'klas');
        if (! array_key_exists($sort, $sortColumns)) $sort = 'klas';
        $direction = strtolower((string) $request->query('richting', 'asc')) === 'desc' ? 'desc' : 'asc';

        $students = DB::table('leerlingen')
            ->leftJoin('klassen', 'klassen.id', '=', 'leerlingen.klasid')
            ->where('leerlingen.schoolid', 1)
            ->when($classId>0,fn($q)=>$q->where('leerlingen.klasid',$classId))
            ->when(! $showInactive, fn ($q) => $q->where('leerlingen.active', 1))
            ->when($search !== '', function ($query) use ($search) {
                $term = '%'.addcslashes($search, '%_\\').'%';
                $query->where(function ($q) use ($term) {
                    $q->where('leerlingen.voornaam', 'like', $term)
                        ->orWhere('leerlingen.naam', 'like', $term)
                        ->orWhere('leerlingen.gebruikersnaam', 'like', $term)
                        ->orWhere('klassen.klasnaam', 'like', $term)
                        ->orWhere('leerlingen.stamnummer','like',$term)
                        ->orWhere('leerlingen.InformatInternNr','like',$term);
                });
            })
            ->select('leerlingen.id', 'leerlingen.voornaam', 'leerlingen.naam', 'leerlingen.gebruikersnaam',
                'leerlingen.active', 'leerlingen.geboortedatum', 'leerlingen.stamnummer','leerlingen.InformatInternNr','klassen.klasnaam')
            ->when($sort==='klas',fn($q)=>$q->orderByRaw("CASE WHEN klassen.klasnaam IS NULL OR klassen.klasnaam = '' THEN 1 ELSE 0 END"))
            ->orderBy($sortColumns[$sort], $direction)
            ->orderBy('leerlingen.naam')->orderBy('leerlingen.voornaam', $sort==='leerling'?$direction:'asc')->orderBy('leerlingen.id')
            ->paginate(40)->withQueryString();
        return view('beheer.students.index', compact('students', 'search', 'showInactive','classes','classId'));
    }

    public function bulkRights(Request $request, LibraryAccess $access): RedirectResponse
    {
        abort_unless($access->role($request)==='ict_coordinator',403);
        $data=$request->validate([
            'ids'=>'required|array|min:1|max:100',
            'ids.*'=>'required|integer|min:1|distinct',
            'rights'=>'required|array',
            'rights.*'=>'required|in:unchanged,inherit,allow,deny',
        ]);
        $known=array_merge(array_keys(config('portal_permissions.leerling.permissions',[])),array_keys(config('portal_permissions.bibliotheek.permissions',[])));
        abort_if(array_diff(array_keys($data['rights']),$known),422);
        $changes=array_filter($data['rights'],fn($v)=>$v!=='unchanged');
        if (!$changes) return back()->withErrors(['rights'=>'Selecteer minstens een recht om te wijzigen.']);
        $ids=array_map('intval',$data['ids']);
        DB::transaction(function()use($ids,$changes,$access,$request){
            $students=DB::table('leerlingen')->where('schoolid',1)->whereIn('id',$ids)->orderBy('id')->lockForUpdate()->pluck('id');
            abort_unless($students->count()===count($ids),422);
            foreach($students as $id){
                $before=DB::table('portal_student_permissions')->where('leerling_id',$id)->pluck('allowed','permission')->all();
                foreach($changes as $key=>$value){
                    $query=DB::table('portal_student_permissions')->where('leerling_id',$id)->where('permission',$key);
                    if($value==='inherit') $query->delete();
                    else DB::table('portal_student_permissions')->updateOrInsert(['leerling_id'=>$id,'permission'=>$key],['allowed'=>$value==='allow','updated_at'=>now()]);
                }
                DB::table('portal_permission_audit')->insert(['actor'=>$access->username($request),'target'=>'leerling:'.$id,'changes'=>json_encode(['before'=>$before,'after'=>DB::table('portal_student_permissions')->where('leerling_id',$id)->pluck('allowed','permission')->all()]),'created_at'=>now(),'updated_at'=>now()]);
            }
        });
        return back()->with('success',count($ids).' leerlingen: individuele rechten bijgewerkt.');
    }

    public function bulkUpdate(Request $request, LibraryAccess $access): RedirectResponse
    {
        $access->requirePermission($request, 'beheer.students.status');
        $data = $request->validate([
            'ids' => ['required', 'array', 'min:1', 'max:100'],
            'ids.*' => ['required', 'integer', 'min:1', 'distinct'],
            'active' => ['required', 'in:0,1'],
        ]);
        $ids = array_map('intval', $data['ids']);
        DB::transaction(function () use ($ids, $data) {
            $rows = DB::table('leerlingen')->whereIn('id', $ids)
                ->where('schoolid', 1)->lockForUpdate()->get(['id']);
            if ($rows->count() !== count($ids)) {
                throw \Illuminate\Validation\ValidationException::withMessages([
                    'ids' => 'Een of meer leerlingen werden niet gevonden binnen deze school.',
                ]);
            }
            DB::table('leerlingen')->where('schoolid', 1)->whereIn('id', $ids)
                ->update(['active' => (int) $data['active'], 'updated_at' => now()]);
        });
        return back()->with('success', count($ids).' leerlingen bijgewerkt. Let op: de volgende Smartschool-sync kan de actieve status opnieuw instellen.');
    }

    public function show(Request $request, LibraryAccess $access, int $student): View
    {
        $access->requirePermission($request, 'beheer.students.manage');
        $studentRow = DB::table('leerlingen')
            ->leftJoin('klassen', 'klassen.id', '=', 'leerlingen.klasid')
            ->where('leerlingen.id', $student)
            ->where('leerlingen.schoolid', 1)
            ->select('leerlingen.*', 'klassen.klasnaam')
            ->first();
        abort_unless($studentRow, 404);

        return view('beheer.students.show', [
            'student' => $studentRow,
            'hasScanCode' => \App\Support\LegacyDatabaseSchema::hasColumn('leerlingen', 'scannable_code'),
        ]);
    }

    public function savePortalRights(Request $request, LibraryAccess $access, int $student): RedirectResponse
    {
        abort_unless($access->role($request)==='ict_coordinator',403);
        abort_unless(DB::table('leerlingen')->where('schoolid',1)->where('id',$student)->exists(),404);
        $data=$request->validate(['rights'=>'required|array','rights.*'=>'required|in:inherit,allow,deny']);
        $valid=array_merge(array_keys(config('portal_permissions.leerling.permissions',[])),array_keys(config('portal_permissions.bibliotheek.permissions',[])));
        abort_if(array_diff(array_keys($data['rights']),$valid),422);
        DB::transaction(function()use($data,$student,$access,$request){
            $before=DB::table('portal_student_permissions')->where('leerling_id',$student)->pluck('allowed','permission')->all();
            foreach($data['rights'] as $key=>$value){
                if($value==='inherit') DB::table('portal_student_permissions')->where('leerling_id',$student)->where('permission',$key)->delete();
                else DB::table('portal_student_permissions')->updateOrInsert(['leerling_id'=>$student,'permission'=>$key],['allowed'=>$value==='allow','updated_at'=>now()]);
            }
            DB::table('portal_permission_audit')->insert(['actor'=>$access->username($request),'target'=>'leerling:'.$student,'changes'=>json_encode(['before'=>$before,'after'=>DB::table('portal_student_permissions')->where('leerling_id',$student)->pluck('allowed','permission')->all()]),'created_at'=>now(),'updated_at'=>now()]);
        });
        return back()->with('success','Individuele leerlingrechten opgeslagen.');
    }

    public function update(Request $request, LibraryAccess $access, int $student): RedirectResponse
    {
        $access->requirePermission($request, 'beheer.students.edit');
        $data = $request->validate([
            'voornaam' => ['required', 'string', 'max:120'],
            'naam' => ['required', 'string', 'max:120'],
            'geboortedatum' => ['nullable', 'date_format:Y-m-d', 'before_or_equal:today'],
            'email' => ['nullable', 'email', 'max:255'],
        ]);
        $exists = DB::table('leerlingen')->where('id', $student)->where('schoolid', 1)->exists();
        abort_unless($exists, 404);

        DB::table('leerlingen')->where('id', $student)->where('schoolid', 1)->update([
            'voornaam' => trim($data['voornaam']),
            'naam' => trim($data['naam']),
            'geboortedatum' => $data['geboortedatum'] ?? null,
            'e-mailadres' => $data['email'] ?? null,
            'updated_at' => now(),
        ]);
        return redirect()->route('beheer.students.show', $student)
            ->with('success', 'Leerlinggegevens opgeslagen. Smartschool-gegevens kunnen bij een volgende synchronisatie opnieuw worden bijgewerkt.');
    }
}
