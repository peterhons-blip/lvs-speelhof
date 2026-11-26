<?php

namespace App\Http\Controllers;

use App\Models\Leerling;
use App\Models\Melding;
use App\Models\Meldingscategorie;
use App\Models\Meldingssoort;
use Illuminate\Http\Request;
use Illuminate\Database\Eloquent\ModelNotFoundException;

class MeldingController extends Controller
{
    public function index(Request $request, \App\Services\LibraryAccess $access)
    {
        $access->requirePermission($request, 'lvs.meldingen.view');
        $data = $request->validate([
            'zoek' => ['nullable', 'string', 'max:100'],
            'categorie' => ['nullable', 'integer', 'min:1'],
            'klas' => ['nullable', 'string', 'max:60'],
            'van' => ['nullable', 'date_format:Y-m-d'],
            'tot' => ['nullable', 'date_format:Y-m-d', 'after_or_equal:van'],
        ]);

        $query = \Illuminate\Support\Facades\DB::table('meldingen as m')
            ->join('leerlingen as l', 'l.id', '=', 'm.leerlingId')
            ->leftJoin('klassen as k', 'k.id', '=', 'l.klasid')
            ->leftJoin('meldingssoorten as s', 's.id', '=', 'm.meldingssoortId')
            ->leftJoin('meldingscategorien as c', 'c.id', '=', 's.meldingscategorieId')
            ->select('m.id', 'm.leerlingId', 'm.comment', 'm.vertrouwelijk', 'm.gebeurdop', 'm.created_at',
                'l.naam', 'l.voornaam', 'k.klasnaam', 's.naam as soort', 'c.naam as categorie');

        \App\Services\LvsIncidentVisibility::apply($query,'m.vertrouwelijk');
        if (\Illuminate\Support\Facades\DB::select("SHOW COLUMNS FROM `meldingen` LIKE 'auteur_gebruikersnaam'")) {
            $query->addSelect('m.auteur_gebruikersnaam');
        }
        if (!empty($data['categorie'])) $query->where('c.id', $data['categorie']);
        if (!empty($data['van'])) $query->whereDate('m.gebeurdop', '>=', $data['van']);
        if (!empty($data['tot'])) $query->whereDate('m.gebeurdop', '<=', $data['tot']);
        if (!empty($data['klas'])) {
            $query->where('k.klasnaam', 'like', '%'.addcslashes($data['klas'], '%_\\').'%');
        }
        if (!empty($data['zoek'])) {
            $term = '%'.addcslashes($data['zoek'], '%_\\').'%';
            $query->where(function ($q) use ($term) {
                $q->where('l.voornaam', 'like', $term)
                    ->orWhere('l.naam', 'like', $term)
                    ->orWhere('l.gebruikersnaam', 'like', $term)
                    ->orWhere('m.comment', 'like', $term);
            });
        }
        $meldingen = $query->orderByDesc('m.gebeurdop')->orderByDesc('m.id')
            ->paginate(30)->withQueryString();
        $categorieen = Meldingscategorie::orderBy('naam')->get();
        $canCreate = $access->has($request, 'lvs.meldingen.create');
        return view('meldingen.index', compact('meldingen', 'categorieen', 'canCreate', 'data'));
    }

    public function selectStudent(Request $request, \App\Services\LibraryAccess $access)
    {
        $access->requirePermission($request, 'lvs.meldingen.create');
        $leerlingen = \Illuminate\Support\Facades\DB::table('leerlingen as l')
            ->leftJoin('klassen as k', 'k.id', '=', 'l.klasid')
            ->where('l.active', 1)
            ->selectRaw('l.id, l.voornaam, COALESCE(l.naam, "") AS achternaam, COALESCE(k.klasnaam, "") AS klas')
            ->orderBy('l.naam')->orderBy('l.voornaam')->get();

        return view('meldingen.select-student', compact('leerlingen'));
    }

    public function create($id)
    {
        try {
            $leerling = Leerling::with('klas')->where('active', 1)->findOrFail($id);
        } catch (ModelNotFoundException $e) {
            abort(404, 'Leerling niet gevonden');
        }

        // Categorieën + hun soorten voor de 2 dropdowns
        $categorieen = Meldingscategorie::with(['soorten' => function ($q) {
            $q->orderBy('naam');
        }])->orderBy('naam')->get();

        // Incident history is confidential and requires the independent view right.
        $canSeeMeldingen = app(\App\Services\LibraryAccess::class)
            ->has(request(), 'lvs.meldingen.view');
        $meldingen = $canSeeMeldingen
            ? \App\Services\LvsIncidentVisibility::apply(Melding::with(['soort.categorie']))->where('leerlingId', $leerling->id)
                ->orderByDesc('created_at')->get()
            : collect();

        return view('meldingen.add', compact('leerling', 'categorieen',
            'meldingen', 'canSeeMeldingen'));
    }

    public function store(Request $request, $id)
    {
        try {
            $leerling = Leerling::where('active', 1)->findOrFail($id);
        } catch (ModelNotFoundException $e) {
            abort(404, 'Leerling niet gevonden');
        }

        $validated = $request->validate([
            'categorie_id' => ['required','exists:meldingscategorien,id'],
            'soort_id'     => ['required','exists:meldingssoorten,id'],
            'gebeurdop'    => ['required','date','before_or_equal:today'],
            'comment'      => ['required','min:5'],
            'vertrouwelijk' => ['nullable','boolean'],
        ]);

        // check: soort hoort bij gekozen categorie
        $soort = Meldingssoort::where('id', $validated['soort_id'])
            ->where('meldingscategorieId', $validated['categorie_id'])
            ->first();

        if (!$soort) {
            return back()
                ->withErrors(['soort_id' => 'De gekozen soort hoort niet bij de geselecteerde categorie.'])
                ->withInput();
        }

        $confidential = $request->boolean('vertrouwelijk');
        if ($confidential) app(\App\Services\LibraryAccess::class)->requirePermission($request,'lvs.meldingen.confidential.create');
        $attributes = [
            'vertrouwelijk'=>$confidential,
            'leerlingId' => $leerling->id,
            'meldingssoortId' => $soort->id,
            'comment' => $validated['comment'],
            'gebeurdop' => $validated['gebeurdop'],
        ];
        // Author comes only from trusted Smartschool session, never from the form.
        // Legacy DB remains operational before optional author migration is installed.
        if (\Illuminate\Support\Facades\DB::select("SHOW COLUMNS FROM `meldingen` LIKE 'auteur_gebruikersnaam'")) {
            $attributes['auteur_gebruikersnaam'] =
                app(\App\Services\LibraryAccess::class)->username($request);
        }
        $saved = Melding::create($attributes);
        app(\App\Services\LvsFollowupEngine::class)->evaluate($saved);

        $destination = app(\App\Services\LibraryAccess::class)->has($request, 'lvs.view')
            ? route('leerlingen.show', $leerling->id)
            : route('meldingen.select-student');

        return redirect($destination)->with('success', 'Melding succesvol toegevoegd.');
    }
}
