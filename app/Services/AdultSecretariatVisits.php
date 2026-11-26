<?php
namespace App\Services;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
class AdultSecretariatVisits {
    public function register(int $student,int $school,string $date): void {
        DB::table('adult_secretariat_visits')->insertOrIgnore(['student_id'=>$student,'school_id'=>$school,'invited_on'=>$date,'created_at'=>now(),'updated_at'=>now()]);
    }
    public function nextDate(object $visit,array $settings,SchoolCalendar $calendar): CarbonImmutable {
        $base=CarbonImmutable::parse($visit->last_attempt_on ?: $visit->invited_on,'Europe/Brussels');
        return $calendar->shift($base,max(1,min(60,(int)$settings['secretariat_reminder_days'])));
    }
}
