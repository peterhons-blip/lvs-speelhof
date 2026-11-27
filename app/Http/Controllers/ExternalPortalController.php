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
        $titles=['dashboard'=>'Welkom, '.($request->session()->get('ss_user.voornaam')??'').'!','library'=>'Bibliotheek','profile'=>'Leerlinggegevens','loans'=>'Actuele uitleningen','history'=>'Uitleenhistoriek','catalog'=>'Boekencatalogus','book'=>'Boekdetails','requests'=>'Mijn aanvragen','reservations'=>'Mijn reserveringen','renewals'=>'Mijn verlengingsaanvragen','incidents'=>'LVS-meldingen'];
        $libraryAllowed=$can('library.view')||$can('library.history.view')||$can('library.catalog.view')||$can('library.reserve')||$can('library.renew.request');
        $allowed=['dashboard'=>true,'library'=>$staff||$libraryAllowed,'profile'=>$can('profile.view'),'loans'=>$can('library.view'),'history'=>$can('library.history.view'),'catalog'=>$can('library.catalog.view'),'book'=>$can('library.catalog.view'),'requests'=>$can('library.reserve')||$can('library.renew.request'),'reservations'=>$can('library.reserve'),'renewals'=>$can('library.renew.request'),'incidents'=>$can('lvs.view')&&$can('incidents.view')];
        abort_unless($allowed[$section]??false,403);
        if ($section==='requests') return redirect()->route($portalRoute,['section'=>$can('library.reserve')?'reservations':'renewals']);
        $profile=$can('profile.view') ? DB::table('klassen')->where('id',$student->klasid)->value('klasnaam') : null;
        $catalogFilters=[]; $book=null; $books=null; $loans=null; $history=null; $incidents=null;
        if ($section==='incidents' && $can('lvs.view') && $can('incidents.view')) $incidents=DB::table('meldingen as m')->leftJoin('meldingssoorten as s','s.id','=','m.meldingssoortId')->where('m.leerlingId',$student->id)->where(function($q){
            $q->where('m.vertrouwelijk',0);
            if(\App\Support\LegacyDatabaseSchema::hasColumn('meldingen','confidential_release'))$q->orWhere(fn($v)=>$v->where('m.vertrouwelijk',1)->where('m.confidential_release',1));
        })->where($role==='leerling'?'m.visible_student':'m.visible_coaccount',1)->select('m.gebeurdop','m.comment','s.naam as soort')->orderByDesc('m.gebeurdop')->paginate(20,['*'],'meldingen')->withQueryString();
        $requests=($can('library.reserve') || $can('library.renew.request')) ? DB::table('external_library_requests')->where($staff?'staff_user_id':'leerling_id',$student->id)->where('principal_type',$role)->select('external_library_requests.*')->selectSub(DB::table('library_books')->whereColumn('library_books.id','external_library_requests.target_id')->select('title'),'requested_book')->selectSub(DB::table('library_loans as rqloan')->join('library_copies as rqcopy','rqcopy.id','=','rqloan.copy_id')->join('library_books as rqbook','rqbook.id','=','rqcopy.book_id')->whereColumn('rqloan.id','external_library_requests.target_id')->select('rqbook.title'),'renew_book')->whereIn('request_type',array_values(array_filter(['reserve','renew'],fn($t)=>$t==='reserve'?$can('library.reserve'):$can('library.renew.request'))))->when($section==='reservations',fn($q)=>$q->where('request_type','reserve'))->when($section==='renewals',fn($q)=>$q->where('request_type','renew'))->orderByDesc('id')->get() : collect();
        if ($section==='catalog' && $can('library.catalog.view')) {
            $q=mb_substr(trim((string)$request->query('q','')),0,100);
            $query=DB::table('library_books')->select('id','title','authors','isbn','category','published_year','publisher');
            if ($q!=='') $query->where(fn($x)=>$x->where('title','like','%'.$q.'%')->orWhere('authors','like','%'.$q.'%')->orWhere('isbn','like','%'.$q.'%')->orWhere('category','like','%'.$q.'%'));
            foreach (['category','authors','publisher','published_year'] as $field) {
                $value=mb_substr(trim((string)$request->query($field,'')),0,255);
                $catalogFilters[$field]=DB::table('library_books')->whereNotNull($field)->where($field,'<>','')->distinct()->orderBy($field)->pluck($field);
                if ($value!=='') $query->where($field,$value);
            }
            if ($can('library.availability.view')) {
                $available=fn()=>app(\App\Services\LibraryReservations::class)->available(DB::table('library_copies')->whereColumn('book_id','library_books.id')->where('status','beschikbaar'));
                $query->selectSub($available()->selectRaw('count(*)'),'available');
                if ($request->query('availability')==='available') $query->whereExists($available()->selectRaw('1'));
                if ($request->query('availability')==='unavailable') $query->whereNotExists($available()->selectRaw('1'));
            }
            $sort=(string)$request->query('sort','title');
            $sorts=['title'=>['title','asc'],'author'=>['authors','asc'],'year'=>['published_year','desc'],'recent'=>['id','desc']];
            [$column,$direction]=$sorts[$sort]??$sorts['title'];
            $books=$query->orderBy($column,$direction)->orderBy('id')->paginate(20)->withQueryString();
            if ($request->ajax()) return response()->view('external.partials.catalog-results',compact('books','can','portalRoute','submitRoute'))->header('Cache-Control','private, no-store');
        }
        if ($section==='book') {
            $bookQuery=DB::table('library_books')->select('id','title','subtitle','authors','isbn','publisher','published_year','category','description','cover_url');
            if ($can('library.availability.view')) $bookQuery->selectSub(app(\App\Services\LibraryReservations::class)->available(DB::table('library_copies')->whereColumn('book_id','library_books.id')->where('status','beschikbaar'))->selectRaw('count(*)'),'available');
            $book=$bookQuery->where('id',(int)$request->query('book'))->first();
            abort_unless($book,404);
            $titles['book']=$book->title;
        }
        $base=fn()=>DB::table('library_loans as l')->join('library_copies as c','c.id','=','l.copy_id')->join('library_books as b','b.id','=','c.book_id')->where($staff?'l.staff_user_id':'l.leerling_id',$student->id)->whereNull($staff?'l.leerling_id':'l.staff_user_id')->select('l.id','b.title','l.due_date','l.returned_at');
        if ($can('library.view')) $loans=$base()->whereNull('l.returned_at')->orderBy('l.due_date')->get();
        if ($section==='history' && $can('library.history.view')) $history=$base()->whereNotNull('l.returned_at')->orderByDesc('l.returned_at')->paginate(20,['*'],'historiek')->withQueryString();
        return view('external.dashboard',compact('student','role','can','profile','books','loans','history','incidents','requests','section','titles','portalRoute','submitRoute','book','catalogFilters'));
    }
}
