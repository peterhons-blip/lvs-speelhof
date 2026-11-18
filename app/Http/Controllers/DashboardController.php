<?php

namespace App\Http\Controllers;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\View\View;

class DashboardController extends Controller
{
    public function index(): View
    {
        // De root-route toont ook de publieke login. Geen tellingen zonder Smartschool-sessie.
        if (! session('ss_user')) {
            return view('home');
        }

        $lvs = ['leerlingen' => null, 'meldingen' => null, 'meldingen_maand' => null, 'jarig_vandaag' => null];

        if (Schema::hasTable('leerlingen')) {
            $lvs['leerlingen'] = DB::table('leerlingen')->where('active', 1)->count();
            $lvs['jarig_vandaag'] = DB::table('leerlingen')
                ->where('active', 1)->whereNotNull('geboortedatum')
                ->whereRaw("DATE_FORMAT(geboortedatum, '%m-%d') = ?", [now()->format('m-d')])
                ->count();
        }

        if (Schema::hasTable('meldingen')) {
            $lvs['meldingen'] = DB::table('meldingen')->count();
            $lvs['meldingen_maand'] = DB::table('meldingen')
                ->whereBetween('gebeurdop', [
                    now()->startOfMonth()->toDateString(),
                    now()->endOfMonth()->toDateString(),
                ])->count();
        }

        $bibliotheek = [
            'boeken' => null, 'exemplaren' => null, 'beschikbaar' => null,
            'niet_beschikbaar' => null, 'beschadigd' => null,
            'imports_wachten' => null, 'uitleningen' => null, 'te_laat' => null,
        ];

        if (Schema::hasTable('library_books')) {
            $bibliotheek['boeken'] = DB::table('library_books')->count();
        }

        if (Schema::hasTable('library_copies')) {
            $counts = DB::table('library_copies')
                ->selectRaw("COUNT(*) AS totaal")
                ->selectRaw("SUM(CASE WHEN status = 'beschikbaar' THEN 1 ELSE 0 END) AS beschikbaar")
                ->selectRaw("SUM(CASE WHEN status <> 'beschikbaar' THEN 1 ELSE 0 END) AS niet_beschikbaar")
                ->selectRaw("SUM(CASE WHEN `condition` = 'beschadigd' THEN 1 ELSE 0 END) AS beschadigd")
                ->first();

            $bibliotheek['exemplaren'] = (int) $counts->totaal;
            $bibliotheek['beschikbaar'] = (int) $counts->beschikbaar;
            $bibliotheek['niet_beschikbaar'] = (int) $counts->niet_beschikbaar;
            $bibliotheek['beschadigd'] = (int) $counts->beschadigd;
        }

        if (Schema::hasTable('library_import_items')) {
            $bibliotheek['imports_wachten'] = DB::table('library_import_items')
                ->where('status', 'wachten')->count();
        }

        // De uitleenmodule bestaat nog niet; laat de uitleencijfers leeg
        // in plaats van kolominspectie te gebruiken (legacy MariaDB ondersteunt
        // 'generation_expression' niet in information_schema.columns).

        return view('home', compact('lvs', 'bibliotheek'));
    }
}
