<?php

namespace App\Http\Controllers;

use App\Services\LibraryAccess;
use App\Services\SmartschoolSoap;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class SmartschoolTestMessageController extends Controller
{
    public function create(Request $request, LibraryAccess $access): View
    {
        $access->requirePermission($request, 'beheer.smartschool_test.send');

        $sender = old('sender', 'bib');
        $recipient = old('recipient', $access->username($request));
        return view('beheer.smartschool-test', compact('sender', 'recipient'));
    }

    public function recipients(Request $request, LibraryAccess $access): \Illuminate\Http\JsonResponse
    {
        $access->requirePermission($request, 'beheer.smartschool_test.send');
        $data = $request->validate(['q' => ['required', 'string', 'min:2', 'max:80']]);
        $term = trim($data['q']);
        if (mb_strlen($term) < 2) return response()->json([]);

        $students = DB::table('leerlingen')
            ->where('active', 1)
            ->whereNotNull('gebruikersnaam')
            ->where(function ($query) use ($term) {
                $query->where('voornaam', 'like', '%'.$term.'%')
                    ->orWhere('naam', 'like', '%'.$term.'%')
                    ->orWhere('gebruikersnaam', 'like', '%'.$term.'%');
            })
            ->select('gebruikersnaam', 'voornaam', 'naam')
            ->orderBy('naam')->orderBy('voornaam')->limit(10)->get()
            ->map(fn ($person) => [
                'username' => $person->gebruikersnaam,
                'name' => trim(($person->voornaam ?? '').' '.($person->naam ?? '')),
                'type' => 'Leerling',
            ]);

        $staff = DB::table('users')
            ->where('allowed_login', 1)
            ->whereNotNull('gebruikersnaam')
            ->where(function ($query) use ($term) {
                $query->where('voornaam', 'like', '%'.$term.'%')
                    ->orWhere('name', 'like', '%'.$term.'%')
                    ->orWhere('gebruikersnaam', 'like', '%'.$term.'%');
            })
            ->select('gebruikersnaam', 'voornaam', 'name')
            ->orderBy('name')->orderBy('voornaam')->limit(10)->get()
            ->map(fn ($person) => [
                'username' => $person->gebruikersnaam,
                'name' => trim(($person->voornaam ?? '').' '.($person->name ?? '')),
                'type' => 'Personeel',
            ]);

        return response()->json($students->concat($staff)->unique('username')->take(16)->values());
    }

    public function send(Request $request, LibraryAccess $access): RedirectResponse
    {
        $access->requirePermission($request, 'beheer.smartschool_test.send');

        $data = $request->validate([
            'sender' => ['required', Rule::in(['lvs', 'bib'])],
            'recipient' => ['required', 'string', 'max:120', 'regex:/^[a-zA-Z0-9][a-zA-Z0-9._-]*$/'],
            'subject' => ['required', 'string', 'max:200'],
            'message' => ['required', 'string', 'max:5000'],
            'confirm_send' => ['accepted'],
        ]);

        // Only known, active Smartschool-linked LVS accounts are eligible for this manual test.
        $knownRecipient = DB::table('users')->where('gebruikersnaam', $data['recipient'])
            ->where('allowed_login', 1)->exists()
            || DB::table('leerlingen')->where('gebruikersnaam', $data['recipient'])
                ->where('active', 1)->exists();

        if (! $knownRecipient) {
            return back()->withInput()->withErrors([
                'recipient' => 'Kies een actieve leerling of medewerker uit de zoeklijst.',
            ]);
        }

        $sender = trim($data['sender']);
        $recipient = trim($data['recipient']);
        $subject = trim($data['subject']);
        $message = trim($data['message']);

        try {
            // Dedicated, manual ICT-only tool. Uses existing server-side Smartschool SOAP
            // credentials, never credentials supplied by the form.
            $smartschool = app(SmartschoolSoap::class);
            $response = $smartschool->sendMessage(
                $recipient,
                $subject,
                nl2br(e($message)),
                $sender,
                false,
                0
            );

            Log::info('Handmatig Smartschool-testbericht aangeboden', [
                'operator' => $access->username($request),
                'sender' => $sender,
                'recipient' => $recipient,
                'subject' => $subject,
            ]);

            // The library uses the same SOAP method. Do not log message bodies.
            return redirect()->route('beheer.smartschool-test.create')
                ->with('success', 'Het testbericht is naar Smartschool verzonden. Controleer het account van de ontvanger.');
        } catch (\Throwable $e) {
            Log::error('Handmatig Smartschool-testbericht mislukt', [
                'operator' => $access->username($request),
                'sender' => $sender,
                'recipient' => $recipient,
                'error' => $e->getMessage(),
            ]);
            return back()->withInput()->withErrors([
                'smartschool' => 'Verzenden mislukt. Controleer de afzender en ontvanger en raadpleeg het Laravel-log voor de technische fout.',
            ]);
        }
    }
}
