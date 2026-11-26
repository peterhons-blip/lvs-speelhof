<?php
namespace App\Http\Controllers;

use App\Services\ExternalPortalAccess;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class ExternalPortalController extends Controller
{
    public function staff(Request $request,\App\Services\LibraryAccess $access)
    {
        $access->requirePermission($request,'persoonlijke_bibliotheek.view');
        $id=$access->staffId($request);
        abort_unless($id,403);
        return $this->render($request,'staff',(object)['id'=>$id],fn($p)=>$access->personalLibraryCan($request,$p),'personal-library.index','personal-library.submit');
    }

    public function index(Request $request,ExternalPortalAccess $access)
    {
        $student=$request->attributes->get('external_student');
        abort_unless($student,403);
        $role=$request->session()->get('ss_user.principal_type');
        $can=fn(string $permission)=>$access->has($role,$permission,(int)$student->id);
        return $this->render($request,$role,$student,$can,'external.dashboard','external.requests.submit');
    }

    private function render(Request $request,string $role,object $student,\Closure $can,string $portalRoute,string $submitRoute)
    {
        $staff=$role==='staff';
        $section=(string)$request->query('section',$staff?'library':'dashboard');
        $titles=['dashboard'=>'Welkom, '.($request->session()->get('ss_user.voornaam')??'').'!','library'=>'Bibliotheek','profile'=>'Leerlinggegevens','loans'=>'Actuele uitleningen','history'=>'Uitleenhistoriek','catalog'=>'Boekencatalogus','requests'=>'Mijn aanvragen','incidents'=>'LVS-meldingen'];
        $libraryAllowed=$can('library.view')||$can('library.history.view')||$can('library.catalog.view')||$can('library.reserve')||$can('library.renew.request');
        $allowed=['dashboard'=>true,'library'=>$staff||$libraryAllowed,'profile'=>$can('profile.view'),'loans'=>$can('library.view'),'history'=>$can('library.history.view'),'catalog'=>$can('library.catalog.view'),'requests'=>$can('library.reserve')||$can('library.renew.request'),'incidents'=>$can('lvs.view')&&$can('incidents.view')];
        abort_unless($allowed[$section]??false,403);
        $profile=$can('profile.view') ? DB::table('klassen')->where('id',$student->klasid)->value('klasnaam') : null;
        $books=null; $loans=null; $history=null; $incidents=null;
        if ($section==='incidents' && $can('lvs.view') && $can('incidents.view')) $incidents=DB::table('meldingen as m')->leftJoin('meldingssoorten as s','s.id','=','m.meldingssoortId')->where('m.leerlingId',$student->id)->where('m.vertrouwelijk',0)->where($role==='leerling'?'m.visible_student':'m.visible_coaccount',1)->select('m.gebeurdop','m.comment','s.naam as soort')->orderByDesc('m.gebeurdop')->paginate(20,['*'],'meldingen')->withQueryString();
        $requests=($can('library.reserve') || $can('library.renew.request')) ? DB::table('external_library_requests')->where($staff?'staff_user_id':'leerling_id',$student->id)->where('principal_type',$role)->whereIn('request_type',array_values(array_filter(['reserve','renew'],fn($t)=>$t==='reserve'?$can('library.reserve'):$can('library.renew.request'))))->orderByDesc('id')->limit(50)->get() : collect();
        if ($section==='catalog' && $can('library.catalog.view')) {
            $q=mb_substr(trim((string)$request->query('q','')),0,100);
            $query=DB::table('library_books')->select('id','title','authors','isbn');
            if ($q!=='') $query->where(fn($x)=>$x->where('title','like','%'.$q.'%')->orWhere('authors','like','%'.$q.'%')->orWhere('isbn','like','%'.$q.'%'));
            if ($can('library.availability.view')) $query->selectSub(app(\App\Services\LibraryReservations::class)->available(DB::table('library_copies')->whereColumn('book_id','library_books.id')->where('status','beschikbaar'))->selectRaw('count(*)'),'available');
            $books=$query->orderBy('title')->paginate(20)->withQueryString();
        }
        $base=fn()=>DB::table('library_loans as l')->join('library_copies as c','c.id','=','l.copy_id')->join('library_books as b','b.id','=','c.book_id')->where($staff?'l.staff_user_id':'l.leerling_id',$student->id)->whereNull($staff?'l.leerling_id':'l.staff_user_id')->select('l.id','b.title','l.due_date','l.returned_at');
        if ($can('library.view')) $loans=$base()->whereNull('l.returned_at')->orderBy('l.due_date')->get();
        if ($section==='history' && $can('library.history.view')) $history=$base()->whereNotNull('l.returned_at')->orderByDesc('l.returned_at')->paginate(20,['*'],'historiek')->withQueryString();
        return view('external.dashboard',compact('student','role','can','profile','books','loans','history','incidents','requests','section','titles','portalRoute','submitRoute'));
    }
}
