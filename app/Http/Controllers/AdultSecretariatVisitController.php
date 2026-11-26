<?php
namespace App\Http\Controllers;
use App\Services\{AdultSecretariatVisits,BirthdaySettings,SchoolCalendar,LibraryAccess};
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Carbon\CarbonImmutable;
class AdultSecretariatVisitController extends Controller {
    public function index(Request $r,AdultSecretariatVisits $service,BirthdaySettings $settings,SchoolCalendar $calendar){
        $mode=$r->query('status','open'); $q=trim((string)$r->query('q',''));
        $query=DB::table('adult_secretariat_visits as v')->join('leerlingen as l','l.id','=','v.student_id')->leftJoin('klassen as k','k.id','=','l.klasid')->whereColumn('l.schoolid','v.school_id')->where('l.active',1);
        if($mode==='done') $query->whereNotNull('v.completed_at'); elseif($mode!=='all') $query->whereNull('v.completed_at');
        if($q!=='') $query->where(fn($x)=>$x->where('l.naam','like',"%$q%")->orWhere('l.voornaam','like',"%$q%")->orWhere('k.klasnaam','like',"%$q%"));
        $rows=$query->select('v.*','l.voornaam','l.naam','k.klasnaam')->orderBy('l.naam')->orderBy('l.voornaam')->paginate(40)->withQueryString();
        $config=$settings->all();
        foreach($rows as $row) $row->next_date=$service->nextDate($row,$config,$calendar)->format('d/m/Y');
        return view('beheer.secretariat-visits',compact('rows','mode','q','config'));
    }
    public function update(Request $r,int $visit,LibraryAccess $access){
        $data=$r->validate(['completed'=>'required|in:0,1']);
        DB::transaction(function()use($r,$visit,$access,$data){
            $v=DB::table('adult_secretariat_visits')->where('id',$visit)->lockForUpdate()->first(); abort_unless($v,404);
            abort_unless(DB::table('leerlingen')->where('id',$v->student_id)->where('schoolid',$v->school_id)->where('active',1)->exists(),404);
            $done=$data['completed']==='1'; if($done===($v->completed_at!==null)) return;
            $actor=$access->username($r);
            $values=['completed_at'=>$done?now():null,'completed_by'=>$done?$actor:null,'updated_at'=>now()];
            if(!$done) $values['last_attempt_on']=now('Europe/Brussels')->toDateString();
            DB::table('adult_secretariat_visits')->where('id',$visit)->update($values);
            DB::table('adult_secretariat_visit_events')->insert(['visit_id'=>$visit,'action'=>$done?'completed':'reopened','actor'=>$actor,'created_at'=>now(),'updated_at'=>now()]);
        });
        return back()->with('success','Secretariaatsopvolging bijgewerkt.');
    }
    public function register(Request $r,int $student,AdultSecretariatVisits $service){
        $l=DB::table('leerlingen')->where('id',$student)->where('active',1)->first(); abort_unless($l && $l->geboortedatum && CarbonImmutable::parse($l->geboortedatum)->addYears(18)->lessThanOrEqualTo(CarbonImmutable::today('Europe/Brussels')),422);
        $service->register($student,(int)$l->schoolid,now('Europe/Brussels')->toDateString());
        return back()->with('success','Secretariaatsopvolging gestart. Eerste herinnering na het ingestelde aantal schooldagen.');
    }
}
