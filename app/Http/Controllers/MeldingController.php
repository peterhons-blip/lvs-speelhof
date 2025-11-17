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
    public function create($id)
    {
        try {
            $leerling = Leerling::with('klas')->findOrFail($id);
        } catch (ModelNotFoundException $e) {
            abort(404, 'Leerling niet gevonden');
        }

        // Categorieën + hun soorten voor de 2 dropdowns
        $categorieen = Meldingscategorie::with(['soorten' => function ($q) {
            $q->orderBy('naam');
        }])->orderBy('naam')->get();

        // Bestaande meldingen van de leerling (nieuw → oud), met soort + categorie
        $meldingen = Melding::with(['soort.categorie'])
            ->where('leerlingId', $leerling->id)
            ->orderByDesc('created_at')
            ->get();

        return view('meldingen.add', compact('leerling', 'categorieen', 'meldingen'));
    }

    public function store(Request $request, $id)
    {
        try {
            $leerling = Leerling::findOrFail($id);
        } catch (ModelNotFoundException $e) {
            abort(404, 'Leerling niet gevonden');
        }

        $validated = $request->validate([
            'categorie_id' => ['required','exists:meldingscategorien,id'],
            'soort_id'     => ['required','exists:meldingssoorten,id'],
            'comment'      => ['required','min:5'],
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

        Melding::create([
            'leerlingId'       => $leerling->id,
            'meldingssoortId'  => $soort->id,
            'comment'          => $validated['comment'],
        ]);

        return redirect("/leerlingen/{$leerling->id}")
            ->with('success', 'Melding succesvol toegevoegd.');
    }
}
