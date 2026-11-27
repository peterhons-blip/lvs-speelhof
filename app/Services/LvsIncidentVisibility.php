<?php
namespace App\Services;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class LvsIncidentVisibility
{
    public const LABELS=['directie'=>'Directie','leerlingbegeleiding'=>'Leerlingenbegeleiding','mentor'=>'Klastitularis van de leerling','teacher'=>'Vakleerkracht van de leerling','other'=>'Andere leerkracht','administratie'=>'Administratie','secretariaat'=>'Secretariaat','boekhouding'=>'Boekhouding','tac'=>'TAC'];
    public const FIELDS=['own','ordinary','confidential'];

    public static function matrix(): array
    {
        $rows=[];
        foreach(self::LABELS as $key=>$label){
            $rows[$key]=['own'=>in_array($key,['directie','leerlingbegeleiding','mentor','teacher','other'],true),'ordinary'=>in_array($key,['directie','leerlingbegeleiding','mentor','teacher'],true),'confidential'=>in_array($key,['directie','leerlingbegeleiding'],true)];
        }
        if(Schema::hasTable('lvs_visibility_matrix')){
            foreach(DB::table('lvs_visibility_matrix')->where('school_id',1)->get() as $row){
                if(isset($rows[$row->relation]))foreach(self::FIELDS as $field)$rows[$row->relation][$field]=(bool)$row->$field;
            }
        }
        return $rows;
    }

    public static function apply($query,string $column='vertrouwelijk')
    {
        $access=app(LibraryAccess::class);$request=request();
        $role=$access->role($request);$staff=$access->staffId($request);
        $prefix=str_contains($column,'.')?substr($column,0,strrpos($column,'.')+1):'meldingen.';
        $student=$prefix.'leerlingId';$author=$prefix.'auteur_gebruikersnaam';
        if(!$staff||!$access->has($request,'lvs.meldingen.view'))return $query->whereRaw('1=0');
        $canHistory=$access->has($request,'lvs.history.view');
        $query->whereExists(function($q)use($student,$canHistory){
            $q->selectRaw('1')->from('leerlingen as vis_p')->whereColumn('vis_p.id',$student)->where('vis_p.schoolid',1);
            if(!$canHistory)$q->where('vis_p.active',1);
        });
        $canConf=$access->has($request,'lvs.meldingen.confidential.view');
        if($role==='ict_coordinator')return $query;
        if(!Schema::hasTable('lvs_visibility_matrix'))return $query->whereRaw('1=0');
        $matrix=self::matrix();
        $hasAuthor=\App\Support\LegacyDatabaseSchema::hasColumn('meldingen','auteur_gebruikersnaam');
        $username=$access->username($request);
        $hasMentor=Schema::hasTable('class_staff_links');
        $hasTeacher=Schema::hasTable('skore_student_teacher_links');
        $year=ClassStaffLinks::year();
        $relation=function($q,string $kind,bool $negate=false)use($student,$staff,$year,$hasMentor,$hasTeacher){
            if(($kind==='mentor'&&!$hasMentor)||($kind==='teacher'&&!$hasTeacher)){
                if(!$negate)$q->whereRaw('1=0');
                return;
            }
            $method=$negate?'whereNotExists':'whereExists';
            $q->$method(function($sub)use($kind,$student,$staff,$year){
                $sub->selectRaw('1')->from('leerlingen as rel_p')
                    ->whereColumn('rel_p.id',$student)->where('rel_p.schoolid',1)->where('rel_p.active',1);
                if($kind==='mentor'){
                    $sub->join('class_staff_links as rel_l','rel_l.class_id','=','rel_p.klasid')->where('rel_l.kind','mentor');
                }else{
                    $sub->join('skore_student_teacher_links as rel_l','rel_l.student_id','=','rel_p.id')->whereColumn('rel_l.class_id','rel_p.klasid');
                }
                $sub->where('rel_l.school_id',1)->where('rel_l.school_year',$year)->where('rel_l.user_id',$staff);
            });
        };
        $allow=function($q,array $rights)use($column,$author,$username,$hasAuthor,$canConf){
            $q->where(function($v)use($rights,$column,$author,$username,$hasAuthor,$canConf){
                $v->whereRaw('1=0');
                if($rights['ordinary'])$v->orWhere($column,0);
                if($rights['own']&&$hasAuthor&&$username!=='')$v->orWhere(fn($own)=>$own->where($column,0)->where($author,$username));
                if($rights['confidential']&&$canConf)$v->orWhere($column,1);
            });
        };
        if($role!=='leerkracht'){
            $allow($query,$matrix[$role]??['own'=>false,'ordinary'=>false,'confidential'=>false]);
            return $query;
        }
        return $query->where(function($q)use($relation,$allow,$matrix){
            $q->where(function($v)use($relation,$allow,$matrix){$relation($v,'mentor');$allow($v,$matrix['mentor']);})
              ->orWhere(function($v)use($relation,$allow,$matrix){$relation($v,'mentor',true);$relation($v,'teacher');$allow($v,$matrix['teacher']);})
              ->orWhere(function($v)use($relation,$allow,$matrix){$relation($v,'mentor',true);$relation($v,'teacher',true);$allow($v,$matrix['other']);});
        });
    }
}
