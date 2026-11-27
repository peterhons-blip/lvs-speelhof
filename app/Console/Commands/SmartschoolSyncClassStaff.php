<?php
namespace App\Console\Commands;

use App\Services\ClassStaffLinks;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Schema;

class SmartschoolSyncClassStaff extends Command
{
    protected $signature='smartschool:sync-klaskoppelingen {--apply : Schrijf gecontroleerde koppelingen naar de databank}';
    protected $description='Synchroniseer leerkrachten en klastitularissen uit Smartschool';

    public function handle(ClassStaffLinks $links): int
    {
        if(!Schema::hasTable('class_staff_links')){
            $this->error('Migratie class_staff_links ontbreekt.');
            return self::FAILURE;
        }
        $lock=Cache::lock('class-staff-apply:1',1800);
        if(!$lock->get()){
            $this->error('Klaskoppeling wordt al uitgevoerd.');
            return self::FAILURE;
        }
        $failed=false;
        try{
            foreach(['teacher'=>'Leerkrachten','mentor_smartschool'=>'Klastitularissen'] as $kind=>$label){
                try{
                    $preview=$kind==='teacher'?$links->teacherPreview():$links->smartschoolMentorPreview();
                    foreach($preview['notes']??[] as $note)$this->warn($note);
                    foreach($preview['errors'] as $error)$this->error($error);
                    if($preview['errors'])$failed=true;
                    if(!$preview['rows']){$failed=true;$this->error($label.': geen betrouwbare koppelingen.');continue;}
                    // Only teachers have independently validated group snapshots.
                    // Mentor records can be incomplete within a class; keep the whole snapshot on errors.
                    if($kind==='mentor_smartschool' && $preview['errors'])continue;
                    $this->info($label.': '.count($preview['rows']).' koppelingen, '.count($preview['covered']).' klassen.');
                    if($this->option('apply')){
                        $count=$links->apply($preview,$kind,$kind==='teacher');
                        $this->info($label.': '.$count.' koppelingen opgeslagen.');
                    }
                }catch(\Throwable $e){
                    $failed=true;
                    $this->error($label.': technische fout; bestaande gegevens behouden.');
                    \Illuminate\Support\Facades\Log::warning('Klaskoppeling mislukt',['kind'=>$kind,'exception'=>get_class($e)]);
                }
            }
        }finally{$lock->release();}
        return $failed?self::FAILURE:self::SUCCESS;
    }
}
