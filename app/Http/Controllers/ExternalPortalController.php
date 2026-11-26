<?php
namespace App\Http\Controllers;

use App\Services\ExternalPortalAccess;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class ExternalPortalController extends Controller
{
    public function index(Request $request,ExternalPortalAccess $access)
    {
        $student=$request->attributes->get('external_student');
        abort_unless($student,403);
        $role=$request->session()->get('ss_user.principal_type');
        $can=fn(string $permission)=>$access->has($role,$permission,(int)$student->id);
        $profile=$can('profile.view') ? DB::table('klassen')->where('id',$student->klasid)->value('klasnaam') : null;
        $books=null; $loans=null; $history=null;
        if ($can('library.catalog.view')) {
            $q=mb_substr(trim((string)$request->query('q','')),0,100);
            $query=DB::table('library_books')->select('id','title','authors','isbn');
            if ($q!=='') $query->where(fn($x)=>$x->where('title','like','%'.$q.'%')->orWhere('authors','like','%'.$q.'%')->orWhere('isbn','like','%'.$q.'%'));
            if ($can('library.availability.view')) $query->selectSub(DB::table('library_copies')->whereColumn('book_id','library_books.id')->where('status','beschikbaar')->selectRaw('count(*)'),'available');
            $books=$query->orderBy('title')->paginate(20)->withQueryString();
        }
        $base=fn()=>DB::table('library_loans as l')->join('library_copies as c','c.id','=','l.copy_id')->join('library_books as b','b.id','=','c.book_id')->where('l.leerling_id',$student->id)->whereNull('l.staff_user_id')->select('b.title','l.due_date','l.returned_at');
        if ($can('library.view')) $loans=$base()->whereNull('l.returned_at')->orderBy('l.due_date')->get();
        if ($can('library.history.view')) $history=$base()->whereNotNull('l.returned_at')->orderByDesc('l.returned_at')->paginate(20,['*'],'historiek')->withQueryString();
        return view('external.dashboard',compact('student','role','can','profile','books','loans','history'));
    }
}
