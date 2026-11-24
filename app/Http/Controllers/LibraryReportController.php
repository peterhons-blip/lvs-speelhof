<?php

namespace App\Http\Controllers;

use App\Services\LibraryAccess;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

class LibraryReportController extends Controller
{
    private function filters(Request $request): array
    {
        $year = (int) now('Europe/Brussels')->format('Y');
        if ((int) now('Europe/Brussels')->format('n') < 9) $year--;
        $data = $request->validate([
            'from' => ['nullable', 'date_format:Y-m-d'],
            'to' => ['nullable', 'date_format:Y-m-d'],
            'status' => ['nullable', 'in:all,open,returned,overdue'],
            'borrower' => ['nullable', 'in:all,leerling,personeel'],
        ]);
        $from = $data['from'] ?? ($year.'-09-01');
        $to = $data['to'] ?? (($year + 1).'-08-31');
        if ($from > $to) {
            throw \Illuminate\Validation\ValidationException::withMessages(['from' => 'Begindatum moet vóór de einddatum liggen.']);
        }
        return [$from, $to, $data['status'] ?? 'all', $data['borrower'] ?? 'all'];
    }

    private function loans(string $from, string $to, string $status, string $borrower): \Illuminate\Database\Query\Builder
    {
        $q = DB::table('library_loans as loans')
            ->join('library_copies as copies', 'copies.id', '=', 'loans.copy_id')
            ->join('library_books as books', 'books.id', '=', 'copies.book_id')
            ->leftJoin('leerlingen as student', 'student.id', '=', 'loans.leerling_id')
            ->leftJoin('users as staff', 'staff.id', '=', 'loans.staff_user_id')
            ->whereBetween('loans.loan_date', [$from, $to]);
        if ($status === 'open') $q->whereNull('loans.returned_at');
        if ($status === 'returned') $q->whereNotNull('loans.returned_at');
        if ($status === 'overdue') $q->whereNull('loans.returned_at')->where('loans.due_date', '<', now('Europe/Brussels')->toDateString());
        if ($borrower === 'leerling') $q->whereNotNull('loans.leerling_id');
        if ($borrower === 'personeel') $q->whereNotNull('loans.staff_user_id');
        return $q;
    }

    public function index(Request $request, LibraryAccess $access): View
    {
        $access->requirePermission($request, 'bibliotheek.loans.view');
        [$from, $to, $status, $borrower] = $this->filters($request);

        $stats = [
            'titles' => DB::table('library_books')->count(),
            'copies' => DB::table('library_copies')->count(),
            'available' => DB::table('library_copies')->where('status', 'beschikbaar')->count(),
            'on_loan' => DB::table('library_loans')->whereNull('returned_at')->count(),
            'overdue' => DB::table('library_loans')->whereNull('returned_at')->where('due_date', '<', now('Europe/Brussels')->toDateString())->count(),
            'qr_printed' => DB::table('library_copies')->whereNotNull('qr_printed_at')->count(),
            'period_loans' => $this->loans($from, $to, $status, $borrower)->count(),
        ];

        $monthly = DB::table('library_loans')
            ->whereBetween('loan_date', [$from, $to])
            ->selectRaw("DATE_FORMAT(loan_date, '%Y-%m') as month, COUNT(*) as total")
            ->groupByRaw("DATE_FORMAT(loan_date, '%Y-%m')")
            ->orderBy('month')->get();

        $popular = $this->loans($from, $to, $status, $borrower)
            ->select('books.id', 'books.title', 'books.authors')
            ->selectRaw('COUNT(loans.id) as times_loaned')
            ->groupBy('books.id', 'books.title', 'books.authors')
            ->orderByDesc('times_loaned')->limit(10)->get();

        $borrowers = $this->loans($from, $to, $status, $borrower)
            ->select('loans.staff_user_id', 'loans.leerling_id',
                'student.voornaam as student_first', 'student.naam as student_last',
                'staff.voornaam as staff_first', 'staff.name as staff_last')
            ->selectRaw('COUNT(loans.id) as times_loaned')
            ->groupBy('loans.staff_user_id', 'loans.leerling_id',
                'student.voornaam', 'student.naam', 'staff.voornaam', 'staff.name')
            ->orderByDesc('times_loaned')->limit(20)->get();

        $loans = $this->loans($from, $to, $status, $borrower)
            ->select('loans.id', 'loans.loan_date', 'loans.due_date', 'loans.returned_at',
                'books.title', 'copies.barcode', 'student.voornaam as student_first',
                'student.naam as student_last', 'staff.voornaam as staff_first',
                'staff.name as staff_last', 'loans.staff_user_id')
            ->orderByDesc('loans.loan_date')->orderByDesc('loans.id')
            ->paginate(25)->withQueryString();

        return view('bibliotheek.reports.index', compact('from', 'to', 'status', 'borrower', 'stats', 'monthly', 'popular', 'borrowers', 'loans'));
    }

    public function export(Request $request, LibraryAccess $access): StreamedResponse
    {
        $access->requirePermission($request, 'bibliotheek.loans.view');
        [$from, $to, $status, $borrower] = $this->filters($request);
        $query = $this->loans($from, $to, $status, $borrower)
            ->select('loans.id', 'loans.loan_date', 'loans.due_date', 'loans.returned_at',
                'books.title', 'books.isbn', 'copies.barcode',
                'student.voornaam as student_first', 'student.naam as student_last',
                'staff.voornaam as staff_first', 'staff.name as staff_last', 'loans.staff_user_id')
            ->orderBy('loans.id');

        return response()->streamDownload(function () use ($query) {
            $handle = fopen('php://output', 'w');
            fwrite($handle, "\xEF\xBB\xBF");
            fputcsv($handle, ['ID', 'Datum uitlening', 'Uiterste datum', 'Ingeleverd op',
                'Boek', 'ISBN', 'Exemplaarcode', 'Type ontlener', 'Ontlener'], ';');
            foreach ($query->cursor() as $loan) {
                $name = $loan->staff_user_id !== null
                    ? trim(($loan->staff_first ?? '').' '.($loan->staff_last ?? ''))
                    : trim(($loan->student_first ?? '').' '.($loan->student_last ?? ''));
                // Protect spreadsheet users from formula injection.
                $cells = [$loan->id, $loan->loan_date, $loan->due_date, $loan->returned_at,
                    $loan->title, $loan->isbn, $loan->barcode,
                    $loan->staff_user_id !== null ? 'Personeel' : 'Leerling', $name];
                $cells = array_map(function ($value) {
                    $v = (string) ($value ?? '');
                    return preg_match('/^[\s]*[=+@\-]/u', $v) ? "'".$v : $v;
                }, $cells);
                fputcsv($handle, $cells, ';');
            }
            fclose($handle);
        }, 'bibliotheek-uitleningen-'.$from.'-'.$to.'.csv', ['Content-Type' => 'text/csv; charset=UTF-8']);
    }
}
