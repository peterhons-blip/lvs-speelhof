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

        $base = \App\Services\LvsIncidentVisibility::apply(DB::table('meldingen as m'),'m.vertrouwelijk')
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

        $canSeeNotifications = $access->has($request, 'lvs.meldingen.view');
        $canSeeStudents = $access->has($request, 'lvs.view');

        return view('lvs.reports', compact('data', 'start', 'end',
            'perClass', 'topStudents', 'total', 'canSeeNotifications', 'canSeeStudents'));
    }
    public function export(Request $request, LibraryAccess $access): \Symfony\Component\HttpFoundation\StreamedResponse
    {
        $access->requirePermission($request, 'lvs.reports.export');
        $data = $request->validate([
            'start' => ['nullable', 'date_format:Y-m-d'],
            'end' => ['nullable', 'date_format:Y-m-d', 'after_or_equal:start'],
            'klas' => ['nullable', 'string', 'max:60'],
        ]);
        $start = $data['start'] ?? now('Europe/Brussels')->startOfMonth()->toDateString();
        $end = $data['end'] ?? now('Europe/Brussels')->toDateString();

        $rows = \App\Services\LvsIncidentVisibility::apply(DB::table('meldingen as m'),'m.vertrouwelijk')
            ->join('leerlingen as l', 'l.id', '=', 'm.leerlingId')
            ->leftJoin('klassen as k', 'k.id', '=', 'l.klasid')
            ->join('meldingssoorten as s', 's.id', '=', 'm.meldingssoortId')
            ->join('meldingscategorien as c', 'c.id', '=', 's.meldingscategorieId')
            ->whereBetween('m.gebeurdop', [$start, $end])
            ->when(!empty($data['klas']), fn ($query) =>
                $query->where('k.klasnaam', 'like', '%'.addcslashes($data['klas'], '%_\\').'%'))
            ->select('k.klasnaam', 'c.naam as categorie')
            ->selectRaw('COUNT(*) as aantal')
            ->groupBy('k.klasnaam', 'c.naam')
            ->orderBy('k.klasnaam')->orderBy('c.naam')->get();

        $filename = 'lvs-klassenraad-'.$start.'-tot-'.$end.'.csv';

        return response()->streamDownload(static function () use ($rows, $start, $end) {
            $out = fopen('php://output', 'w');
            fwrite($out, "\xEF\xBB\xBFsep=;\r\n");
            fputcsv($out, ['Periode vanaf', 'Periode tot', 'Klas', 'Categorie', 'Aantal'], ';');
            foreach ($rows as $row) {
                // Prevent spreadsheet formula execution when opening exported school data.
                $safe = static function ($value): string {
                    $value = (string) ($value ?? '');
                    return preg_match('/^[\\s]*[=+@-]/u', $value) ? "'".$value : $value;
                };
                fputcsv($out, [$start, $end, $safe($row->klasnaam), $safe($row->categorie), $row->aantal], ';');
            }
            fclose($out);
        }, $filename, ['Content-Type' => 'text/csv; charset=UTF-8']);
    }

}
