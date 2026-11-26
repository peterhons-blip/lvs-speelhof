<?php
namespace App\Services;

use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;

class SchoolCalendar
{
    private ?array $closures = null;
    public static function official(int $year): array
    {
        if ($year < 2000 || $year > 2098) throw new \InvalidArgumentException('Ongeldig schooljaar.');
        $date = fn ($y,$m,$d) => CarbonImmutable::createMidnightDate($y,$m,$d,'Europe/Brussels');
        $nov = $date($year,11,1);
        $christmas = $date($year,12,25);
        $autumn = $nov->isSunday() ? $nov->addDay() : $nov->startOfWeek();
        $winter = $christmas->isWeekend() ? $christmas->next(1) : $christmas->startOfWeek();
        $easter = $date($year+1,3,21)->addDays(easter_days($year+1));
        $spring = $easter->subWeeks(7)->next(1);
        $april = $date($year+1,4,1);
        $firstMonday = $april->isMonday() ? $april : $april->next(1);
        $pasen = $easter->month === 3 ? $easter->addDay() : ($easter->day > 15 ? $easter->subWeeks(2)->next(1) : $firstMonday);
        $ascension = $easter->addDays(39);
        return [
            ['Herfstvakantie',$autumn,$autumn->addDays(6)],
            ['Wapenstilstand',$nov->addDays(10),$nov->addDays(10)],
            ['Kerstvakantie',$winter,$winter->addDays(13)],
            ['Krokusvakantie',$spring,$spring->addDays(6)],
            ['Paasvakantie',$pasen,$pasen->addDays(13)],
            ['Paasmaandag',$easter->addDay(),$easter->addDay()],
            ['Dag van de Arbeid',$date($year+1,5,1),$date($year+1,5,1)],
            ['Hemelvaart en brugdag',$ascension,$ascension->addDay()],
            ['Pinkstermaandag',$easter->addDays(50),$easter->addDays(50)],
            ['Zomervakantie',$date($year+1,7,1),$date($year+1,8,31)],
        ];
    }
    public static function import(int $year): void
    {
        foreach (self::official($year) as [$name,$start,$end]) {
            DB::table('school_calendar_closures')->insertOrIgnore([
                'school_year'=>$year,'name'=>$name,'starts_on'=>$start->toDateString(),
                'ends_on'=>$end->toDateString(),'source'=>'official','created_at'=>now(),'updated_at'=>now(),
            ]);
        }
    }
    public function reason(CarbonImmutable $date, bool $weekends = true, bool $holidays = true): ?string
    {
        if ($weekends && $date->isWeekend()) return 'Weekend';
        if (!$holidays) return null;
        $this->closures ??= DB::table('school_calendar_closures')->get()->all();
        $day=$date->toDateString();
        foreach ($this->closures as $row) {
            if ($row->starts_on <= $day && $row->ends_on >= $day) return $row->name;
        }
        return null;
    }
    public function nextOpen(CarbonImmutable $date): CarbonImmutable
    {
        for ($i=0; $i<370; $i++, $date=$date->addDay()) {
            if (!$this->reason($date)) return $date;
        }
        throw new \RuntimeException('Geen schooldag gevonden binnen 370 dagen.');
    }
    public function shift(CarbonImmutable $date, int $days, bool $weekends=true, bool $holidays=true): CarbonImmutable
    {
        $direction=$days<0 ? -1 : 1;
        for ($i=0; $i<abs($days);) {
            $date=$date->addDays($direction);
            if (!$this->reason($date,$weekends,$holidays)) $i++;
        }
        return $date;
    }
    public function distance(CarbonImmutable $from, CarbonImmutable $to, bool $weekends=true, bool $holidays=true): int
    {
        if ($from->greaterThan($to)) return -$this->distance($to,$from,$weekends,$holidays);
        $n=0;
        for ($d=$from->addDay(); $d->lessThanOrEqualTo($to); $d=$d->addDay()) {
            if (!$this->reason($d,$weekends,$holidays)) $n++;
        }
        return $n;
    }
}
