<?php

namespace App\Http\Controllers;

use Carbon\CarbonImmutable;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class LibraryLoanController extends Controller
{
    private function setting(string $key, int $fallback): int
    {
        return max(1, (int) (DB::table('library_settings')->where('key', $key)->value('value') ?: $fallback));
    }

    private function username(Request $request): string
    {
        return (string) data_get($request->session()->get('ss_user'), 'gebruikersnaam',
            data_get($request->session()->get('ss_user'), 'id', ''));
    }

    public function index(Request $request): View
    {
        $search = trim((string) $request->query('q', ''));
        $mode = (string) $request->query('filter', 'open');
        if (! in_array($mode, ['open', 'overdue', 'returned', 'all'], true)) $mode = 'open';

        $query = DB::table('library_loans as loans')
            ->join('library_copies as copies', 'copies.id', '=', 'loans.copy_id')
            ->join('library_books as books', 'books.id', '=', 'copies.book_id')
            ->leftJoin('leerlingen as leerlingen', 'leerlingen.id', '=', 'loans.leerling_id')
            ->leftJoin('users as staff', 'staff.id', '=', 'loans.staff_user_id')
            ->select('loans.*', 'copies.barcode', 'books.title',
                'leerlingen.voornaam', 'leerlingen.naam', 'leerlingen.gebruikersnaam',
                'staff.voornaam as staff_voornaam', 'staff.name as staff_naam',
                'staff.smartschool_gebruikersnaam as staff_gebruikersnaam');

        if ($mode === 'open') $query->whereNull('loans.returned_at');
        if ($mode === 'overdue') $query->whereNull('loans.returned_at')->where('loans.due_date', '<', now()->toDateString());
        if ($mode === 'returned') $query->whereNotNull('loans.returned_at');

        if ($search !== '') {
            $query->where(function ($q) use ($search) {
                $q->where('copies.barcode', 'like', "%{$search}%")
                    ->orWhere('books.title', 'like', "%{$search}%")
                    ->orWhere('leerlingen.naam', 'like', "%{$search}%")
                    ->orWhere('leerlingen.voornaam', 'like', "%{$search}%")
                    ->orWhere('leerlingen.gebruikersnaam', 'like', "%{$search}%")
                    ->orWhere('staff.voornaam', 'like', "%{$search}%")
                    ->orWhere('staff.name', 'like', "%{$search}%")
                    ->orWhere('staff.smartschool_gebruikersnaam', 'like', "%{$search}%");
            });
        }

        $loans = $query->orderByRaw('CASE WHEN loans.returned_at IS NULL AND loans.due_date < CURDATE() THEN 0 ELSE 1 END')
            ->orderByDesc('loans.id')->paginate(30)->withQueryString();

        $totals = [
            'open' => DB::table('library_loans')->whereNull('returned_at')->count(),
            'overdue' => DB::table('library_loans')->whereNull('returned_at')->where('due_date', '<', now()->toDateString())->count(),
            'returned' => DB::table('library_loans')->whereNotNull('returned_at')->count(),
        ];

        return view('bibliotheek.loans.index', compact('loans', 'totals', 'search', 'mode'));
    }

    public function create(Request $request): View
    {
        $barcode = trim((string) $request->query('barcode', ''));
        $username = trim((string) $request->query('leerling', ''));
        $borrowerType = $request->query('ontlener_type') === 'personeel' ? 'personeel' : 'leerling';
        $staffId = $request->query('medewerker');
        $loanDays = $this->setting('loan_days', 21);
        $maxLoans = $this->setting('max_loans', 3);

        $copy = $barcode !== '' ? DB::table('library_copies as copies')
            ->join('library_books as books', 'books.id', '=', 'copies.book_id')
            ->where('copies.barcode', $barcode)
            ->select('copies.id', 'copies.barcode', 'books.title', 'copies.status')
            ->first() : null;
        $leerling = $username !== '' ? DB::table('leerlingen')
            ->where('active', 1)->where('gebruikersnaam', $username)
            ->select('id', 'voornaam', 'naam', 'gebruikersnaam')->first() : null;

        $staffMember = $borrowerType === 'personeel' && ctype_digit((string) $staffId)
            ? DB::table('users')->where('id', (int) $staffId)->where('schoolid', 1)
                ->where('allowed_login', 1)
                ->where(function ($q) { $q->whereNull('smartschool_active')->orWhere('smartschool_active', 1); })
                ->select('id', 'voornaam', 'name', 'smartschool_gebruikersnaam')->first()
            : null;
        return view('bibliotheek.loans.create', compact('barcode', 'username', 'copy', 'leerling',
            'loanDays', 'maxLoans', 'borrowerType', 'staffMember'));
    }

    public function lookupStudent(Request $request): \Illuminate\Http\JsonResponse
    {
        $this->authorizeOperator($request);
        $value = trim((string) $request->query('code', ''));
        if ($value === '' || mb_strlen($value) > 200) return response()->json(['message' => 'Ongeldige scanwaarde.'], 422);

        // Directly typed usernames work without pairing.
        $student = DB::table('leerlingen')->where('active', 1)
            ->where('gebruikersnaam', $value)
            ->select('id', 'voornaam', 'naam', 'gebruikersnaam')->first();

        if (! $student && \App\Support\LegacyDatabaseSchema::hasColumn('leerlingen', 'scannable_code')) {
            $student = DB::table('leerlingen')
                ->where('scannable_code', $value)->where('active', 1)
                ->select('id', 'voornaam', 'naam', 'gebruikersnaam')->first();
        }
        if (! $student) return response()->json(['message' => 'Geen actieve leerling gevonden voor deze scanwaarde. Controleer of de Smartschool-scancodes gesynchroniseerd zijn.'], 404);
        return response()->json(['student' => $student]);
    }

    public function staff(Request $request): \Illuminate\Http\JsonResponse
    {
        $this->authorizeOperator($request);
        $q = trim((string) $request->query('q', ''));
        if (mb_strlen($q) < 2 || mb_strlen($q) > 100) return response()->json([]);
        $term = '%'.addcslashes($q, '%_\\').'%';
        $people = DB::table('users')->where('schoolid', 1)->where('allowed_login', 1)
            ->where(function ($query) {
                $query->whereNull('smartschool_active')->orWhere('smartschool_active', 1);
            })
            ->where(function ($query) use ($term) {
                $query->where('voornaam', 'like', $term)
                    ->orWhere('name', 'like', $term)
                    ->orWhere('smartschool_gebruikersnaam', 'like', $term);
            })
            ->orderBy('name')->orderBy('voornaam')->limit(15)
            ->get(['id', 'voornaam', 'name', 'smartschool_gebruikersnaam']);
        return response()->json($people);
    }

    public function lookupCopy(Request $request): \Illuminate\Http\JsonResponse
    {
        $this->authorizeOperator($request);
        $barcode = trim((string) $request->query('barcode', ''));
        if ($barcode === '' || mb_strlen($barcode) > 60) {
            return response()->json(['message' => 'Scan een geldige boekbarcode.'], 422);
        }

        $copy = DB::table('library_copies as copies')
            ->join('library_books as books', 'books.id', '=', 'copies.book_id')
            ->where('copies.barcode', $barcode)
            ->select('copies.id', 'copies.barcode', 'copies.status', 'books.title')
            ->first();
        if (! $copy) return response()->json(['message' => 'Geen exemplaar met deze barcode gevonden.'], 404);
        if ($copy->status !== 'beschikbaar'
            || DB::table('library_loans')->where('copy_id', $copy->id)->whereNull('returned_at')->exists()) {
            return response()->json(['message' => 'Dit exemplaar is momenteel niet beschikbaar.'], 409);
        }
        return response()->json(['copy' => $copy]);
    }

    public function store(Request $request): RedirectResponse
    {
        $this->authorizeOperator($request);
        $data = $request->validate([
            'ontlener_type' => ['required', 'in:leerling,personeel'],
            'leerling' => ['required_if:ontlener_type,leerling', 'nullable', 'string', 'max:200'],
            'medewerker' => ['required_if:ontlener_type,personeel', 'nullable', 'integer', 'min:1'],
            'barcodes' => ['required', 'array', 'min:1', 'max:30'],
            'barcodes.*' => ['required', 'string', 'max:60'],
            'loan_days' => ['required', 'integer', 'min:1', 'max:365'],
        ]);

        $barcodes = array_values(array_map('trim', $data['barcodes']));
        if (count(array_unique($barcodes)) !== count($barcodes)) {
            throw ValidationException::withMessages(['barcodes' => 'Een boekbarcode komt meerdere keren voor.']);
        }
        // Stable lock order reduces contention and deadlock risk when scans are processed concurrently.
        sort($barcodes, SORT_STRING);
        $learnerUsername = trim((string) ($data['leerling'] ?? ''));
        $borrowerType = $data['ontlener_type'];
        $staffId = (int) ($data['medewerker'] ?? 0);
        $today = CarbonImmutable::today('Europe/Brussels');

        $loanIds = DB::transaction(function () use ($barcodes, $learnerUsername, $borrowerType, $staffId, $today, $data, $request) {
            $student = null;
            $staff = null;
            if ($borrowerType === 'leerling') {
                $student = DB::table('leerlingen')
                    ->where('gebruikersnaam', $learnerUsername)->where('active', 1)
                    ->lockForUpdate()->first();
                if (! $student) {
                    throw ValidationException::withMessages(['leerling' => 'Selecteer eerst een actieve leerling.']);
                }
            } else {
                $staff = DB::table('users')->where('id', $staffId)->where('schoolid', 1)
                    ->where('allowed_login', 1)
                    ->where(function ($q) { $q->whereNull('smartschool_active')->orWhere('smartschool_active', 1); })
                    ->lockForUpdate()->first();
                if (! $staff) {
                    throw ValidationException::withMessages(['medewerker' => 'Selecteer eerst een actieve medewerker met aanmeldtoegang.']);
                }
            }

            $open = DB::table('library_loans')
                ->where($student ? 'leerling_id' : 'staff_user_id', $student ? $student->id : $staff->id)
                ->whereNull('returned_at')->count();
            $maxLoans = $this->setting('max_loans', 3);
            if ($open + count($barcodes) > $maxLoans) {
                throw ValidationException::withMessages(['barcodes' =>
                    "Deze ontlener heeft al {$open} openstaande uitlening(en). Maximaal {$maxLoans} boeken tegelijk."]);
            }

            $ids = [];
            foreach ($barcodes as $barcode) {
                $copy = DB::table('library_copies')->where('barcode', $barcode)
                    ->lockForUpdate()->first();
                if (! $copy || $copy->status !== 'beschikbaar'
                    || DB::table('library_loans')->where('copy_id', $copy->id)->whereNull('returned_at')->exists()) {
                    throw ValidationException::withMessages(['barcodes' =>
                        "Exemplaar {$barcode} is niet beschikbaar. Geen van de boeken werd uitgeleend."]);
                }

                $id = DB::table('library_loans')->insertGetId([
                    'copy_id' => $copy->id,
                    'leerling_id' => $student?->id,
                    'staff_user_id' => $staff?->id,
                    'registered_by' => $this->username($request),
                    'loan_date' => $today->toDateString(),
                    'due_date' => $today->addDays((int) $data['loan_days'])->toDateString(),
                    'extensions' => 0,
                    'reminder_days_before' => null,
                    'remind_on_due_date' => null,
                    'created_at' => now(), 'updated_at' => now(),
                ]);
                DB::table('library_copies')->where('id', $copy->id)
                    ->update(['status' => 'uitgeleend', 'updated_at' => now()]);
                DB::table('library_loan_events')->insert([
                    'loan_id' => $id, 'action' => 'uitgeleend',
                    'actor' => $this->username($request), 'details' => null,
                    'created_at' => now(), 'updated_at' => now(),
                ]);
                $ids[] = $id;
            }
            return $ids;
        }, 3);

        // The SOAP operation is performed only after committing all books atomically.
        $notifier = app(\App\Services\LibraryLoanNotifier::class);
        $sent = 0;
        foreach ($loanIds as $loanId) {
            if ($notifier->send((int) $loanId, 'confirmation', $today->toDateString())) $sent++;
        }

        return redirect()->route('bibliotheek.loans.index')->with('success',
            count($loanIds).' boek(en) uitgeleend.'
            .($sent > 0 ? " Smartschool-bevestiging(en): {$sent} verzonden." : ''));
    }

    public function returnByBarcode(Request $request): RedirectResponse
    {
        $this->authorizeOperator(request());
        $data = $request->validate(['barcode' => ['required', 'string', 'max:60']]);

        $loan = DB::table('library_loans as loans')
            ->join('library_copies as copies', 'copies.id', '=', 'loans.copy_id')
            ->where('copies.barcode', trim($data['barcode']))
            ->whereNull('loans.returned_at')->select('loans.id')->first();

        if (! $loan) {
            return back()->withErrors(['barcode' => 'Geen openstaande uitlening voor deze barcode gevonden.']);
        }

        return $this->returnLoan((int) $loan->id);
    }

    public function returnLoan(int $loan): RedirectResponse
    {
        $this->authorizeOperator(request());
        return DB::transaction(function () use ($loan) {
            $item = DB::table('library_loans')->where('id', $loan)->lockForUpdate()->first();
            abort_unless($item, 404);
            if ($item->returned_at !== null) return back()->withErrors(['return' => 'Dit exemplaar is al ingeleverd.']);

            DB::table('library_loans')->where('id', $loan)
                ->update(['returned_at' => now(), 'updated_at' => now()]);
            DB::table('library_copies')->where('id', $item->copy_id)
                ->update(['status' => 'beschikbaar', 'updated_at' => now()]);
            DB::table('library_loan_events')->insert([
                'loan_id' => $loan, 'action' => 'ingeleverd',
                'actor' => $this->username(request()), 'details' => null,
                'created_at' => now(), 'updated_at' => now(),
            ]);
            return back()->with('success', 'Exemplaar succesvol ingeleverd.');
        });
    }

    public function extend(int $loan): RedirectResponse
    {
        $this->authorizeOperator(request());
        return DB::transaction(function () use ($loan) {
            $item = DB::table('library_loans')->where('id', $loan)->lockForUpdate()->first();
            abort_unless($item, 404);
            if ($item->returned_at !== null) return back()->withErrors(['extend' => 'Een ingeleverd boek kan niet verlengd worden.']);
            if ((int) $item->extensions >= $this->setting('max_extensions', 2)) {
                return back()->withErrors(['extend' => 'Maximaal aantal verlengingen bereikt.']);
            }
            $old = CarbonImmutable::parse($item->due_date, 'Europe/Brussels');
            $base = $old->isAfter(CarbonImmutable::today('Europe/Brussels')) ? $old : CarbonImmutable::today('Europe/Brussels');
            $newDue = $base->addDays($this->setting('extension_days', 21))->toDateString();
            DB::table('library_loans')->where('id', $loan)->update([
                'due_date' => $newDue,
                'extensions' => (int) $item->extensions + 1, 'updated_at' => now(),
            ]);
            DB::table('library_loan_events')->insert([
                'loan_id' => $loan, 'action' => 'verlengd',
                'actor' => $this->username(request()),
                'details' => json_encode(['old_due' => $item->due_date, 'new_due' => $newDue]),
                'created_at' => now(), 'updated_at' => now(),
            ]);
            return back()->with('success', 'Uitleentermijn verlengd.');
        });
    }

    public function editReminders(int $loan): View
    {
        $this->authorizeOperator(request());
        $item = DB::table('library_loans as loans')
            ->join('library_copies as copies', 'copies.id', '=', 'loans.copy_id')
            ->join('library_books as books', 'books.id', '=', 'copies.book_id')
            ->where('loans.id', $loan)->whereNull('loans.returned_at')
            ->select('loans.*', 'copies.barcode', 'books.title')->first();
        abort_unless($item, 404);
        return view('bibliotheek.loans.reminders', compact('item'));
    }

    public function updateReminders(Request $request, int $loan): RedirectResponse
    {
        $this->authorizeOperator(request());
        $data = $request->validate([
            'due_date' => ['required', 'date', 'after_or_equal:today'],
            'reminder_days_before' => ['nullable', 'integer', 'min:0', 'max:60'],
            'remind_on_due_date' => ['nullable', 'in:0,1'],
        ]);
        $item = DB::table('library_loans')->where('id', $loan)->whereNull('returned_at')->first();
        abort_unless($item, 404);
        DB::table('library_loans')->where('id', $loan)->whereNull('returned_at')->update([
            'due_date' => $data['due_date'],
            'reminder_days_before' => ($data['reminder_days_before'] ?? '') === ''
                ? null : (int) $data['reminder_days_before'],
            'remind_on_due_date' => ($data['remind_on_due_date'] ?? '') === ''
                ? null : (int) $data['remind_on_due_date'],
            'updated_at' => now(),
        ]);
        DB::table('library_loan_events')->insert([
            'loan_id' => $loan, 'action' => 'herinneringen_aangepast',
            'actor' => $this->username($request),
            'details' => json_encode([
                'due_date' => $data['due_date'],
                'reminder_days_before' => $data['reminder_days_before'] ?? null,
                'remind_on_due_date' => $data['remind_on_due_date'] ?? null,
            ]),
            'created_at' => now(), 'updated_at' => now(),
        ]);
        return redirect()->route('bibliotheek.loans.index')
            ->with('success', 'Herinneringsinstellingen voor deze uitlening aangepast.');
    }

    private function authorizeOperator(Request $request): void
    {
        app(\App\Services\LibraryAccess::class)
            ->requirePermission($request, 'bibliotheek.loans.manage');
    }

    private function authorizeSettings(Request $request): void
    {
        app(\App\Services\LibraryAccess::class)
            ->requirePermission($request, 'bibliotheek.settings.manage');
    }

    public function settings(): View
    {
        $this->authorizeSettings(request());
        $settings = DB::table('library_settings')->pluck('value', 'key');
        return view('bibliotheek.loans.settings', compact('settings'));
    }

    public function saveSettings(Request $request): RedirectResponse
    {
        $this->authorizeSettings($request);
        $values = $request->validate([
            'loan_days' => ['required', 'integer', 'min:1', 'max:365'],
            'max_loans' => ['required', 'integer', 'min:1', 'max:30'],
            'max_extensions' => ['required', 'integer', 'min:1', 'max:20'],
            'extension_days' => ['required', 'integer', 'min:1', 'max:365'],
            'reminder_days_before' => ['required', 'integer', 'min:0', 'max:60'],
            'remind_on_due_date' => ['required', 'in:0,1'],
            'smartschool_enabled' => ['required', 'in:0,1'],
        ]);

        // Changing outbound messages requires a stronger permission than ordinary settings.
        $oldEnabled = DB::table('library_settings')->where('key', 'smartschool_enabled')->value('value') ?? '0';
        if ($values['smartschool_enabled'] !== $oldEnabled) {
            app(\App\Services\LibraryAccess::class)
                ->requirePermission($request, 'bibliotheek.messages.manage');
        }

        foreach ($values as $key => $value) {
            DB::table('library_settings')->updateOrInsert(
                ['key' => $key],
                ['value' => (string) $value, 'updated_at' => now()]
            );
        }

        return redirect()->route('bibliotheek.loans.settings')->with('success', 'Bibliotheekinstellingen opgeslagen.');
    }

    public function students(Request $request): \Illuminate\Http\JsonResponse
    {
        $q = trim((string) $request->query('q', ''));
        if (mb_strlen($q) < 2) return response()->json([]);
        $result = DB::table('leerlingen')->where('active', 1)
            ->where(function ($query) use ($q) {
                $query->where('voornaam', 'like', "%{$q}%")
                    ->orWhere('naam', 'like', "%{$q}%")
                    ->orWhere('gebruikersnaam', 'like', "%{$q}%");
            })->select('voornaam', 'naam', 'gebruikersnaam')->limit(15)->get();
        return response()->json($result);
    }
}
