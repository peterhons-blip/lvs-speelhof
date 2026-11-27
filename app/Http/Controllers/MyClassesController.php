<?php
namespace App\Http\Controllers;

use App\Services\ClassStaffLinks;
use App\Services\LibraryAccess;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class MyClassesController extends Controller
{
    private function staff(Request $request,LibraryAccess $access): int
    {
        $access->requirePermission($request,'lvs.view');
        $id=$access->staffId($request);
        abort_unless($id,403);
        return $id;
    }

    private function links(int $id)
    {
        return DB::table('class_staff_links as l')->join('klassen as k','k.id','=','l.class_id')
            ->where('l.school_id',1)->where('k.schoolid',1)->where('l.school_year',ClassStaffLinks::year())->where('l.user_id',$id)
            ->whereIn('l.kind',['mentor','teacher'])->select('l.class_id','l.kind','l.courses','k.klasnaam');
    }

    private function pupils(int $id,int $class,bool $mentor)
    {
        $q=DB::table('leerlingen as p')->where('p.schoolid',1)->where('p.active',1)->where('p.klasid',$class);
        if(!$mentor)$q->whereExists(function($sub)use($id,$class){
            $sub->selectRaw('1')->from('skore_student_teacher_links as s')->where('s.school_id',1)->where('s.school_year',ClassStaffLinks::year())->where('s.class_id',$class)->where('s.user_id',$id)->whereColumn('s.student_id','p.id');
        });
        return $q;
    }

    public function index(Request $request,LibraryAccess $access)
    {
        $id=$this->staff($request,$access);
        $classes=$this->links($id)->orderBy('k.klasnaam')->get()->groupBy('class_id')->map(function($group)use($id){
            $first=$group->first();$mentor=$group->contains('kind','mentor');
            $courses=collect($group->where('kind','teacher'))->flatMap(fn($r)=>json_decode($r->courses??'[]',true)?:[])->unique()->sort(SORT_NATURAL|SORT_FLAG_CASE)->values();
            return ['id'=>(int)$first->class_id,'name'=>$first->klasnaam,'mentor'=>$mentor,'courses'=>$courses,'count'=>$this->pupils($id,(int)$first->class_id,$mentor)->count()];
        })->values();
        return response()->view('lvs.my-classes',['classes'=>$classes,'year'=>ClassStaffLinks::year(),'staffId'=>$id])->header('Cache-Control','private, no-store');
    }

    public function roster(Request $request,LibraryAccess $access,int $class)
    {
        $id=$this->staff($request,$access);
        $links=$this->links($id)->where('l.class_id',$class)->get();
        abort_if($links->isEmpty(),404);
        $mentor=$links->contains('kind','mentor');
        $students=$this->pupils($id,$class,$mentor)->orderBy('p.naam')->orderBy('p.voornaam')->get(['p.id','p.naam','p.voornaam']);
        $courses=DB::table('skore_student_teacher_links')->where('school_id',1)->where('school_year',ClassStaffLinks::year())->where('class_id',$class)->where('user_id',$id)->pluck('courses','student_id');
        return response()->view('lvs.my-class-roster',['students'=>$students,'courses'=>$courses,'canCreate'=>$access->has($request,'lvs.meldingen.create')])->header('Cache-Control','private, no-store');
    }

    public function search(Request $request,LibraryAccess $access)
    {
        $this->staff($request,$access);
        $term=trim((string)$request->validate(['q'=>'required|string|max:100'])['q']);
        if(mb_strlen($term)<2)return response()->json([])->header('Cache-Control','private, no-store');
        $query=DB::table('leerlingen as p')->leftJoin('klassen as k',function($join){$join->on('k.id','=','p.klasid')->where('k.schoolid',1);})
            ->where('p.schoolid',1)->where('p.active',1);
        foreach(preg_split('/\s+/u',$term) as $word){
            $pattern='%'.str_replace(['!','%','_'],['!!','!%','!_'],$word).'%';
            $query->where(function($q)use($pattern){
                $q->whereRaw("p.naam LIKE ? ESCAPE '!'",[$pattern])->orWhereRaw("p.voornaam LIKE ? ESCAPE '!'",[$pattern])->orWhereRaw("k.klasnaam LIKE ? ESCAPE '!'",[$pattern]);
            });
        }
        $rows=$query->orderBy('p.naam')->orderBy('p.voornaam')->limit(20)->get(['p.id','p.naam','p.voornaam','k.klasnaam'])->map(fn($p)=>['name'=>$p->voornaam.' '.$p->naam,'class'=>$p->klasnaam,'photo'=>route('students.photo',$p->id),'url'=>route('leerlingen.show',$p->id)]);
        return response()->json($rows)->header('Cache-Control','private, no-store');
    }
}
