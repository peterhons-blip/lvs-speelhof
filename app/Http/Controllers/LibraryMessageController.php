<?php

namespace App\Http\Controllers;

use App\Services\LibraryAccess;
use App\Services\LibraryLoanNotifier;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\View\View;

class LibraryMessageController extends Controller
{
    public function index(Request $request, LibraryAccess $access): View
    {
        $access->requirePermission($request, 'bibliotheek.messages.manage');

        $defaults = LibraryLoanNotifier::defaultTemplates();
        $saved = Schema::hasTable('library_message_templates')
            ? DB::table('library_message_templates')->get()->keyBy('type') : collect();

        $templates = [];
        foreach ($defaults as $type => $default) {
            $custom = $saved->get($type);
            $templates[$type] = [
                'label' => $default['label'],
                'subject' => $custom?->subject ?? $default['subject'],
                'body' => $custom?->body ?? $default['body'],
            ];
        }

        $enabled = DB::table('library_settings')->where('key', 'smartschool_enabled')->value('value') === '1';
        return view('bibliotheek.messages.index', compact('templates', 'enabled'));
    }

    public function update(Request $request, LibraryAccess $access): RedirectResponse
    {
        $access->requirePermission($request, 'bibliotheek.messages.manage');
        $types = array_keys(LibraryLoanNotifier::defaultTemplates());
        $values = $request->validate([
            'smartschool_enabled' => ['required', 'in:0,1'],
            'templates' => ['required', 'array'],
            'templates.*.subject' => ['required', 'string', 'max:200'],
            'templates.*.body' => ['required', 'string', 'max:5000'],
        ]);

        foreach ($types as $type) {
            if (! isset($values['templates'][$type])) {
                return back()->withErrors(['templates' => "Sjabloon {$type} ontbreekt."])->withInput();
            }
        }
        if (! Schema::hasTable('library_message_templates')) {
            return back()->withErrors(['templates' => 'Voer eerst de bibliotheek-sjablonenmigratie uit.'])->withInput();
        }

        DB::transaction(function () use ($values, $types) {
            foreach ($types as $type) {
                DB::table('library_message_templates')->updateOrInsert(
                    ['type' => $type],
                    [
                        'subject' => trim($values['templates'][$type]['subject']),
                        'body' => trim($values['templates'][$type]['body']),
                        'updated_at' => now(),
                    ]
                );
            }
            DB::table('library_settings')->updateOrInsert(
                ['key' => 'smartschool_enabled'],
                ['value' => $values['smartschool_enabled'], 'updated_at' => now()]
            );
        });

        return redirect()->route('bibliotheek.messages.index')
            ->with('success', 'Smartschool-berichten en verzendinstelling opgeslagen. Afzender: bib.');
    }
}
