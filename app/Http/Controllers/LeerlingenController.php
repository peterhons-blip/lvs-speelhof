<?php

namespace App\Http\Controllers;

use App\Models\Leerling;
use App\Models\Melding;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Carbon\CarbonImmutable;

class LeerlingenController extends Controller
{
    public function index(Request $request)
    {
        $canHistory = app(\App\Services\LibraryAccess::class)->has($request,'lvs.history.view');
        $historical = $request->boolean('historisch');
        abort_if($historical && !$canHistory,403);
        $rows = DB::table('leerlingen')
            ->leftJoin('klassen', 'leerlingen.klasid', '=', 'klassen.id')
            ->where('leerlingen.schoolid',1)
            ->where('leerlingen.active', $historical ? 0 : 1)
            ->selectRaw('
                leerlingen.id,
                leerlingen.voornaam,
                COALESCE(leerlingen.naam, "") AS achternaam,
                COALESCE(klassen.klasnaam, "") AS klas
            ')
            ->orderBy('leerlingen.naam')
            ->orderBy('leerlingen.voornaam')
            ->get();

        $today = CarbonImmutable::today();
        $end   = $today->addDays(7);

        $vandaagJarig = Leerling::query()
            ->with(['klas:id,klasnaam'])
            ->where('active', 1)
            ->whereNotNull('geboortedatum')
            ->whereRaw("DATE_FORMAT(geboortedatum, '%m-%d') = ?", [$today->format('m-d')])
            ->get()
            ->map(function ($l) use ($today) {
                $dob = $l->geboortedatum instanceof \Carbon\CarbonInterface
                    ? $l->geboortedatum->toImmutable()
                    : CarbonImmutable::parse($l->geboortedatum);

                $bday = $dob->year($today->year);
                if ($bday->lt($today)) {
                    $bday = $bday->addYear();
                }

                $l->turns = $bday->year - $dob->year;
                return $l;
            })
            ->sortBy(fn($l) => [mb_strtolower($l->achternaam ?? ''), mb_strtolower($l->voornaam ?? '')])
            ->values();

        $startMd = $today->format('m-d');
        $endMd   = $end->format('m-d');

        $upcomingCount = Leerling::query()
            ->where('active', 1)
            ->whereNotNull('geboortedatum')
            ->where(function ($q) use ($startMd, $endMd) {
                if ($endMd >= $startMd) {
                    $q->whereRaw("DATE_FORMAT(geboortedatum, '%m-%d') BETWEEN ? AND ?", [$startMd, $endMd]);
                } else {
                    $q->whereRaw("DATE_FORMAT(geboortedatum, '%m-%d') >= ?", [$startMd])
                        ->orWhereRaw("DATE_FORMAT(geboortedatum, '%m-%d') <= ?", [$endMd]);
                }
            })
            ->count();

        return view('leerlingen.index', [
            'leerlingen'    => $rows,
            'canHistory'=>$canHistory,'historical'=>$historical,
            'ss_user'       => $request->session()->get('ss_user'),
            'vandaagJarig'  => $vandaagJarig,
            'upcomingCount' => $upcomingCount,
            'today'         => $today,
        ]);
    }

    public function show(Request $request, $id)
    {
        try {
            $leerling = Leerling::with('klas')
                ->where('schoolid',1)
                ->findOrFail($id);
            if (!(int)$leerling->active) abort_unless(app(\App\Services\LibraryAccess::class)->has($request,'lvs.history.view'),403);

            $canSeeMeldingen = app(\App\Services\LibraryAccess::class)
                ->has($request, 'lvs.meldingen.view');
            $meldingen = $canSeeMeldingen
                ? \App\Services\LvsIncidentVisibility::apply(Melding::with(['soort.categorie']))
                    ->where('leerlingId', $leerling->id)
                    ->orderByDesc('created_at')->get()
                : collect();

        } catch (ModelNotFoundException $e) {
            abort(404, 'Leerling niet gevonden');
        }

        $meldingGroepen = $meldingen->groupBy(fn ($melding) =>
            $melding->soort?->categorie?->naam ?: 'Overige meldingen'
        )->sortKeys(SORT_NATURAL | SORT_FLAG_CASE);
        $canCreate = (bool)$leerling->active && app(\App\Services\LibraryAccess::class)
            ->has($request, 'lvs.meldingen.create');

        $canManageVisit=(bool)$leerling->active && app(\App\Services\LibraryAccess::class)->has($request,'lvs.secretariat_visits.manage') && \Illuminate\Support\Facades\Schema::hasTable('adult_secretariat_visits');
        $adultVisit=$canManageVisit ? \Illuminate\Support\Facades\DB::table('adult_secretariat_visits')->where('student_id',$leerling->id)->where('school_id',$leerling->schoolid)->first() : null;
        $access=app(\App\Services\LibraryAccess::class);
        $schema=DB::getSchemaBuilder();
        $mentors=collect();$teachers=collect();
        // Current class assignments are not historical evidence.
        if($leerling->active && $leerling->klas && (int)$leerling->klas->schoolid===(int)$leerling->schoolid && $schema->hasTable('class_staff_links')){
            $members=DB::table('class_staff_links as l')->join('users as u','u.id','=','l.user_id')
                ->where('l.school_id',$leerling->schoolid)->where('u.schoolid',$leerling->schoolid)
                ->where('l.school_year',\App\Services\ClassStaffLinks::year())->where('l.class_id',$leerling->klasid)
                ->where(fn($q)=>$q->whereNull('u.smartschool_active')->orWhere('u.smartschool_active',1))
                ->orderBy('u.name')->orderBy('u.voornaam')->get(['l.kind','u.id','u.name','u.voornaam','u.smartschool_gebruikersnaam']);
            $mentors=$members->where('kind','mentor')->map(fn($u)=>trim($u->voornaam.' '.$u->name)?:$u->smartschool_gebruikersnaam)->unique()->values();
            if($schema->hasTable('skore_student_teacher_links')){
                $teachers=DB::table('skore_student_teacher_links as l')->join('users as u','u.id','=','l.user_id')
                    ->where('l.school_id',$leerling->schoolid)->where('u.schoolid',$leerling->schoolid)
                    ->where('l.school_year',\App\Services\ClassStaffLinks::year())->where('l.class_id',$leerling->klasid)->where('l.student_id',$leerling->id)
                    ->where(fn($q)=>$q->whereNull('u.smartschool_active')->orWhere('u.smartschool_active',1))
                    ->orderBy('u.name')->orderBy('u.voornaam')->get(['l.courses','u.name','u.voornaam','u.smartschool_gebruikersnaam']);
            }
        }
        $canSeeFollowups=$access->has($request,'lvs.followups.view');
        $followupsReady=$schema->hasTable('lvs_followup_events') && $schema->hasTable('lvs_followup_rules');
        $followups=collect();
        if($canSeeFollowups && $followupsReady){
            $followups=\App\Services\LvsIncidentVisibility::apply(DB::table('lvs_followup_events as e')->join('meldingen as m','m.id','=','e.melding_id'),'m.vertrouwelijk')
                ->leftJoin('lvs_followup_rules as r','r.id','=','e.rule_id')
                ->where('e.leerling_id',$leerling->id)->where('m.leerlingId',$leerling->id)
                ->whereIn('e.action',['review','sanction_proposal'])->orderByDesc('e.created_at')
                ->get(['e.id','e.action','e.status','e.created_at','e.resolved_at','e.resolved_by','e.resolution_note','r.title as rule_title']);
        }
        return response()->view('leerlingen.show', compact('canManageVisit','adultVisit','leerling','meldingen','meldingGroepen',
            'canCreate','canSeeMeldingen','mentors','teachers','canSeeFollowups','followupsReady','followups'))->header('Cache-Control','private, no-store');
    }

    public function verjaardagen()
    {
        CarbonImmutable::setLocale('nl');

        $today = CarbonImmutable::today();
        $end   = $today->addDays(7);

        $startMd = $today->format('m-d');
        $endMd   = $end->format('m-d');

        $leerlingen = Leerling::with(['klas:id,klasnaam'])
            ->where('active', 1)
            ->whereNotNull('geboortedatum')
            ->where(function ($q) use ($startMd, $endMd) {
                if ($endMd >= $startMd) {
                    $q->whereRaw("DATE_FORMAT(geboortedatum, '%m-%d') BETWEEN ? AND ?", [$startMd, $endMd]);
                } else {
                    $q->whereRaw("DATE_FORMAT(geboortedatum, '%m-%d') >= ?", [$startMd])
                        ->orWhereRaw("DATE_FORMAT(geboortedatum, '%m-%d') <= ?", [$endMd]);
                }
            })
            ->get()
            ->map(function ($l) use ($today) {
                $dob = $l->geboortedatum instanceof \Carbon\CarbonInterface
                    ? $l->geboortedatum->toImmutable()
                    : CarbonImmutable::parse($l->geboortedatum);

                $bday = $dob->year($today->year);
                if ($bday->lt($today)) {
                    $bday = $bday->addYear();
                }

                $l->next_birthday = $bday;
                $l->turns = $bday->year - $dob->year;
                $l->days_left = $today->diffInDays($bday);
                return $l;
            })
            ->sortBy('next_birthday')
            ->groupBy(fn($l) => $l->next_birthday->format('Y-m-d'));

        return view('leerlingen.verjaardagen', [
            'groups' => $leerlingen,
            'today'  => $today,
            'end'    => $end,
        ]);
    }
}