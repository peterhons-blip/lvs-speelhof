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
            $counts = DB::table('leerlingen')->where('active', 1)
                ->selectRaw('COUNT(*) AS total')
                ->selectRaw("COUNT(CASE WHEN DATE_FORMAT(geboortedatum, '%m-%d') = ? THEN 1 END) AS birthdays", [now()->format('m-d')])
                ->first();
            $lvs['leerlingen'] = (int) $counts->total;
            $lvs['jarig_vandaag'] = (int) $counts->birthdays;
        }

        if ($canSeeLvs && $access->has(request(),'lvs.meldingen.view') && Schema::hasTable('meldingen')) {
            $counts = \App\Services\LvsIncidentVisibility::apply(DB::table('meldingen'))
                ->selectRaw('COUNT(*) AS total')
                ->selectRaw('COUNT(CASE WHEN gebeurdop BETWEEN ? AND ? THEN 1 END) AS this_month', [
                    now()->startOfMonth()->toDateString(), now()->endOfMonth()->toDateString(),
                ])->first();
            $lvs['meldingen'] = (int) $counts->total;
            $lvs['meldingen_maand'] = (int) $counts->this_month;
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
            $counts = DB::table('library_loans')->whereNull('returned_at')
                ->selectRaw('COUNT(*) AS total')
                ->selectRaw('COUNT(CASE WHEN due_date < ? THEN 1 END) AS overdue', [now('Europe/Brussels')->toDateString()])
                ->first();
            $bibliotheek['uitleningen'] = (int) $counts->total;
            $bibliotheek['te_laat'] = (int) $counts->overdue;
        }

        return view('home', compact('lvs', 'bibliotheek'));
    }
}
