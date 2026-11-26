<?php
namespace App\Services;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
class LibraryCalendar
{
    private ?array $settings = null;
    public function __construct(private SchoolCalendar $calendar) {}
    public function setting(string $key, string $default): string
    {
        $this->settings ??= DB::table('library_settings')->pluck('value','key')->all();
        return (string) ($this->settings[$key] ?? $default);
    }
    public function due(CarbonImmutable $base, int $days): CarbonImmutable
    {
        $date = $this->setting('loan_day_unit','calendar') === 'school' ? $this->calendar->shift($base,$days) : $base->addDays($days);
        return $this->adjust($date);
    }
    public function adjust(CarbonImmutable $date): CarbonImmutable
    {
        return $this->setting('shift_due_to_schoolday','1') === '1' ? $this->calendar->nextOpen($date) : $date;
    }
    public function closed(CarbonImmutable $date): ?string
    {
        return $this->calendar->reason($date,$this->setting('skip_weekends','1') === '1',$this->setting('skip_holidays','1') === '1');
    }
    public function distance(CarbonImmutable $from, CarbonImmutable $to): int
    {
        return $this->setting('reminder_day_unit','school') === 'school'
            ? $this->calendar->distance($from,$to)
            : (int)$from->diffInDays($to,false);
    }
    public function reminderDate(CarbonImmutable $due, int $days): CarbonImmutable
    {
        $date = $this->setting('reminder_day_unit','school') === 'school' ? $this->calendar->shift($due,-$days) : $due->subDays($days);
        while ($this->closed($date)) $date=$date->subDay();
        return $date;
    }
}
