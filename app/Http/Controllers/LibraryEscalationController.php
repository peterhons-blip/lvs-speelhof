<?php

namespace App\Http\Controllers;

use App\Services\LibraryAccess;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\View\View;
use Illuminate\Http\RedirectResponse;

class LibraryEscalationController extends Controller
{
    public function index(Request $request, LibraryAccess $access): View
    {
        $access->requirePermission($request, 'bibliotheek.messages.manage');
        $ready = Schema::hasTable('library_loan_escalations');
        $all = $request->boolean('alle');
        $items = $ready ? DB::table('library_loan_escalations as e')
            ->join('library_loans as l', 'l.id', '=', 'e.loan_id')
            ->join('library_copies as c', 'c.id', '=', 'l.copy_id')
            ->join('library_books as b', 'b.id', '=', 'c.book_id')
            ->leftJoin('leerlingen as s', 's.id', '=', 'l.leerling_id')
            ->leftJoin('users as staff', 'staff.id', '=', 'l.staff_user_id')
            ->select('e.*', 'l.due_date', 'l.returned_at',
                'b.title', 'c.barcode', 's.voornaam', 's.naam',
                'staff.voornaam as staff_voornaam', 'staff.name as staff_naam')
            ->selectSub(DB::table('library_loan_notifications as n')->select('n.status')->whereColumn('n.loan_id', 'e.loan_id')->where('n.type', 'policy_escalation')->orderByDesc('n.id')->limit(1), 'policy_alert_status')
            ->when(!$all, fn ($q) => $q->where('e.status', 'open'))
            ->orderByDesc('e.created_at')->paginate(30)->withQueryString() : null;
        return view('bibliotheek.loans.escalations', compact('ready', 'items', 'all'));
    }

    public function resolve(Request $request, LibraryAccess $access, int $escalation): RedirectResponse
    {
        $access->requirePermission($request, 'bibliotheek.messages.manage');
        $data = $request->validate([
            'status' => ['required', 'in:resolved,dismissed'],
            'note' => ['required', 'string', 'min:5', 'max:1000'],
        ]);
        $changed = DB::table('library_loan_escalations')->where('id', $escalation)
            ->where('status', 'open')->update([
                'status' => $data['status'], 'resolution_note' => trim($data['note']),
                'resolved_by' => $access->username($request),
                'resolved_at' => now(), 'updated_at' => now(),
            ]);
        abort_unless($changed, 409, 'Deze opvolging is al afgehandeld.');
        return back()->with('success', 'Bibliotheekopvolging geregistreerd.');
    }
}
