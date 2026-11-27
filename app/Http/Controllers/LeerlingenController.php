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
        return view('leerlingen.show', compact('canManageVisit','adultVisit','leerling', 'meldingen', 'meldingGroepen',
            'canCreate', 'canSeeMeldingen'));
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