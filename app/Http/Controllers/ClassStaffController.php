<?php
namespace App\Http\Controllers;

use App\Services\ClassStaffLinks;
use App\Services\LibraryAccess;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;

class ClassStaffController extends Controller
{
    private function authorizeManager(Request $request,LibraryAccess $access): void
    {
        $access->requirePermission($request,'beheer.classes.manage');
        abort_unless($access->staffId($request),403);
    }

    public function index(Request $request,LibraryAccess $access,ClassStaffLinks $links)
    {
        $this->authorizeManager($request,$access);
        $classes=$links->classes();
        $year=ClassStaffLinks::year();
        $members=DB::table('class_staff_links as l')->join('users as u','u.id','=','l.user_id')
            ->where('l.school_id',1)->where('l.school_year',$year)->where('u.schoolid',1)
            ->select('l.class_id','l.kind','u.smartschool_gebruikersnaam')->orderBy('u.smartschool_gebruikersnaam')->get()->groupBy('class_id');
        $counts=DB::table('leerlingen')->where('schoolid',1)->where('active',1)->selectRaw('klasid,count(*) as total')->groupBy('klasid')->pluck('total','klasid');
        return view('beheer.classes.index',compact('classes','year','members','counts'));
    }

    public function students(Request $request,LibraryAccess $access,int $class)
    {
        $this->authorizeManager($request,$access);
        abort_unless(DB::table('klassen')->where('schoolid',1)->where('id',$class)->exists(),404);
        $students=DB::table('leerlingen')->where('schoolid',1)->where('klasid',$class)->where('active',1)
            ->orderBy('naam')->orderBy('voornaam')->orderBy('id')->get(['id','naam','voornaam']);
        return response()->view('beheer.classes.students',['students'=>$students,'canOpen'=>$access->has($request,'beheer.students.manage'),'canLvs'=>$access->has($request,'lvs.view')])->header('Cache-Control','private, no-store');
    }

    public function preview(Request $request,LibraryAccess $access,ClassStaffLinks $links)
    {
        $this->authorizeManager($request,$access);
        $request->validate(['kind'=>'required|in:teacher,mentor_smartschool']);
        $kind=$request->input('kind');
        try {
            if($kind==='mentor_smartschool'){
                $preview=$links->smartschoolMentorPreview();
            }else{
                $preview=$links->teacherPreview();
            }
        }catch(\Throwable $e){
            report($e);
            return back()->withErrors(['preview'=>'Smartschool-preview mislukt. Er zijn geen wijzigingen uitgevoerd.']);
        }
        $token=Str::random(48);
        Cache::put('class-staff-preview:'.$token,['actor'=>$access->staffId($request),'year'=>ClassStaffLinks::year(),'kind'=>$kind,'preview'=>$preview],now()->addMinutes(15));
        return view('beheer.classes.preview',compact('preview','token','kind'));
    }

    public function apply(Request $request,LibraryAccess $access,ClassStaffLinks $links)
    {
        $this->authorizeManager($request,$access);
        $request->validate(['token'=>'required|string|size:48']);
        $key='class-staff-preview:'.$request->input('token');
        $lock=Cache::lock('class-staff-apply:1',1800);
        if(!$lock->get())return back()->withErrors(['preview'=>'Er loopt al een import.']);
        try{
            $record=Cache::get($key);
            abort_unless($record && $record['actor']===$access->staffId($request) && $record['year']===ClassStaffLinks::year(),403);
            try{$count=$links->apply($record['preview'],$record['kind'],$record['kind']==='teacher');}
            catch(\RuntimeException $e){return redirect()->route('beheer.classes.index')->withErrors(['preview'=>$e->getMessage()]);}
            Cache::forget($key);
            \Illuminate\Support\Facades\Log::info('Class staff links imported',['actor'=>$access->username($request),'kind'=>$record['kind'],'count'=>$count]);
            return redirect()->route('beheer.classes.index')->with('success',$count.' koppelingen verwerkt.');
        }finally{$lock->release();}
    }
}
