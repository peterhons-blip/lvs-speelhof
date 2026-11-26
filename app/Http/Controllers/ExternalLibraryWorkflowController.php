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
        $data=$request->validate(['type'=>'required|in:reserve,renew,helper','target_id'=>'nullable|integer|min:1','action'=>'nullable|in:books.create,isbn.lookup,labels.print,loans.create,loans.return','details'=>'nullable|string|max:500']);
        $can=fn($p)=>$access->has($role,$p,(int)$student->id);
        $target=$data['target_id']??null;
        $payload=null;
        if ($data['type']==='reserve') {
            abort_unless($can('library.reserve') && $can('library.catalog.view'),403);
            abort_unless($target && DB::table('library_books')->where('id',$target)->exists(),404);
        } elseif ($data['type']==='renew') {
            abort_unless($can('library.renew.request') && $can('library.view'),403);
            abort_unless($target && DB::table('library_loans')->where('id',$target)->where('leerling_id',$student->id)->whereNull('staff_user_id')->whereNull('returned_at')->exists(),404);
        } else {
            $action=$data['action']??'';
            abort_unless($role==='leerling' && $can('library.help') && $can('library.help.'.$action),403);
            $request->validate(['details'=>'required|string|max:500']);
            $target=null;
            $payload=json_encode(['action'=>$action,'details'=>$data['details']]);
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
        $requests=DB::table('external_library_requests as r')->join('leerlingen as l','l.id','=','r.leerling_id')->where('l.schoolid',1)->select('r.*','l.voornaam','l.naam')->orderByRaw("CASE WHEN r.status='pending' THEN 0 ELSE 1 END")->orderByDesc('r.id')->paginate(30);
        return view('bibliotheek.external-requests',compact('requests'));
    }

    public function decide(Request $request,LibraryAccess $access,int $id)
    {
        $access->requirePermission($request,'bibliotheek.view');
        $access->requirePermission($request,'bibliotheek.requests.manage');
        $data=$request->validate(['status'=>'required|in:approved,rejected','note'=>'nullable|string|max:500']);
        $updated=DB::table('external_library_requests')->where('id',$id)->where('status','pending')->whereIn('leerling_id',DB::table('leerlingen')->where('schoolid',1)->select('id'))->update(['status'=>$data['status'],'decision_note'=>$data['note']??null,'decided_by'=>$access->username($request),'decided_at'=>now(),'updated_at'=>now()]);
        abort_unless($updated,409);
        return back()->with('success','Aanvraag beoordeeld. Voer eventuele fysieke reservering of verlenging uit in het gewone bibliotheekbeheer.');
    }
}
