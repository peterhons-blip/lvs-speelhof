<?php

namespace App\Http\Controllers;

use App\Services\LibraryAccess;
use App\Services\WindowsOperationsStatus;
use Illuminate\Http\Request;

class BackupDashboardController extends Controller
{
    public function index(Request $request, LibraryAccess $access, WindowsOperationsStatus $operations)
    {
        abort_unless($access->role($request) === 'ict_coordinator' && !$request->session()->has('portal_preview'), 403);
        $backup = $operations->backup();
        $history = $operations->backupHistory();
        $successful = count(array_filter($history, fn($row) => $row['local'] === 'ok' && $row['external'] === 'ok'));
        $failed = count(array_filter($history, fn($row) => $row['local'] === 'failed' || $row['external'] === 'failed'));
        $response = response()->view('beheer.backups', compact('backup','history','successful','failed'));
        $response->headers->set('Cache-Control', 'private, no-store');
        return $response;
    }
}
