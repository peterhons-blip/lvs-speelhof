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

        $canSeeStudents = $access->has($request, 'lvs.view');
        $canSeeNotifications = $access->has($request, 'lvs.meldingen.view');
        $today = now('Europe/Brussels');
        $from = $today->copy()->startOfMonth()->toDateString();
        $until = $today->toDateString();

        $stats = ['students' => null, 'notifications' => null, 'this_month' => null, 'last_7_days' => null];
        $categories = collect();
        $recent = collect();
        if ($canSeeStudents) {
            $stats['students'] = DB::table('leerlingen')->where('schoolid', 1)->where('active', 1)->count();
        }
        if ($canSeeNotifications) {
            $visible = \App\Services\LvsIncidentVisibility::apply(DB::table('meldingen'));
            $counts = (clone $visible)->selectRaw('COUNT(*) AS total')
                ->selectRaw('COUNT(CASE WHEN gebeurdop BETWEEN ? AND ? THEN 1 END) AS this_month', [$from, $until])
                ->selectRaw('COUNT(CASE WHEN gebeurdop BETWEEN ? AND ? THEN 1 END) AS last_7_days', [$today->copy()->subDays(6)->toDateString(), $until])
                ->first();
            $stats['notifications'] = (int) $counts->total;
            $stats['this_month'] = (int) $counts->this_month;
            $stats['last_7_days'] = (int) $counts->last_7_days;

            $categories = DB::table('meldingscategorien as c')
                ->leftJoin('meldingssoorten as s', 's.meldingscategorieId', '=', 'c.id')
                ->leftJoinSub((clone $visible)->select('id','meldingssoortId'),'m',fn($join)=>$join->on('m.meldingssoortId','=','s.id'))
                ->select('c.id', 'c.naam')
                ->selectRaw('COUNT(m.id) AS totaal')
                ->groupBy('c.id', 'c.naam')
                ->orderByDesc('totaal')->orderBy('c.naam')->get();

            $recent = \App\Services\LvsIncidentVisibility::apply(DB::table('meldingen as m'),'m.vertrouwelijk')
                ->join('leerlingen as l', 'l.id', '=', 'm.leerlingId')
                ->where('l.active', 1)
                ->where('l.schoolid', 1)
                ->leftJoin('klassen as k', 'k.id', '=', 'l.klasid')
                ->leftJoin('meldingssoorten as s', 's.id', '=', 'm.meldingssoortId')
                ->leftJoin('meldingscategorien as c', 'c.id', '=', 's.meldingscategorieId')
                ->select('m.id', 'm.leerlingId', 'm.gebeurdop', 'm.created_at',
                    'l.voornaam', 'l.naam', 'k.klasnaam', 's.naam as soort', 'c.naam as categorie')
                ->orderByDesc('m.created_at')->limit(8)->get();

        }
        $canSeeFollowups = $access->has($request, 'lvs.followups.view');
        $canSeeReports = $access->has($request, 'lvs.reports.view');
        $canCreate = $access->has($request, 'lvs.meldingen.create');

        return view('lvs.dashboard', compact('stats', 'categories', 'recent',
            'canSeeReports', 'canCreate', 'canSeeStudents', 'canSeeNotifications', 'canSeeFollowups'));
    }
}
