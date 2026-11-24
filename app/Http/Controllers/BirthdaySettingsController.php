<?php

namespace App\Http\Controllers;

use App\Services\BirthdaySettings;
use App\Services\LibraryAccess;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\View\View;

class BirthdaySettingsController extends Controller
{
    public function index(Request $request, LibraryAccess $access, BirthdaySettings $birthdaySettings): View
    {
        $access->requirePermission($request, 'beheer.birthdays.manage');
        $settings = $birthdaySettings->all();
        $previewDate = (string) $request->query('datum', now('Europe/Brussels')->toDateString());
        $validatedDate = \DateTimeImmutable::createFromFormat('!Y-m-d', $previewDate);
        if (! $validatedDate || $validatedDate->format('Y-m-d') !== $previewDate) {
            $previewDate = now('Europe/Brussels')->toDateString();
        }
        $preview = $birthdaySettings->preview($previewDate, $settings);
        $schools = DB::table('scholen')->orderBy('schoolnaam')->get(['id', 'schoolnaam', 'ontvangers_overzicht_18', 'ontvangers_overzicht_18_ssaccount']);
        $staffOptions = DB::table('users')->whereNotNull('smartschool_gebruikersnaam')
            ->where('smartschool_gebruikersnaam', '<>', '')
            ->where(function ($query) {
                $query->whereNull('smartschool_active')->orWhere('smartschool_active', 1);
            })
            ->select('schoolid', 'voornaam', 'name', 'smartschool_gebruikersnaam')
            ->orderBy('name')->orderBy('voornaam')->get()
            ->groupBy('schoolid');
        $schoolSmartschoolChoices = [];
        foreach ($schools as $school) {
            $options = collect($staffOptions->get($school->id, collect()))
                ->mapWithKeys(fn ($person) => [
                    (string) $person->smartschool_gebruikersnaam =>
                        trim(($person->voornaam ?? '').' '.($person->name ?? '')).' ('.$person->smartschool_gebruikersnaam.')',
                ]);
            $existing = preg_split('/[;,\\r\\n]+/', (string) $school->ontvangers_overzicht_18_ssaccount, -1, PREG_SPLIT_NO_EMPTY);
            foreach ($existing as $username) {
                $username = trim($username);
                if ($username !== '' && ! $options->has($username)) {
                    $options->put($username, $username.' (bestaande ontvanger, niet in actieve personeelslijst)');
                }
            }
            $schoolSmartschoolChoices[$school->id] = $options;
        }
        $recent = Schema::hasTable('birthday_deliveries')
            ? DB::table('birthday_deliveries')
                ->select('birthday_date', 'channel', 'status', DB::raw('COUNT(*) as total'))
                ->groupBy('birthday_date', 'channel', 'status')
                ->orderByDesc('birthday_date')->limit(30)->get() : collect();

        return view('beheer.birthdays', compact('settings', 'recent', 'previewDate', 'preview', 'schools', 'schoolSmartschoolChoices'));
    }


    /**
     * 18-year overview recipients are already read directly from scholen
     * by CheckLeeftijdLeerlingen. Update that existing source of truth.
     */
    public function updateOverviewRecipients(Request $request, LibraryAccess $access): RedirectResponse
    {
        $access->requirePermission($request, 'beheer.birthdays.manage');
        $data = $request->validate([
            'school_id' => ['required', 'integer', 'min:1'],
            'overview_smartschool_users' => ['sometimes', 'array', 'max:150'],
            'overview_smartschool_users.*' => ['string', 'max:120', 'regex:/^[a-zA-Z0-9._-]{2,120}$/'],
            'overview_email' => ['nullable', 'string', 'max:3000'],
        ]);

        $normalize = static function (?string $raw, bool $email): string {
            $items = preg_split('/[;,\\r\\n]+/', (string) $raw, -1, PREG_SPLIT_NO_EMPTY);
            $items = array_values(array_unique(array_filter(array_map('trim', $items))));
            foreach ($items as $item) {
                if ($email) {
                    if (! filter_var($item, FILTER_VALIDATE_EMAIL)) {
                        throw \Illuminate\Validation\ValidationException::withMessages([
                            'overview_email' => 'Ongeldig e-mailadres: '.$item,
                        ]);
                    }
                } elseif (! preg_match('/^[a-zA-Z0-9._-]{2,120}$/', $item)) {
                    throw \Illuminate\Validation\ValidationException::withMessages([
                        'overview_smartschool' => 'Ongeldige Smartschool-gebruikersnaam: '.$item,
                    ]);
                }
            }
            return implode('; ', $items);
        };

        $smartschool = $normalize(implode('; ', $data['overview_smartschool_users'] ?? []), false);
        $emails = $normalize($data['overview_email'] ?? '', true);

        $updated = DB::table('scholen')->where('id', $data['school_id'])
            ->update([
                'ontvangers_overzicht_18_ssaccount' => $smartschool,
                'ontvangers_overzicht_18' => $emails,
            ]);
        if (! $updated && ! DB::table('scholen')->where('id', $data['school_id'])->exists()) {
            return back()->withErrors(['school_id' => 'School niet gevonden.']);
        }

        return redirect()->route('beheer.birthdays.index', ['overzicht_school' => $data['school_id']])
            ->with('success', 'Ontvangers van het 18-jarigenoverzicht opgeslagen voor de geselecteerde school.');
    }

