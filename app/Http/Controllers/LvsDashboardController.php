<?php

namespace App\Http\Controllers;

use App\Services\LibraryAccess;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

class LvsDashboardController extends Controller
{
    public function index(Request $request, LibraryAccess $access): View
    {
        $access->requirePermission($request, 'lvs.dashboard.view');

        $today = now('Europe/Brussels');
        $from = $today->copy()->startOfMonth()->toDateString();
        $until = $today->toDateString();

        $stats = [
            'students' => DB::table('leerlingen')->where('active', 1)->count(),
            'notifications' => DB::table('meldingen')->count(),
            'this_month' => DB::table('meldingen')->whereBetween('gebeurdop', [$from, $until])->count(),
            'last_7_days' => DB::table('meldingen')->whereBetween('gebeurdop', [
                $today->copy()->subDays(6)->toDateString(), $until,
            ])->count(),
        ];

        $categories = DB::table('meldingscategorien as c')
            ->leftJoin('meldingssoorten as s', 's.meldingscategorieId', '=', 'c.id')
            ->leftJoin('meldingen as m', 'm.meldingssoortId', '=', 's.id')
            ->select('c.id', 'c.naam')
            ->selectRaw('COUNT(m.id) AS totaal')
            ->groupBy('c.id', 'c.naam')
            ->orderByDesc('totaal')->orderBy('c.naam')->get();

        $recent = DB::table('meldingen as m')
            ->join('leerlingen as l', 'l.id', '=', 'm.leerlingId')
            ->leftJoin('klassen as k', 'k.id', '=', 'l.klasid')
            ->leftJoin('meldingssoorten as s', 's.id', '=', 'm.meldingssoortId')
            ->leftJoin('meldingscategorien as c', 'c.id', '=', 's.meldingscategorieId')
            ->select('m.id', 'm.leerlingId', 'm.gebeurdop', 'm.created_at',
                'l.voornaam', 'l.naam', 'k.klasnaam', 's.naam as soort', 'c.naam as categorie')
            ->orderByDesc('m.created_at')->limit(8)->get();

        $canSeeReports = $access->has($request, 'lvs.reports.view');
        $canCreate = $access->has($request, 'lvs.meldingen.create');

        return view('lvs.dashboard', compact('stats', 'categories', 'recent',
            'canSeeReports', 'canCreate'));
    }
}
