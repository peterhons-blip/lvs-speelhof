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
        $access->requireManager($request);
        $search = mb_substr(trim((string) $request->query('zoek', '')), 0, 100);
        $showInactive = $request->boolean('toon_inactief');

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
            ->orderBy('leerlingen.naam')->orderBy('leerlingen.voornaam')
            ->paginate(40)->withQueryString();
        return view('beheer.students.index', compact('students', 'search', 'showInactive'));
    }

    public function show(Request $request, LibraryAccess $access, int $student): View
    {
        $access->requireManager($request);
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
        $access->requireManager($request);
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
