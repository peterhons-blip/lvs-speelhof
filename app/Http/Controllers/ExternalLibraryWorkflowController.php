<?php
namespace App\Http\Controllers;

use App\Services\ExternalPortalAccess;
use App\Services\LibraryAccess;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class ExternalLibraryWorkflowController extends Controller
{
    public function submit(Request $request,ExternalPortalAccess $access)
    {
        $student=$request->attributes->get('external_student');
        abort_unless($student && !$request->session()->has('portal_preview'),403);
        $role=$request->session()->get('ss_user.principal_type');
        $data=$request->validate(['type'=>'required|in:reserve,renew','target_id'=>'nullable|integer|min:1','action'=>'nullable|in:books.create,isbn.lookup,labels.print,loans.create,loans.return','details'=>'nullable|string|max:500']);
        $can=fn($p)=>$access->has($role,$p,(int)$student->id);
        $target=$data['target_id']??null;
        $payload=null;
        if ($data['type']==='reserve') {
            abort_unless($can('library.reserve') && $can('library.catalog.view'),403);
            abort_unless($target && DB::table('library_books')->where('id',$target)->exists(),404);
        } elseif ($data['type']==='renew') {
            abort_unless($can('library.renew.request') && $can('library.view'),403);
            abort_unless($target && DB::table('library_loans')->where('id',$target)->where('leerling_id',$student->id)->whereNull('staff_user_id')->whereNull('returned_at')->exists(),404);
        }
        DB::transaction(function()use($student,$role,$data,$target,$payload){
            DB::table('leerlingen')->where('id',$student->id)->lockForUpdate()->first();
            $pending=DB::table('external_library_requests')->where('leerling_id',$student->id)->where('status','pending');
            if ((clone $pending)->count()>=20) abort(422,'Te veel openstaande aanvragen.');
            if ($data['type']!=='helper' && (clone $pending)->where('request_type',$data['type'])->where('target_id',$target)->exists()) abort(422,'Er bestaat al een openstaande aanvraag.');
            DB::table('external_library_requests')->insert(['leerling_id'=>$student->id,'request_type'=>$data['type'],'target_id'=>$target,'principal_type'=>$role,'payload'=>$payload,'status'=>'pending','created_at'=>now(),'updated_at'=>now()]);
        });
        return back()->with('success','Aanvraag doorgestuurd naar de bibliotheek.');
    }

    public function index(Request $request,LibraryAccess $access)
    {
        $access->requirePermission($request,'bibliotheek.view');
        $access->requirePermission($request,'bibliotheek.requests.manage');
        $requests=DB::table('external_library_requests as r')->join('leerlingen as l','l.id','=','r.leerling_id')->where('l.schoolid',1)->whereIn('r.request_type',['reserve','renew'])->select('r.*','l.voornaam','l.naam')->orderByRaw("CASE WHEN r.status='pending' THEN 0 ELSE 1 END")->orderByDesc('r.id')->paginate(30);
        return view('bibliotheek.external-requests',compact('requests'));
    }

    public function decide(Request $request,LibraryAccess $access,int $id)
    {
        $access->requirePermission($request,'bibliotheek.view');
        $access->requirePermission($request,'bibliotheek.requests.manage');
        $data=$request->validate(['status'=>'required|in:approved,rejected','note'=>'nullable|string|max:500']);
        $result=DB::transaction(function()use($id,$data,$access,$request){
            $row=DB::table('external_library_requests')->where('id',$id)->where('status','pending')->whereIn('request_type',['reserve','renew'])->lockForUpdate()->first();
            abort_unless($row,409);
            abort_unless(DB::table('leerlingen')->where('id',$row->leerling_id)->where('schoolid',1)->where('active',1)->exists(),422,'Leerling is niet actief.');
            $values=['status'=>$data['status'],'decision_note'=>$data['note']??null,'decided_by'=>$access->username($request),'decided_at'=>now(),'updated_at'=>now()];
            $result=null;
            if ($data['status']==='approved') {
                $holds=app(\App\Services\LibraryReservations::class);
                if ($row->request_type==='reserve') {
                    $copies=DB::table('library_copies')->where('book_id',$row->target_id)->where('status','beschikbaar')->orderBy('id')->lockForUpdate()->get();
                    $copy=$copies->first(fn($c)=>!(clone $holds->active())->where('reserved_copy_id',$c->id)->lockForUpdate()->first() && !DB::table('library_loans')->where('copy_id',$c->id)->whereNull('returned_at')->exists());
                    if (!$copy) throw \Illuminate\Validation\ValidationException::withMessages(['reserve'=>'Geen vrij exemplaar beschikbaar. De aanvraag blijft open.']);
                    $values['reserved_copy_id']=$copy->id;
                    $values['reserved_until']=app(\App\Services\SchoolCalendar::class)->shift(\Carbon\CarbonImmutable::today('Europe/Brussels'),5)->toDateString();
                } else {
                    $access->requirePermission($request,'bibliotheek.loans.extend');
                    $loan=DB::table('library_loans')->where('id',$row->target_id)->where('leerling_id',$row->leerling_id)->whereNull('staff_user_id')->whereNull('returned_at')->lockForUpdate()->first();
                    abort_unless($loan,422,'Geen openstaande uitlening.');
                    $calendar=app(\App\Services\LibraryCalendar::class);
                    if ((int)$loan->extensions>=max(1,(int)$calendar->setting('max_extensions','2'))) throw \Illuminate\Validation\ValidationException::withMessages(['renew'=>'Maximaal aantal verlengingen bereikt.']);
                    $book=DB::table('library_copies')->where('id',$loan->copy_id)->value('book_id');
                    if (DB::table('external_library_requests')->where('request_type','reserve')->where('target_id',$book)->where('leerling_id','<>',$row->leerling_id)->where(fn($q)=>$q->where('status','pending')->orWhere(fn($x)=>$x->where('status','approved')->where('reserved_until','>=',now('Europe/Brussels')->toDateString())))->exists()) throw \Illuminate\Validation\ValidationException::withMessages(['renew'=>'Een andere leerling wacht op dit boek.']);
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