    public function updateWarning(Request $request, LibraryAccess $access): RedirectResponse
    {
        $access->requirePermission($request, 'beheer.birthdays.manage');
        $data = $request->validate([
            'warning_enabled' => ['required', 'in:0,1'],
            'warning_days' => ['required', 'integer', 'min:1', 'max:30'],
            'warning_student' => ['required', 'in:0,1'],
            'warning_coaccounts' => ['required', 'in:0,1'],
            'warning_sender' => ['nullable', 'string', 'max:120', 'regex:/^[a-zA-Z0-9._-]*$/'],
            'warning_subject' => ['required', 'string', 'max:200'],
            'warning_body_student' => ['nullable', 'string', 'max:5000'],
            'warning_body_coaccount' => ['nullable', 'string', 'max:5000'],
        ]);
        if (! Schema::hasTable('birthday_settings')) {
            return back()->withErrors(['database' => 'Voer eerst de verjaardagsmigratie uit.']);
        }
        DB::transaction(function () use ($data) {
            foreach ($data as $key => $value) {
                DB::table('birthday_settings')->updateOrInsert(
                    ['setting_key' => $key], ['setting_value' => trim((string) ($value ?? ''))]
                );
            }
        });
        return redirect()->route('beheer.birthdays.index')
            ->with('success', 'Waarschuwingen voor 18-jarigen opgeslagen.');
    }

    public function update(Request $request, LibraryAccess $access, BirthdaySettings $birthdaySettings): RedirectResponse
    {
        $access->requirePermission($request, 'beheer.birthdays.manage');
        $data = $request->validate([
            'enabled' => ['required', 'in:0,1'],
            'students_enabled' => ['required', 'in:0,1'],
            'staff_enabled' => ['required', 'in:0,1'],
            'smartschool_students' => ['required', 'in:0,1'],
            'smartschool_staff' => ['required', 'in:0,1'],
            'email_students' => ['required', 'in:0,1'],
            'email_staff' => ['required', 'in:0,1'],
            'school_id' => ['required', 'integer', 'min:0', 'max:999999'],
            'sender' => ['nullable', 'string', 'max:120', 'regex:/^[a-zA-Z0-9._-]*$/'],
            'subject_students' => ['required', 'string', 'max:200'],
            'subject_staff' => ['required', 'string', 'max:200'],
            'body_students' => ['required', 'string', 'max:4000'],
            'body_staff' => ['required', 'string', 'max:4000'],
        ]);
        if (! Schema::hasTable('birthday_settings') || ! Schema::hasTable('birthday_deliveries')) {
            return back()->withErrors(['database' => 'Voer eerst de verjaardagsmigratie uit.'])->withInput();
        }
        if ((int) $data['school_id'] !== 0 && ! DB::table('scholen')->where('id', $data['school_id'])->exists()) {
            return back()->withErrors(['school_id' => 'Deze school bestaat niet.'])->withInput();
        }
        if (($data['email_students'] === '1' || $data['email_staff'] === '1')
            && in_array(config('mail.default'), ['log', 'array'], true)) {
            return back()->withErrors(['email_students' => 'Configureer eerst een echte Laravel-mailer voordat e-mail wordt ingeschakeld.'])->withInput();
        }

        DB::transaction(function () use ($data) {
            foreach ($data as $key => $value) {
                DB::table('birthday_settings')->updateOrInsert(
                    ['setting_key' => $key], ['setting_value' => trim((string) ($value ?? ''))]
                );
            }
        });
        return redirect()->route('beheer.birthdays.index')
            ->with('success', 'Verjaardagsinstellingen opgeslagen. De volgende geplande uitvoering gebruikt deze instellingen.');
    }
}
