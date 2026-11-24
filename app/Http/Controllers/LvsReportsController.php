<?php

namespace App\Http\Controllers;

use App\Services\LibraryAccess;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

class LvsReportsController extends Controller
{
    public function index(Request $request, LibraryAccess $access): View
    {
        $access->requirePermission($request, 'lvs.reports.view');
        $data = $request->validate([
            'start' => ['nullable', 'date_format:Y-m-d'],
            'end' => ['nullable', 'date_format:Y-m-d', 'after_or_equal:start'],
            'klas' => ['nullable', 'string', 'max:60'],
        ]);
        $start = $data['start'] ?? now('Europe/Brussels')->startOfMonth()->toDateString();
        $end = $data['end'] ?? now('Europe/Brussels')->toDateString();

        $base = DB::table('meldingen as m')
            ->join('leerlingen as l', 'l.id', '=', 'm.leerlingId')
            ->leftJoin('klassen as k', 'k.id', '=', 'l.klasid')
            ->join('meldingssoorten as s', 's.id', '=', 'm.meldingssoortId')
            ->join('meldingscategorien as c', 'c.id', '=', 's.meldingscategorieId')
            ->whereBetween('m.gebeurdop', [$start, $end]);
        if (!empty($data['klas'])) {
            $base->where('k.klasnaam', 'like', '%'.addcslashes($data['klas'], '%_\\').'%');
        }

        $perClass = (clone $base)->select('k.klasnaam', 'c.naam as categorie')
            ->selectRaw('COUNT(*) as aantal')
            ->groupBy('k.klasnaam', 'c.naam')
            ->orderBy('k.klasnaam')->orderBy('c.naam')->get();

        $topStudents = (clone $base)->select('l.id', 'l.voornaam', 'l.naam', 'k.klasnaam')
            ->selectRaw('COUNT(*) as aantal')
            ->groupBy('l.id', 'l.voornaam', 'l.naam', 'k.klasnaam')
            ->orderByDesc('aantal')->orderBy('l.naam')->limit(50)->get();

        $total = $perClass->sum('aantal');

        return view('lvs.reports', compact('data', 'start', 'end', 'perClass', 'topStudents', 'total'));
    }
}
