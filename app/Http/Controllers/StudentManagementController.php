<?php

namespace App\Http\Controllers;

use App\Services\LibraryAccess;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

class StudentManagementController extends Controller
{
    public function index(Request $request, LibraryAccess $access): View
    {
        $access->requirePermission($request, 'beheer.students.manage');
        $search = mb_substr(trim((string) $request->query('zoek', '')), 0, 100);
        $showInactive = $request->boolean('toon_inactief');
        $sortColumns = [
            'leerling' => 'leerlingen.naam',
            'klas' => 'klassen.klasnaam',
            'account' => 'leerlingen.gebruikersnaam',
            'status' => 'leerlingen.active',
        ];
        $sort = (string) $request->query('sort', 'leerling');
        if (! array_key_exists($sort, $sortColumns)) $sort = 'leerling';
        $direction = strtolower((string) $request->query('richting', 'asc')) === 'desc' ? 'desc' : 'asc';

        $students = DB::table('leerlingen')
            ->leftJoin('klassen', 'klassen.id', '=', 'leerlingen.klasid')
            ->where('leerlingen.schoolid', 1)
            ->when(! $showInactive, fn ($q) => $q->where('leerlingen.active', 1))
            ->when($search !== '', function ($query) use ($search) {
                $term = '%'.addcslashes($search, '%_\\').'%';
                $query->where(function ($q) use ($term) {
                    $q->where('leerlingen.voornaam', 'like', $term)
                        ->orWhere('leerlingen.naam', 'like', $term)
                        ->orWhere('leerlingen.gebruikersnaam', 'like', $term)
                        ->orWhere('klassen.klasnaam', 'like', $term);
                });
            })
            ->select('leerlingen.id', 'leerlingen.voornaam', 'leerlingen.naam', 'leerlingen.gebruikersnaam',
                'leerlingen.active', 'leerlingen.geboortedatum', 'klassen.klasnaam')
            ->orderBy($sortColumns[$sort], $direction)
            ->orderBy('leerlingen.naam')->orderBy('leerlingen.voornaam')->orderBy('leerlingen.id')
            ->paginate(40)->withQueryString();
        return view('beheer.students.index', compact('students', 'search', 'showInactive'));
    }

    public function bulkUpdate(Request $request, LibraryAccess $access): RedirectResponse
    {
        $access->requirePermission($request, 'beheer.students.manage');
        $data = $request->validate([
            'ids' => ['required', 'array', 'min:1', 'max:100'],
            'ids.*' => ['required', 'integer', 'min:1', 'distinct'],
            'active' => ['required', 'in:0,1'],
        ]);
        $ids = array_map('intval', $data['ids']);
        DB::transaction(function () use ($ids, $data) {
            $rows = DB::table('leerlingen')->whereIn('id', $ids)
                ->where('schoolid', 1)->lockForUpdate()->get(['id']);
            if ($rows->count() !== count($ids)) {
                throw \Illuminate\Validation\ValidationException::withMessages([
                    'ids' => 'Een of meer leerlingen werden niet gevonden binnen deze school.',
                ]);
            }
            DB::table('leerlingen')->where('schoolid', 1)->whereIn('id', $ids)
                ->update(['active' => (int) $data['active'], 'updated_at' => now()]);
        });
        return back()->with('success', count($ids).' leerlingen bijgewerkt. Let op: de volgende Smartschool-sync kan de actieve status opnieuw instellen.');
    }

    public function show(Request $request, LibraryAccess $access, int $student): View
    {
        $access->requirePermission($request, 'beheer.students.manage');
        $studentRow = DB::table('leerlingen')
            ->leftJoin('klassen', 'klassen.id', '=', 'leerlingen.klasid')
            ->where('leerlingen.id', $student)
            ->where('leerlingen.schoolid', 1)
            ->select('leerlingen.*', 'klassen.klasnaam')
            ->first();
        abort_unless($studentRow, 404);

        return view('beheer.students.show', [
            'student' => $studentRow,
            'hasScanCode' => \App\Support\LegacyDatabaseSchema::hasColumn('leerlingen', 'scannable_code'),
        ]);
    }

    public function update(Request $request, LibraryAccess $access, int $student): RedirectResponse
    {
        $access->requirePermission($request, 'beheer.students.manage');
        $data = $request->validate([
            'voornaam' => ['required', 'string', 'max:120'],
            'naam' => ['required', 'string', 'max:120'],
            'geboortedatum' => ['nullable', 'date_format:Y-m-d', 'before_or_equal:today'],
            'email' => ['nullable', 'email', 'max:255'],
        ]);
        $exists = DB::table('leerlingen')->where('id', $student)->where('schoolid', 1)->exists();
        abort_unless($exists, 404);

        DB::table('leerlingen')->where('id', $student)->where('schoolid', 1)->update([
            'voornaam' => trim($data['voornaam']),
            'naam' => trim($data['naam']),
            'geboortedatum' => $data['geboortedatum'] ?? null,
            'e-mailadres' => $data['email'] ?? null,
            'updated_at' => now(),
        ]);
        return redirect()->route('beheer.students.show', $student)
            ->with('success', 'Leerlinggegevens opgeslagen. Smartschool-gegevens kunnen bij een volgende synchronisatie opnieuw worden bijgewerkt.');
    }
}
