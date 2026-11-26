<?php
namespace App\Console\Commands;
use App\Services\{AdultSecretariatVisits,BirthdaySettings,Birthday18Actions,SchoolCalendar,SmartschoolSoap};
use Carbon\CarbonImmutable;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\{DB,Schema};
class SendAdultSecretariatReminders extends Command {
    protected $signature='leerlingen:secretariaat-herinneringen {--test : Alleen controleren, niets wijzigen of verzenden}';
    protected $description='Herinner actieve meerderjarige leerlingen aan hun openstaand secretariaatsbezoek';
    public function handle(AdultSecretariatVisits $visits,BirthdaySettings $birthday,SchoolCalendar $calendar,Birthday18Actions $actions): int {
        foreach(['adult_secretariat_visits','school_calendar_closures','birthday_18_actions'] as $table) if(!Schema::hasTable($table)){ $this->error('Migratie ontbreekt: '.$table); return self::FAILURE; }
        $settings=$birthday->all();
        if($settings['secretariat_reminder_enabled']!=='1'){ $this->info('Secretariaatsherinneringen staan uit.'); return self::SUCCESS; }
        $today=CarbonImmutable::today('Europe/Brussels');
        if($reason=$calendar->reason($today)){ $this->info('Geen secretariaatsherinneringen: '.$reason); return self::SUCCESS; }
        $date=$today->toDateString(); $planned=0; $sent=0;
        $ids=DB::table('adult_secretariat_visits')->whereNull('completed_at')->pluck('id');
        foreach($ids as $id){
            $result=DB::transaction(function() use($id,$visits,$birthday,$calendar,$actions,$settings,$today,$date){
                $v=DB::table('adult_secretariat_visits')->where('id',$id)->lockForUpdate()->first();
                if(!$v || $v->completed_at || $visits->nextDate($v,$settings,$calendar)->greaterThan($today)) return null;
                $student=DB::table('leerlingen')->where('id',$v->student_id)->where('schoolid',$v->school_id)->where('active',1)->first();
                if(!$student || !trim((string)$student->gebruikersnaam)) return null;
                $school=DB::table('scholen')->where('id',$v->school_id)->first(); if(!$school) return null;
                if($this->option('test')) return 'test';
                $sender=trim((string)$settings['adult_sender_secretariat']) ?: trim((string)$school->smartschool_verzender_secretariaat) ?: trim((string)$school->smartschool_verzender) ?: (env('SMARTSCHOOL_SENDER_USER') ?: 'lvs');
                $vars=['voornaam'=>$student->voornaam,'naam'=>$student->naam,'school'=>$school->schoolnaam];
                $ok=$actions->run((int)$v->school_id,(int)$v->student_id,$date,'adult_secretariat_reminder',$student->gebruikersnaam,function() use($birthday,$settings,$vars,$student,$sender){
                    app(SmartschoolSoap::class)->sendMessage($student->gebruikersnaam,$birthday->render($settings['secretariat_reminder_subject'],$vars),nl2br(e($birthday->render($settings['secretariat_reminder_body'],$vars))),$sender,false,0);
                });
                DB::table('adult_secretariat_visits')->where('id',$id)->update(['last_attempt_on'=>$date,'updated_at'=>now()]);
                return $ok ? 'sent' : 'failed';
            });
            if($result!==null) $planned++;
            if($result==='sent') $sent++;
        }
        $this->info($this->option('test') ? "TEST: {$planned} herinneringen gepland; niets verzonden." : "Klaar: {$sent} van {$planned} secretariaatsherinneringen verzonden.");
        return !$this->option('test') && $sent<$planned ? self::FAILURE : self::SUCCESS;
    }
}
