<?php

namespace App\Http\Controllers;

use App\Services\LibraryAccess;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\View\View;

class SentItemsController extends Controller
{
    public function index(Request $request, LibraryAccess $access): View
    {
        $access->requirePermission($request, 'beheer.sent_items.view');

        $data = $request->validate([
            'channel' => ['nullable', 'in:smartschool,email'],
            'status' => ['nullable', 'in:sent,failed'],
            'search' => ['nullable', 'string', 'max:150'],
            'date_from' => ['nullable', 'date_format:Y-m-d'],
            'date_to' => ['nullable', 'date_format:Y-m-d', 'after_or_equal:date_from'],
        ]);
        $hasHistory = Schema::hasTable('portal_sent_items');
        $items = null;
        $totals = collect();
        if ($hasHistory) {
            $query = DB::table('portal_sent_items');

            if (! empty($data['channel'])) $query->where('channel', $data['channel']);
            if (! empty($data['status'])) $query->where('status', $data['status']);
            if (! empty($data['date_from'])) $query->where('created_at', '>=', $data['date_from'].' 00:00:00');
            if (! empty($data['date_to'])) $query->where('created_at', '<=', $data['date_to'].' 23:59:59');
            if (! empty($data['search'])) {
                $term = str_replace(['%', '_'], ['\\%', '\\_'], trim($data['search']));
                $query->where(static function ($q) use ($term) {
                    $q->where('recipient', 'like', '%'.$term.'%')
                        ->orWhere('sender', 'like', '%'.$term.'%')
                        ->orWhere('subject', 'like', '%'.$term.'%')
                        ->orWhere('source', 'like', '%'.$term.'%');
                });
            }

            $totals = (clone $query)->select('channel', 'status', DB::raw('COUNT(*) as total'))
                ->groupBy('channel', 'status')->get();
            $items = $query->orderByDesc('id')->paginate(50)->withQueryString();
        }

        return view('beheer.sent-items', compact('items', 'totals', 'hasHistory', 'data'));
    }
}
