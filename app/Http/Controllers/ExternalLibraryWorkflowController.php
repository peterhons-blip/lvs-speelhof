<?php
namespace App\Http\Controllers;

use App\Services\ExternalPortalAccess;
use App\Services\LibraryAccess;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class ExternalLibraryWorkflowController extends Controller
{
    public function submitStaff(Request $request,LibraryAccess $access)
    {
        $access->requirePermission($request,'persoonlijke_bibliotheek.view');
        $id=$access->staffId($request);
        abort_unless($id,403);
        return $this->submitFor($request,$id,'staff',fn($p)=>$access->personalLibraryCan($request,$p),'personal-library.index');
    }

    public function submit(Request $request,ExternalPortalAccess $access)
    {
        $student=$request->attributes->get('external_student');
        abort_unless($student,403);
        $role=$request->session()->get('ss_user.principal_type');
        return $this->submitFor($request,(int)$student->id,$role,fn($p)=>$access->has($role,$p,(int)$student->id),'external.dashboard');
    }

    private function submitFor(Request $request,int $id,string $role,\Closure $can,string $portalRoute)
    {
        $simulate=$request->session()->has('portal_preview');
        $staff=$role==='staff';
        $owner=$staff?'staff_user_id':'leerling_id';
        $data=$request->validate(['type'=>'required|in:reserve,renew','target_id'=>'required|integer|min:1']);
        $target=$data['target_id']??null;
        $payload=null;
        if ($data['type']==='reserve') {
            abort_unless($can('library.reserve') && $can('library.catalog.view'),403);
            abort_unless($target && DB::table('library_books')->where('id',$target)->exists(),404);
        } elseif ($data['type']==='renew') {
            abort_unless($can('library.renew.request') && $can('library.view'),403);
            abort_unless($target && DB::table('library_loans')->where('id',$target)->where($owner,$id)->whereNull($staff?'leerling_id':'staff_user_id')->whereNull('returned_at')->exists(),404);
        }
        if ($simulate) return back()->with('success','Testmodus: aanvraag is geldig. Er is geen echte aanvraag opgeslagen.');
        DB::transaction(function()use($id,$role,$data,$target,$payload,$staff,$owner){
            DB::table($staff?'users':'leerlingen')->where('id',$id)->lockForUpdate()->first();
            $pending=DB::table('external_library_requests')->where($owner,$id)->where('status','pending');
            if ((clone $pending)->count()>=20) throw \Illuminate\Validation\ValidationException::withMessages(['request'=>'Je hebt te veel openstaande aanvragen.']);
            if ((clone $pending)->where('request_type',$data['type'])->where('target_id',$target)->exists()) throw \Illuminate\Validation\ValidationException::withMessages(['request'=>'Er bestaat al een openstaande aanvraag voor dit boek.']);
            if ($data['type']==='reserve' && app(\App\Services\LibraryReservations::class)->active()->where($owner,$id)->where('target_id',$target)->exists()) throw \Illuminate\Validation\ValidationException::withMessages(['request'=>'Dit boek is al voor jou gereserveerd.']);
            DB::table('external_library_requests')->insert(['leerling_id'=>$staff?null:$id,'staff_user_id'=>$staff?$id:null,'request_type'=>$data['type'],'target_id'=>$target,'principal_type'=>$role,'payload'=>$payload,'status'=>'pending','created_at'=>now(),'updated_at'=>now()]);
        });
        return redirect()->route($portalRoute,['section'=>$data['type']==='reserve'?'reservations':'renewals'])->with('success','Aanvraag doorgestuurd naar de bibliotheek.');
    }

    public function index(Request $request,LibraryAccess $access)
    {
        $access->requirePermission($request,'bibliotheek.view');
        $access->requirePermission($request,'bibliotheek.requests.manage');
        $base=DB::table('external_library_requests as r')->leftJoin('leerlingen as l','l.id','=','r.leerling_id')->leftJoin('users as u','u.id','=','r.staff_user_id')->where(fn($q)=>$q->where('l.schoolid',1)->orWhere('u.schoolid',1))->whereIn('r.request_type',['reserve','renew'])->select('r.*')->selectSub(DB::table('library_books')->whereColumn('library_books.id','r.target_id')->select('title'),'requested_book')->selectSub(DB::table('library_loans as rqloan')->join('library_copies as rqcopy','rqcopy.id','=','rqloan.copy_id')->join('library_books as rqbook','rqbook.id','=','rqcopy.book_id')->whereColumn('rqloan.id','r.target_id')->select('rqbook.title'),'renew_book')->selectRaw('COALESCE(u.voornaam,l.voornaam) as voornaam, COALESCE(u.name,l.naam) as naam');
        $type=$request->query('type')==='renew'?'renew':'reserve';
        $base->where('r.request_type',$type);
        $today=now('Europe/Brussels')->toDateString();
        $active=fn($q)=>$q->where('r.status','approved')->where('r.request_type','reserve')->where('r.reserved_until','>=',$today);
        $counts=['pending'=>(clone $base)->where('r.status','pending')->count(),'pickup'=>$active(clone $base)->count(),'all'=>(clone $base)->count()];
        $status=(string)$request->query('status','pending');
        if (!in_array($status,$type==='renew'?['pending','history','all']:['pending','pickup','history','all'],true)) $status='pending';
        $query=clone $base;
        if ($status==='pending') $query->where('r.status','pending');
        if ($status==='pickup') $active($query);
        if ($status==='history') $query->where('r.status','<>','pending')->where(fn($q)=>$q->where('r.status','<>','approved')->orWhere('r.request_type','<>','reserve')->orWhereNull('r.reserved_until')->orWhere('r.reserved_until','<',$today));
        $principal=(string)$request->query('principal','');
        if (in_array($principal,['staff','leerling','coaccount'],true)) $query->where('r.principal_type',$principal);
        $search=mb_substr(trim((string)$request->query('q','')),0,100);
        if ($search!=='') {
            $term='%'.$search.'%';
            $query->where(function($q)use($term){
                $q->whereRaw("CONCAT_WS(' ',COALESCE(u.voornaam,l.voornaam),COALESCE(u.name,l.naam)) LIKE ?",[$term])
                  ->orWhereRaw("CONCAT_WS(' ',COALESCE(u.name,l.naam),COALESCE(u.voornaam,l.voornaam)) LIKE ?",[$term])
                  ->orWhereExists(DB::table('library_books')->selectRaw('1')->whereColumn('library_books.id','r.target_id')->where('r.request_type','reserve')->where('title','like',$term))
                  ->orWhereExists(DB::table('library_loans as sl')->join('library_copies as sc','sc.id','=','sl.copy_id')->join('library_books as sb','sb.id','=','sc.book_id')->selectRaw('1')->whereColumn('sl.id','r.target_id')->where('r.request_type','renew')->where('sb.title','like',$term));
            });
        }
        $query->selectSub(DB::table('library_copies')->whereColumn('library_copies.id','r.reserved_copy_id')->select('barcode'),'reserved_barcode');
        $query->selectSub(app(\App\Services\LibraryReservations::class)->available(DB::table('library_copies')->whereColumn('book_id','r.target_id')->where('status','beschikbaar'))->selectRaw('count(*)'),'available');
        $query->selectSub(DB::table('external_library_requests as older')->whereColumn('older.target_id','r.target_id')->whereColumn('older.id','<','r.id')->where('older.request_type','reserve')->where('older.status','pending')->selectRaw('count(*)'),'ahead');
        $requests=$query->orderByRaw("CASE WHEN r.status='pending' THEN 0 ELSE 1 END")->orderByRaw("CASE WHEN r.status='pending' THEN r.id END ASC")->orderByDesc('r.id')->paginate(30)->withQueryString();
        return view('bibliotheek.external-requests',compact('requests','counts','status','type','principal','search','today'));
    }

    public function cancel(Request $request,LibraryAccess $access,int $id)
    {
        $access->requirePermission($request,'bibliotheek.view');
        $access->requirePermission($request,'bibliotheek.requests.manage');
        $updated=DB::table('external_library_requests')->where('id',$id)->where('request_type','reserve')->where('status','approved')->where(fn($q)=>$q->whereIn('leerling_id',DB::table('leerlingen')->where('schoolid',1)->select('id'))->orWhereIn('staff_user_id',DB::table('users')->where('schoolid',1)->select('id')))->update(['status'=>'cancelled','updated_at'=>now()]);
        abort_unless($updated,409);
        return back()->with('success','Reservering geannuleerd.');
    }

    public function decide(Request $request,LibraryAccess $access,int $id)
    {
        $access->requirePermission($request,'bibliotheek.view');
        $access->requirePermission($request,'bibliotheek.requests.manage');
        $data=$request->validate(['status'=>'required|in:approved,rejected','note'=>'nullable|string|max:500']);
        $result=DB::transaction(function()use($id,$data,$access,$request){
            $row=DB::table('external_library_requests')->where('id',$id)->where('status','pending')->whereIn('request_type',['reserve','renew'])->lockForUpdate()->first();
            abort_unless($row,409);
            $staff=$row->principal_type==='staff';
            $owner=$staff?'staff_user_id':'leerling_id';
            abort_unless($row->$owner && DB::table($staff?'users':'leerlingen')->where('id',$row->$owner)->where('schoolid',1)->exists(),404);
            if ($data['status']==='approved') {
                $active=DB::table($staff?'users':'leerlingen')->where('id',$row->$owner)->where('schoolid',1);
                if($staff) $active->where('allowed_login',1)->where(fn($q)=>$q->whereNull('smartschool_active')->orWhere('smartschool_active',1));
                else $active->where('active',1);
                abort_unless($active->exists(),422,'Ontlener is niet actief.');
            }
            $values=['status'=>$data['status'],'decision_note'=>$data['note']??null,'decided_by'=>$access->username($request),'decided_at'=>now(),'updated_at'=>now()];
            $result=null;
            if ($data['status']==='approved') {
                $holds=app(\App\Services\LibraryReservations::class);
                if ($row->request_type==='reserve') {
                    $copies=DB::table('library_copies')->where('book_id',$row->target_id)->where('status','beschikbaar')->orderBy('id')->lockForUpdate()->get();
                    if(DB::table('external_library_requests')->where('request_type','reserve')->where('target_id',$row->target_id)->where('status','pending')->where('id','<',$row->id)->exists()) throw \Illuminate\Validation\ValidationException::withMessages(['reserve'=>'Behandel eerst de oudere reserveringsaanvraag voor dit boek.']);
                    $copy=$copies->first(fn($c)=>!(clone $holds->active())->where('reserved_copy_id',$c->id)->lockForUpdate()->first() && !DB::table('library_loans')->where('copy_id',$c->id)->whereNull('returned_at')->exists());
                    if (!$copy) throw \Illuminate\Validation\ValidationException::withMessages(['reserve'=>'Geen vrij exemplaar beschikbaar. De aanvraag blijft open.']);
                    $values['reserved_copy_id']=$copy->id;
                    $values['reserved_until']=app(\App\Services\SchoolCalendar::class)->shift(\Carbon\CarbonImmutable::today('Europe/Brussels'),5)->toDateString();
                } else {
                    $access->requirePermission($request,'bibliotheek.loans.extend');
                    $loan=DB::table('library_loans')->where('id',$row->target_id)->where($owner,$row->$owner)->whereNull($staff?'leerling_id':'staff_user_id')->whereNull('returned_at')->lockForUpdate()->first();
                    abort_unless($loan,422,'Geen openstaande uitlening.');
                    $calendar=app(\App\Services\LibraryCalendar::class);
                    if ((int)$loan->extensions>=max(0,(int)$calendar->setting('max_extensions','2'))) throw \Illuminate\Validation\ValidationException::withMessages(['renew'=>'Maximaal aantal verlengingen bereikt.']);
                    $book=DB::table('library_copies')->where('id',$loan->copy_id)->value('book_id');
                    if (DB::table('external_library_requests')->where('request_type','reserve')->where('target_id',$book)->where(fn($q)=>$q->whereNull($owner)->orWhere($owner,'<>',$row->$owner))->where(fn($q)=>$q->where('status','pending')->orWhere(fn($x)=>$x->where('status','approved')->where('reserved_until','>=',now('Europe/Brussels')->toDateString())))->exists()) throw \Illuminate\Validation\ValidationException::withMessages(['renew'=>'Een andere ontlener wacht op dit boek.']);
                    $today=\Carbon\CarbonImmutable::today('Europe/Brussels');
                    $old=\Carbon\CarbonImmutable::parse($loan->due_date,'Europe/Brussels');
                    $due=$calendar->due($old->gt($today)?$old:$today,max(1,(int)$calendar->setting('extension_days','21')))->toDateString();
                    DB::table('library_loans')->where('id',$loan->id)->update(['due_date'=>$due,'extensions'=>(int)$loan->extensions+1,'updated_at'=>now()]);
                    DB::table('library_loan_events')->insert(['loan_id'=>$loan->id,'action'=>'verlengd','actor'=>$access->username($request),'details'=>json_encode(['old_due'=>$loan->due_date,'new_due'=>$due,'request_id'=>$id]),'created_at'=>now(),'updated_at'=>now()]);
                    $result=[$loan->id,$due];
                }
            }
            DB::table('external_library_requests')->where('id',$id)->update($values);
            return $result;
        },3);
        if ($result) app(\App\Services\LibraryLoanNotifier::class)->send((int)$result[0],'extended',$result[1]);
        return back()->with('success','Aanvraag verwerkt.');
    }
}
