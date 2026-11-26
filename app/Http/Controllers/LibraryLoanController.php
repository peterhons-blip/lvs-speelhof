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
        if (! in_array($mode, ['open', 'overdue', 'returned', 'all', 'departed'], true)) $mode = 'open';

        $query = DB::table('library_loans as loans')
            ->join('library_copies as copies', 'copies.id', '=', 'loans.copy_id')
            ->join('library_books as books', 'books.id', '=', 'copies.book_id')
            ->leftJoin('leerlingen as leerlingen', 'leerlingen.id', '=', 'loans.leerling_id')
            ->leftJoin('users as staff', 'staff.id', '=', 'loans.staff_user_id')
            ->select('loans.*', 'copies.barcode', 'books.title',
                'leerlingen.voornaam', 'leerlingen.naam', 'leerlingen.gebruikersnaam',
                'staff.voornaam as staff_voornaam', 'staff.name as staff_naam',
                'staff.smartschool_gebruikersnaam as staff_gebruikersnaam');

        if ($mode === 'departed') $query->whereNull('loans.returned_at')->whereNotNull('loans.leerling_id')->where(fn($q)=>$q->whereNull('leerlingen.id')->orWhere('leerlingen.active',0));
        if ($request->filled('student_id')) $query->where('loans.leerling_id',(int)$request->query('student_id'));
        if ($request->filled('staff_id')) $query->where('loans.staff_user_id',(int)$request->query('staff_id'));
        if ($request->filled('copy_id')) $query->where('loans.copy_id',(int)$request->query('copy_id'));
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
                
                ->where(function ($q) { $q->whereNull('smartschool_active')->orWhere('smartschool_active', 1); })
                ->select('id', 'voornaam', 'name', 'smartschool_gebruikersnaam')->first()
            : null;
        return view('bibliotheek.loans.create', compact('barcode', 'username', 'copy', 'leerling',
            'loanDays', 'maxLoans', 'borrowerType', 'staffMember'));
    }

    public function duePreview(Request $request): \Illuminate\Http\JsonResponse
    {
        $data=$request->validate(['days'=>'required|integer|min:1|max:365']);
        $date=app(\App\Services\LibraryCalendar::class)->due(CarbonImmutable::today('Europe/Brussels'),(int)$data['days']);
        return response()->json(['date'=>$date->toDateString()]);
    }

    public function lookupStudent(Request $request): \Illuminate\Http\JsonResponse
    {
        app(\App\Services\LibraryAccess::class)->requirePermission($request, 'bibliotheek.loans.create');
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
        app(\App\Services\LibraryAccess::class)->requirePermission($request, 'bibliotheek.loans.create');
        $q = trim((string) $request->query('q', ''));
        if (mb_strlen($q) < 2 || mb_strlen($q) > 100) return response()->json([]);

        // Legacy installations do not necessarily expose every optional profile column.
        $schema = \App\Support\LegacyDatabaseSchema::class;
        $term = '%'.addcslashes($q, '%_\\').'%';
        $query = DB::table('users')->where('schoolid', 1)
            ->where(function ($filter) {
                $filter->whereNull('smartschool_active')->orWhere('smartschool_active', 1);
            })
            ->where(function ($filter) use ($term, $schema) {
                $filter->where('name', 'like', $term)
                    ->orWhere('smartschool_gebruikersnaam', 'like', $term);
                if ($schema::hasColumn('users', 'voornaam')) {
                    $filter->orWhere('voornaam', 'like', $term);
                }
            })
            ->orderBy('name')->limit(15);

        $fields = ['id', 'name', 'smartschool_gebruikersnaam'];
        if ($schema::hasColumn('users', 'voornaam')) $fields[] = 'voornaam';
        $people = $query->get($fields)->map(function ($person) {
            return [
                'id' => $person->id,
                'name' => $person->name,
                'voornaam' => $person->voornaam ?? '',
                'smartschool_gebruikersnaam' => $person->smartschool_gebruikersnaam,
            ];
        });
        return response()->json($people);
    }

    /**
     * Find catalogue titles and the individual copies that can still be loaned.
     * All writes are validated again in store() using the selected barcode.
     */
    public function searchBooks(Request $request): \Illuminate\Http\JsonResponse
    {
        app(\App\Services\LibraryAccess::class)->requirePermission($request, 'bibliotheek.loans.create');
        $q = trim((string) $request->query('q', ''));
        if (mb_strlen($q) < 2 || mb_strlen($q) > 120) return response()->json([]);

        $term = '%'.addcslashes($q, '%_\\').'%';
        $books = DB::table('library_books as books')
            ->where(function ($query) use ($term) {
                $query->where('books.title', 'like', $term)
                    ->orWhere('books.isbn', 'like', $term)
                    ->orWhere('books.authors', 'like', $term);
            })
            ->whereExists(function ($query) {
                $query->selectRaw('1')->from('library_copies as copies')
                    ->whereColumn('copies.book_id', 'books.id')
                    ->where('copies.status', 'beschikbaar')
                    ->whereNotExists(function ($loans) {
                        $loans->selectRaw('1')->from('library_loans as loans')
                            ->whereColumn('loans.copy_id', 'copies.id')
                            ->whereNull('loans.returned_at');
                    });
            })
            ->select('books.id', 'books.title', 'books.isbn', 'books.authors')
            ->orderBy('books.title')->limit(20)->get();

        return response()->json($books);
    }

    public function availableCopies(Request $request, int $book): \Illuminate\Http\JsonResponse
    {
        app(\App\Services\LibraryAccess::class)->requirePermission($request, 'bibliotheek.loans.create');
        $exists = DB::table('library_books')->where('id', $book)->exists();
        if (! $exists) return response()->json(['message' => 'Boek niet gevonden.'], 404);

        $copies = DB::table('library_copies as copies')
            ->where('copies.book_id', $book)
            ->where('copies.status', 'beschikbaar')
            ->whereNotExists(function ($query) {
                $query->selectRaw('1')->from('library_loans as loans')
                    ->whereColumn('loans.copy_id', 'copies.id')
                    ->whereNull('loans.returned_at');
            })
            ->select('copies.id','copies.barcode', 'copies.shelf_location', 'copies.condition')
            ->orderBy('copies.barcode')->limit(100)->get();

        $reserved=app(\App\Services\LibraryReservations::class)->active()->whereIn('reserved_copy_id',$copies->pluck('id'))->pluck('reserved_copy_id')->all();
        $copies->each(fn($copy)=>$copy->reserved=in_array($copy->id,$reserved));
        return response()->json($copies);
    }

    public function lookupCopy(Request $request): \Illuminate\Http\JsonResponse
    {
        app(\App\Services\LibraryAccess::class)->requirePermission($request, 'bibliotheek.loans.create');
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
        $hold=app(\App\Services\LibraryReservations::class)->active()->where('reserved_copy_id',$copy->id)->exists();
        $copy->reserved=$hold;
        return response()->json(['copy' => $copy]);
    }

    public function store(Request $request): RedirectResponse
    {
        app(\App\Services\LibraryAccess::class)->requirePermission($request, 'bibliotheek.loans.create');
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
                    
                    ->where(function ($q) { $q->whereNull('smartschool_active')->orWhere('smartschool_active', 1); })
                    ->lockForUpdate()->first();
                if (! $staff) {
                    throw ValidationException::withMessages(['medewerker' => 'Selecteer eerst een actieve medewerker.']);
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

                $hold=app(\App\Services\LibraryReservations::class)->active()->where('reserved_copy_id',$copy->id)->lockForUpdate()->first();
                if ($hold && (!$student || (int)$hold->leerling_id!==(int)$student->id)) {
                    throw ValidationException::withMessages(['barcodes'=>"Exemplaar {$barcode} is gereserveerd voor een andere leerling."]);
                }
                if ($hold) DB::table('external_library_requests')->where('id',$hold->id)->update(['status'=>'fulfilled','updated_at'=>now()]);
                $id = DB::table('library_loans')->insertGetId([
                    'copy_id' => $copy->id,
                    'leerling_id' => $student?->id,
                    'staff_user_id' => $staff?->id,
                    'registered_by' => $this->username($request),
                    'loan_date' => $today->toDateString(),
                    'due_date' => app(\App\Services\LibraryCalendar::class)->due($today, (int) $data['loan_days'])->toDateString(),
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

    public function bulk(Request $request): RedirectResponse
    {
        app(\App\Services\LibraryAccess::class)->requirePermission($request, 'bibliotheek.loans.bulk');
        $data=$request->validate(['action'=>'required|in:return,extend','ids'=>'nullable|array|max:100','ids.*'=>'integer|min:1|distinct','barcodes'=>'nullable|string|max:7000']);
        app(\App\Services\LibraryAccess::class)->requirePermission($request, $data['action']==='return' ? 'bibliotheek.loans.return' : 'bibliotheek.loans.extend');
        $ids=$data['ids'] ?? [];
        $fail=[];
        if (!empty($data['barcodes'])) {
            if ($data['action']!=='return') return back()->withErrors(['bulk'=>'Barcodes zijn enkel voor inleveren.']);
            $codes=array_unique(preg_split('/[\s,;]+/',trim($data['barcodes']),-1,PREG_SPLIT_NO_EMPTY));
            if(count($codes)>100) return back()->withErrors(['bulk'=>'Maximaal 100 scans per keer.']);
            foreach($codes as $code){
                $id=DB::table('library_loans as l')->join('library_copies as c','c.id','=','l.copy_id')->where('c.barcode',$code)->whereNull('l.returned_at')->value('l.id');
                if($id) $ids[]=$id; else $fail[]=$code.': geen openstaande uitlening';
            }
        }
        $ids=array_unique($ids);
        $request->session()->forget('errors');
        if(count($ids)>100 || (!$ids && !$fail)) return back()->withErrors(['bulk'=>'Selecteer 1 tot 100 uitleningen.']);
        $ok=0;
        foreach($ids as $id){
            if(!DB::table('library_loans')->where('id',$id)->exists()){ $fail[]='Uitlening #'.$id.': niet gevonden'; continue; }
            $request->session()->forget('errors');
            $response=$data['action']==='return' ? $this->returnLoan((int)$id) : $this->extend((int)$id);
            $errors=$request->session()->get('errors');
            if($errors instanceof \Illuminate\Support\ViewErrorBag && $errors->any()){ $fail[]='Uitlening #'.$id.': '.$errors->first(); $request->session()->forget('errors'); } else $ok++;
        }
        $response=back()->with('success',$ok.' uitleningen verwerkt.');
        return $fail ? $response->withErrors($fail) : $response;
    }

    public function pause(Request $request,int $loan): RedirectResponse
    {
        app(\App\Services\LibraryAccess::class)->requirePermission($request, 'bibliotheek.reminders.pause');
        $data=$request->validate(['until'=>'nullable|date_format:Y-m-d|after_or_equal:today']);
        DB::transaction(function() use($request,$loan,$data){
            $item=DB::table('library_loans')->where('id',$loan)->whereNull('returned_at')->lockForUpdate()->first(); abort_unless($item,404);
            DB::table('library_loans')->where('id',$loan)->update(['reminders_paused_until'=>$data['until'] ?? null,'updated_at'=>now()]);
            DB::table('library_loan_events')->insert(['loan_id'=>$loan,'action'=>'herinneringen_pauze','actor'=>$this->username($request),'details'=>json_encode($data),'created_at'=>now(),'updated_at'=>now()]);
        });
        return back()->with('success','Pauze aangepast.');
    }

    public function returnByBarcode(Request $request): RedirectResponse
    {
        app(\App\Services\LibraryAccess::class)->requirePermission(request(), 'bibliotheek.loans.return');
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
        app(\App\Services\LibraryAccess::class)->requirePermission(request(), 'bibliotheek.loans.return');
        $result = DB::transaction(function () use ($loan) {
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
            return true;
        });
        if ($result instanceof RedirectResponse) return $result;
        $sent = app(\App\Services\LibraryLoanNotifier::class)->send($loan, 'returned', now('Europe/Brussels')->toDateString());
        return back()->with('success', 'Exemplaar succesvol ingeleverd.'
            .($sent ? ' Smartschool-bevestiging verzonden.' : ''));
    }

    public function sendManualReminder(Request $request, int $loan): RedirectResponse
    {
        app(\App\Services\LibraryAccess::class)->requirePermission($request, 'bibliotheek.reminders.send');
        app(\App\Services\LibraryAccess::class)->requirePermission($request, 'bibliotheek.messages.manage');
        $item = DB::table('library_loans')->where('id', $loan)->first();
        abort_unless($item, 404);
        if ($item->returned_at !== null) {
            return back()->withErrors(['reminder' => 'Dit boek is al ingeleverd.']);
        }
        $today = now('Europe/Brussels')->toDateString();
        if (DB::table('library_loan_notifications')->where('loan_id', $loan)
            ->where('type', 'manual')->where('scheduled_for', $today)->exists()) {
            return back()->withErrors(['reminder' => 'Voor deze uitlening werd vandaag al een handmatige herinnering geregistreerd.']);
        }
        if (! app(\App\Services\LibraryLoanNotifier::class)->enabled()) {
            return back()->withErrors(['reminder' => 'Schakel Smartschool-berichten eerst in bij bibliotheekinstellingen.']);
        }
        $sent = app(\App\Services\LibraryLoanNotifier::class)->send($loan, 'manual', $today);
        return $sent
            ? back()->with('success', 'Smartschool-herinnering verzonden.')
            : back()->withErrors(['reminder' => 'Het Smartschool-bericht kon niet worden verzonden. Controleer het verzendlog.']);
    }

    public function extend(int $loan): RedirectResponse
    {
        app(\App\Services\LibraryAccess::class)->requirePermission(request(), 'bibliotheek.loans.extend');
        $result = DB::transaction(function () use ($loan) {
            $item = DB::table('library_loans')->where('id', $loan)->lockForUpdate()->first();
            abort_unless($item, 404);
            if ($item->returned_at !== null) return back()->withErrors(['extend' => 'Een ingeleverd boek kan niet verlengd worden.']);
            if ((int) $item->extensions >= $this->setting('max_extensions', 2)) {
                return back()->withErrors(['extend' => 'Maximaal aantal verlengingen bereikt.']);
            }
            $book=DB::table('library_copies')->where('id',$item->copy_id)->value('book_id');
            if (DB::table('external_library_requests')->where('request_type','reserve')->where('target_id',$book)->where(fn($q)=>$q->where('status','pending')->orWhere(fn($x)=>$x->where('status','approved')->where('reserved_until','>=',now('Europe/Brussels')->toDateString())))->when($item->leerling_id,fn($q)=>$q->where('leerling_id','<>',$item->leerling_id))->exists()) return back()->withErrors(['extend'=>'Een andere leerling wacht op dit boek.']);
            $old = CarbonImmutable::parse($item->due_date, 'Europe/Brussels');
            $base = $old->isAfter(CarbonImmutable::today('Europe/Brussels')) ? $old : CarbonImmutable::today('Europe/Brussels');
            $newDue = app(\App\Services\LibraryCalendar::class)->due($base, $this->setting('extension_days', 21))->toDateString();
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
            return $newDue;
        });
        if ($result instanceof RedirectResponse) return $result;
        $sent = app(\App\Services\LibraryLoanNotifier::class)->send($loan, 'extended', $result);
        return back()->with('success', 'Uitleentermijn verlengd.'
            .($sent ? ' Smartschool-bevestiging verzonden.' : ''));
    }

    public function editReminders(int $loan): View
    {
        app(\App\Services\LibraryAccess::class)->requirePermission(request(), 'bibliotheek.reminders.edit');
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
        app(\App\Services\LibraryAccess::class)->requirePermission(request(), 'bibliotheek.reminders.edit');
        $data = $request->validate([
            'due_date' => ['required', 'date', 'after_or_equal:today'],
            'reminder_days_before' => ['nullable', 'integer', 'min:0', 'max:60'],
            'remind_on_due_date' => ['nullable', 'in:0,1'],
        ]);
        $data['due_date'] = app(\App\Services\LibraryCalendar::class)->adjust(CarbonImmutable::parse($data['due_date'], 'Europe/Brussels'))->toDateString();
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
        $staffOptions = DB::table('users')->where('schoolid', 1)->where('smartschool_active', 1)
            ->whereNotNull('smartschool_gebruikersnaam')->where('smartschool_gebruikersnaam', '<>', '')
            ->select('voornaam', 'name', 'smartschool_gebruikersnaam')->orderBy('name')->get();
        return view('bibliotheek.loans.settings', compact('settings', 'staffOptions'));
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
            'overdue_first_days' => ['required', 'integer', 'min:0', 'max:365'],
            'overdue_repeat_days' => ['required', 'integer', 'min:0', 'max:365'],
            'overdue_escalate_after' => ['required', 'integer', 'min:0', 'max:100'],
            'policy_alert_enabled' => ['required', 'in:0,1'],
            'policy_alert_recipient' => ['nullable', 'string', 'max:120'],
            'loan_day_unit' => ['required','in:calendar,school'],
            'reminder_day_unit' => ['required','in:calendar,school'],
            'shift_due_to_schoolday' => ['required','in:0,1'],
            'skip_weekends' => ['required','in:0,1'],
            'skip_holidays' => ['required','in:0,1'],
        ]);

        if ((int) $values['overdue_escalate_after'] > 1 && (int) $values['overdue_repeat_days'] === 0) {
            return back()->withInput()->withErrors(['overdue_repeat_days' =>
                'Kies een herhaalinterval wanneer een escalatie pas na meerdere herinneringen moet volgen.']);
        }
        if ((int) $values['overdue_escalate_after'] > 0 && (int) $values['overdue_first_days'] === 0) {
            return back()->withInput()->withErrors(['overdue_escalate_after' =>
                'Schakel eerst een herinnering na de inleverdatum in voordat je een escalatie instelt.']);
        }
        if ($values['policy_alert_enabled'] === '1' && ((int) $values['overdue_escalate_after'] === 0 || ! DB::table('users')->where('schoolid', 1)->where('smartschool_active', 1)->where('smartschool_gebruikersnaam', $values['policy_alert_recipient'] ?? '')->exists())) {
            return back()->withInput()->withErrors(['policy_alert_recipient' => 'Stel een escalatiedrempel in en kies een actief personeelsaccount als ontvanger.']);
        }
        $values['policy_alert_recipient'] = $values['policy_alert_recipient'] ?? '';
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
