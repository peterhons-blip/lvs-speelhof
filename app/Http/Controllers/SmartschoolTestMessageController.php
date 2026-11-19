<?php

namespace App\Http\Controllers;

use App\Services\LibraryAccess;
use App\Services\SmartschoolSoap;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\View\View;

class SmartschoolTestMessageController extends Controller
{
    public function create(Request $request, LibraryAccess $access): View
    {
        $access->requireManager($request);

        $sender = old('sender', 'bib');
        $recipient = old('recipient', $access->username($request));
        return view('beheer.smartschool-test', compact('sender', 'recipient'));
    }

    public function send(Request $request, LibraryAccess $access): RedirectResponse
    {
        $access->requireManager($request);

        $data = $request->validate([
            'sender' => ['required', 'string', 'max:120', 'regex:/^[a-zA-Z0-9][a-zA-Z0-9._-]*$/'],
            'recipient' => ['required', 'string', 'max:120', 'regex:/^[a-zA-Z0-9][a-zA-Z0-9._-]*$/'],
            'subject' => ['required', 'string', 'max:200'],
            'message' => ['required', 'string', 'max:5000'],
            'confirm_send' => ['accepted'],
        ]);

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
