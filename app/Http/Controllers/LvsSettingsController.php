<?php

namespace App\Http\Controllers;

use App\Services\LibraryAccess;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class LvsSettingsController extends Controller
{
    public function index(Request $request, LibraryAccess $access): View
    {
        $access->requirePermission($request, 'lvs.settings.manage');
        $ready = Schema::hasTable('lvs_followup_rules') &&
            Schema::hasTable('lvs_followup_events');
        $rules = $ready ? DB::table('lvs_followup_rules')->orderBy('threshold')
            ->orderBy('id')->get() : collect();
        $events = $ready ? DB::table('lvs_followup_events as e')
            ->leftJoin('lvs_followup_rules as r', 'r.id', '=', 'e.rule_id')
            ->leftJoin('leerlingen as l', 'l.id', '=', 'e.leerling_id')
            ->select('e.*', 'r.title as rule_title', 'l.voornaam', 'l.naam')
            ->orderByDesc('e.id')->limit(80)->get() : collect();
        $categories = DB::table('meldingscategorien')->orderBy('naam')->get();
        $types = DB::table('meldingssoorten')->orderBy('naam')->get();

        return view('lvs.settings', compact('ready', 'rules', 'events', 'categories', 'types'));
    }

    public function save(Request $request, LibraryAccess $access): RedirectResponse
    {
        $access->requirePermission($request, 'lvs.settings.manage');
        abort_unless(Schema::hasTable('lvs_followup_rules'), 503, 'Voer eerst de LVS-migratie uit.');

        $data = $request->validate([
            'id' => ['nullable', 'integer', 'min:1'],
            'title' => ['required', 'string', 'min:3', 'max:160'],
            'enabled' => ['required', Rule::in(['0', '1'])],
            'category_id' => ['nullable', 'integer', 'exists:meldingscategorien,id'],
            'type_id' => ['nullable', 'integer', 'exists:meldingssoorten,id'],
            'threshold' => ['required', 'integer', 'between:1,500'],
            'window_days' => ['required', 'integer', 'between:0,365'],
            'action' => ['required', Rule::in(['review', 'sanction_proposal', 'smartschool'])],
            'recipient_student' => ['nullable', 'in:1'],
            'recipient_parents' => ['nullable', 'in:1'],
            'recipient_counselor' => ['nullable', 'in:1'],
            'recipient_mentor' => ['nullable', 'in:1'],
            'counselor_username' => ['nullable', 'string', 'max:120', 'regex:/^[a-zA-Z0-9._-]*$/'],
            'mentor_username' => ['nullable', 'string', 'max:120', 'regex:/^[a-zA-Z0-9._-]*$/'],
            'sender_username' => ['nullable', 'string', 'max:120', 'regex:/^[a-zA-Z0-9._-]*$/'],
            'subject' => ['nullable', 'string', 'max:200'],
            'body' => ['nullable', 'string', 'max:3000'],
            'confirm_automation' => ['nullable', 'accepted'],
        ]);

        if (!empty($data['type_id'])) {
            $type = DB::table('meldingssoorten')->where('id', $data['type_id'])->first();
            if (!$type || (!empty($data['category_id']) &&
                (int) $type->meldingscategorieId !== (int) $data['category_id'])) {
                return back()->withInput()->withErrors([
                    'type_id' => 'Deze soort behoort niet tot de gekozen categorie.',
                ]);
            }
        }

        if ($data['action'] === 'smartschool') {
            $anyRecipient = $request->boolean('recipient_student') ||
                $request->boolean('recipient_parents') ||
                $request->boolean('recipient_counselor') ||
                $request->boolean('recipient_mentor');
            if (!$anyRecipient) {
                return back()->withInput()->withErrors(['recipient_student' =>
                    'Selecteer minstens één Smartschool-ontvanger.']);
            }
            if ($request->boolean('recipient_counselor') &&
                trim((string) ($data['counselor_username'] ?? '')) === '') {
                return back()->withInput()->withErrors(['counselor_username' =>
                    'Vul de Smartschool-gebruikersnaam van leerlingbegeleiding in.']);
            }
            if ($request->boolean('recipient_mentor') &&
                trim((string) ($data['mentor_username'] ?? '')) === '') {
                return back()->withInput()->withErrors(['mentor_username' =>
                    'Vul de Smartschool-gebruikersnaam van de klastitularis in.']);
            }
            if (trim((string) ($data['subject'] ?? '')) === '' ||
                trim((string) ($data['body'] ?? '')) === '') {
                return back()->withInput()->withErrors(['body' =>
                    'Vul voor een Smartschool-actie onderwerp en berichttekst in.']);
            }
            if ($data['enabled'] === '1' && !$request->boolean('confirm_automation')) {
                return back()->withInput()->withErrors(['confirm_automation' =>
                    'Bevestig expliciet dat deze regel automatische Smartschool-berichten mag versturen.']);
            }
        }

        $user = $access->username($request);
        $values = [
            'title' => trim($data['title']),
            'enabled' => (int) $data['enabled'],
            'category_id' => $data['category_id'] ?? null,
            'type_id' => $data['type_id'] ?? null,
            'threshold' => (int) $data['threshold'],
            'window_days' => (int) $data['window_days'],
            'action' => $data['action'],
            'recipient_student' => $request->boolean('recipient_student') ? 1 : 0,
            'recipient_parents' => $request->boolean('recipient_parents') ? 1 : 0,
            'recipient_counselor' => $request->boolean('recipient_counselor') ? 1 : 0,
            'recipient_mentor' => $request->boolean('recipient_mentor') ? 1 : 0,
            'counselor_username' => trim((string) ($data['counselor_username'] ?? '')) ?: null,
            'mentor_username' => trim((string) ($data['mentor_username'] ?? '')) ?: null,
            'sender_username' => trim((string) ($data['sender_username'] ?? '')) ?: null,
            'subject' => trim((string) ($data['subject'] ?? '')) ?: null,
            'body' => trim((string) ($data['body'] ?? '')) ?: null,
            'updated_by' => $user,
            'updated_at' => now(),
        ];
        $id = (int) ($data['id'] ?? 0);
        if ($id > 0) {
            abort_unless(DB::table('lvs_followup_rules')->where('id', $id)->exists(), 404);
            DB::table('lvs_followup_rules')->where('id', $id)->update($values);
        } else {
            $values['created_by'] = $user;
            $values['created_at'] = now();
            DB::table('lvs_followup_rules')->insert($values);
        }

        return redirect()->route('lvs.settings.index')
            ->with('success', 'Opvolgregel opgeslagen. Bestaande meldingen worden niet opnieuw verwerkt.');
    }

    public function toggle(Request $request, LibraryAccess $access, int $rule): RedirectResponse
    {
        $access->requirePermission($request, 'lvs.settings.manage');
        $request->validate(['enabled' => ['required', 'in:0,1']]);
        abort_unless(Schema::hasTable('lvs_followup_rules'), 503);
        $row = DB::table('lvs_followup_rules')->where('id', $rule)->first();
        abort_unless($row, 404);
        // Automatic Smartschool sending cannot be switched on without deliberate
        // confirmation at the full edit form, including validated recipients/text.
        if ($row->action === 'smartschool' && $request->input('enabled') === '1') {
            return back()->withErrors(['enabled' =>
                'Bewerk de regel en bevestig de Smartschool-verzending om deze te activeren.']);
        }
        DB::table('lvs_followup_rules')->where('id', $rule)->update([
            'enabled' => (int) $request->input('enabled'),
            'updated_by' => $access->username($request), 'updated_at' => now(),
        ]);
        return back()->with('success', 'Status van de opvolgregel gewijzigd.');
    }
}
