<?php

namespace App\Http\Controllers;

use App\Services\LibraryAccess;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\View\View;

class LvsFollowupsController extends Controller
{
    public function index(Request $request, LibraryAccess $access): View
    {
        $access->requirePermission($request, 'lvs.followups.view');
        $ready = Schema::hasTable('lvs_followup_events');
        $showAll = $request->boolean('alles');
        $events = $ready ? DB::table('lvs_followup_events as e')
            ->join('meldingen as m','m.id','=','e.melding_id')
            ->whereIn('m.id',\App\Services\LvsIncidentVisibility::apply(DB::table('meldingen'))->select('meldingen.id'))
            ->leftJoin('lvs_followup_rules as r', 'r.id', '=', 'e.rule_id')
            ->leftJoin('leerlingen as l', 'l.id', '=', 'e.leerling_id')
            ->leftJoin('klassen as k', 'k.id', '=', 'l.klasid')
            ->select('e.*', 'r.title as rule_title', 'l.voornaam', 'l.naam',
                'k.klasnaam')
            ->whereIn('e.action', ['review', 'sanction_proposal'])
            ->when(!$showAll, fn($q) => $q->where('e.status', 'pending'))
            ->orderByDesc('e.created_at')->paginate(40)->withQueryString() : null;

        $canSeeStudents = $access->has($request, 'lvs.view');
        return view('lvs.followups', compact('ready', 'showAll', 'events', 'canSeeStudents'));
    }

    public function resolve(Request $request, LibraryAccess $access, int $event): RedirectResponse
    {
        $access->requirePermission($request, 'lvs.followups.resolve');
        abort_unless(Schema::hasTable('lvs_followup_events'), 503);
        $data = $request->validate([
            'decision' => ['required', 'in:resolved,dismissed'],
            'note' => ['required', 'string', 'min:5', 'max:1000'],
        ]);
        $changed = DB::table('lvs_followup_events')
            ->where('id', $event)->where('status', 'pending')
            ->whereIn('melding_id',\App\Services\LvsIncidentVisibility::apply(DB::table('meldingen'))->select('meldingen.id'))
            ->whereIn('action', ['review', 'sanction_proposal'])
            ->update([
                'status' => $data['decision'],
                'resolution_note' => trim($data['note']),
                'resolved_by' => $access->username($request),
                'resolved_at' => now(),
                'updated_at' => now(),
            ]);
        abort_unless($changed, 409, 'Deze opvolging werd al behandeld of bestaat niet.');
        return back()->with('success', 'Opvolging geregistreerd. Er is geen automatische sanctie uitgevoerd.');
    }
}
