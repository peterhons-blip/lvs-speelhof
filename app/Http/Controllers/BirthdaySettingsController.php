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
        $recent = Schema::hasTable('birthday_deliveries')
            ? DB::table('birthday_deliveries')
                ->select('birthday_date', 'channel', 'status', DB::raw('COUNT(*) as total'))
                ->groupBy('birthday_date', 'channel', 'status')
                ->orderByDesc('birthday_date')->limit(30)->get() : collect();

        return view('beheer.birthdays', compact('settings', 'recent', 'previewDate', 'preview'));
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
