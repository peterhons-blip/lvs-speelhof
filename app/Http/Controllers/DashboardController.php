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

        $access = app(\App\Services\LibraryAccess::class);
        $canSeeLvs = $access->has(request(), 'lvs.dashboard.view');
        $canSeeLibrary = $access->has(request(), 'bibliotheek.view');
        $canSeeBooks = $canSeeLibrary && $access->has(request(), 'bibliotheek.books.view');
        $canSeeLoans = $canSeeLibrary && $access->has(request(), 'bibliotheek.loans.view');
        if ($canSeeLvs && $access->has(request(),'lvs.view') && Schema::hasTable('leerlingen')) {
            $lvs['leerlingen'] = DB::table('leerlingen')->where('active', 1)->count();
            $lvs['jarig_vandaag'] = DB::table('leerlingen')
                ->where('active', 1)->whereNotNull('geboortedatum')
                ->whereRaw("DATE_FORMAT(geboortedatum, '%m-%d') = ?", [now()->format('m-d')])
                ->count();
        }

        if ($canSeeLvs && $access->has(request(),'lvs.meldingen.view') && Schema::hasTable('meldingen')) {
            $lvs['meldingen'] = \App\Services\LvsIncidentVisibility::apply(DB::table('meldingen'))->count();
            $lvs['meldingen_maand'] = \App\Services\LvsIncidentVisibility::apply(DB::table('meldingen'))
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

        if ($canSeeBooks && Schema::hasTable('library_books')) {
            $bibliotheek['boeken'] = DB::table('library_books')->count();
        }

        if ($canSeeBooks && Schema::hasTable('library_copies')) {
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

        if ($canSeeLibrary && $access->has(request(),'bibliotheek.import.history') && Schema::hasTable('library_import_items')) {
            $bibliotheek['imports_wachten'] = DB::table('library_import_items')
                ->where('status', 'wachten')->count();
        }

        // Uitleenstatistieken uit de nieuwe exemplaargebonden uitleentabel.
        // Geen Schema::hasColumn() op de oudere MariaDB-productieserver.
        if ($canSeeLoans && Schema::hasTable('library_loans')) {
            $bibliotheek['uitleningen'] = DB::table('library_loans')->whereNull('returned_at')->count();
            $bibliotheek['te_laat'] = DB::table('library_loans')
                ->whereNull('returned_at')->where('due_date', '<', now('Europe/Brussels')->toDateString())
                ->count();
        }

        return view('home', compact('lvs', 'bibliotheek'));
    }
}
