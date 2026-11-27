<?php
namespace Tests\Feature;

use App\Services\LibraryAccess;
use App\Services\ClassStaffLinks;
use App\Services\LvsIncidentVisibility;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class LvsVisibilityTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        // Force a dedicated in-memory connection, even if .env points at production.
        config(['database.connections.lvs_test'=>['driver'=>'sqlite','database'=>':memory:','prefix'=>'','foreign_key_constraints'=>true],'database.default'=>'lvs_test']);
        DB::purge('lvs_test');
        $db=DB::connection('lvs_test');
        self::assertSame('sqlite',$db->getDriverName());
        self::assertSame(':memory:',$db->getDatabaseName());
        $schema=$db->getSchemaBuilder();
        $schema->create('leerlingen',function(Blueprint $t){$t->id();$t->integer('schoolid');$t->integer('klasid');$t->boolean('active');});
        $schema->create('meldingen',function(Blueprint $t){$t->id();$t->integer('leerlingId');$t->boolean('vertrouwelijk');$t->string('auteur_gebruikersnaam')->nullable();});
        $schema->create('class_staff_links',function(Blueprint $t){$t->integer('school_id');$t->integer('class_id');$t->integer('user_id');$t->string('school_year');$t->string('kind');});
        $schema->create('skore_student_teacher_links',function(Blueprint $t){$t->integer('school_id');$t->integer('class_id');$t->integer('user_id');$t->integer('student_id');$t->string('school_year');});
        $schema->create('lvs_visibility_matrix',function(Blueprint $t){$t->integer('school_id');$t->string('relation');$t->boolean('own');$t->boolean('ordinary');$t->boolean('confidential');});
        foreach([[1,1,10,1],[2,1,10,1],[3,1,20,1],[4,2,10,1],[5,1,10,0]] as [$id,$school,$class,$active])
            $db->table('leerlingen')->insert(['id'=>$id,'schoolid'=>$school,'klasid'=>$class,'active'=>$active]);
        foreach([[1,1,0,'other'],[2,1,1,'other'],[3,2,0,'other'],[4,3,0,'teacher'],[5,3,0,'other'],[6,4,0,'teacher'],[7,5,0,'other'],[8,3,1,'teacher'],[9,3,0,null]] as [$id,$student,$private,$author])
            $db->table('meldingen')->insert(['id'=>$id,'leerlingId'=>$student,'vertrouwelijk'=>$private,'auteur_gebruikersnaam'=>$author]);
        $db->table('skore_student_teacher_links')->insert(['school_id'=>1,'class_id'=>10,'user_id'=>10,'student_id'=>1,'school_year'=>ClassStaffLinks::year()]);
        $db->table('class_staff_links')->insert(['school_id'=>1,'class_id'=>10,'user_id'=>20,'kind'=>'mentor','school_year'=>ClassStaffLinks::year()]);
    }

    private function identity(string $role,int $id=10,bool $read=true,bool $conf=false,bool $history=false): void
    {
        $mock=\Mockery::mock(LibraryAccess::class);
        $mock->shouldReceive('role')->andReturn($role);
        $mock->shouldReceive('staffId')->andReturn($id);
        $mock->shouldReceive('username')->andReturn('teacher');
        $mock->shouldReceive('has')->withAnyArgs()->andReturnUsing(fn($request,$permission)=>match($permission){
            'lvs.meldingen.view'=>$read,'lvs.meldingen.confidential.view'=>$conf,'lvs.history.view'=>$history,default=>false
        });
        $this->app->instance(LibraryAccess::class,$mock);
    }
    private function ids(): array
    {
        return LvsIncidentVisibility::apply(DB::table('meldingen'))->orderBy('id')->pluck('id')->map(fn($id)=>(int)$id)->all();
    }

    public function test_teacher_only_sees_actual_lesson_pupils_and_own_ordinary_incidents(): void
    {
        $this->identity('leerkracht');
        self::assertSame([1,4],$this->ids());
    }
    public function test_mentor_sees_whole_class(): void
    {
        $this->identity('leerkracht',20);
        self::assertSame([1,3,4],$this->ids());
    }
    public function test_other_teacher_cannot_read_other_authors_or_confidential(): void
    {
        $this->identity('leerkracht',99,true,true);
        self::assertSame([4],$this->ids());
    }
    public function test_directie_and_counselor_require_independent_confidential_permission(): void
    {
        foreach(['directie','leerlingbegeleiding'] as $role){
            $this->identity($role,10,true,false);
            self::assertSame([1,3,4,5,9],$this->ids());
            $this->identity($role,10,true,true);
            self::assertSame([1,2,3,4,5,8,9],$this->ids());
        }
    }
    public function test_history_and_school_boundaries(): void
    {
        $this->identity('directie',10,true,true,true);
        self::assertSame([1,2,3,4,5,7,8,9],$this->ids());
    }
    public function test_read_permission_is_required_even_with_matrix_grants(): void
    {
        $this->identity('directie',10,false,true,true);
        self::assertSame([],$this->ids());
    }
    public function test_matrix_changes_apply_to_existing_rows_and_alias_queries(): void
    {
        $this->identity('leerkracht',99);
        DB::table('lvs_visibility_matrix')->insert(['school_id'=>1,'relation'=>'other','own'=>0,'ordinary'=>1,'confidential'=>0]);
        $query=LvsIncidentVisibility::apply(DB::table('meldingen as m'),'m.vertrouwelijk');
        self::assertSame([1,3,4,5,9],$query->orderBy('m.id')->pluck('m.id')->map(fn($id)=>(int)$id)->all());
        DB::table('lvs_visibility_matrix')->where('relation','other')->update(['ordinary'=>0]);
        self::assertSame([],$this->ids());
    }
    public function test_missing_matrix_fails_closed(): void
    {
        $this->identity('leerkracht');
        DB::connection()->getSchemaBuilder()->drop('lvs_visibility_matrix');
        self::assertSame([],$this->ids());
    }
    public function test_expired_lesson_relationship_does_not_grant_access(): void
    {
        $this->identity('leerkracht');
        DB::table('skore_student_teacher_links')->update(['school_year'=>'2000-2001']);
        self::assertSame([4],$this->ids());
    }
    public function test_administration_default_has_no_incident_access(): void
    {
        $this->identity('administratie',10,true,true,true);
        self::assertSame([],$this->ids());
    }
}
