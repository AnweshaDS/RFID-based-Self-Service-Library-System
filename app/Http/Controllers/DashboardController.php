<?php

namespace App\Http\Controllers;

use App\Models\ActivityLog;
use App\Models\TaskDelegation;
use Illuminate\Http\Request;
use Illuminate\View\View;

class DashboardController extends Controller
{
    public function index(Request $request): View
    {
        $user = $request->user()->load('roles.permissions');

        $permissions = $user->roles
            ->flatMap(fn ($role) => $role->permissions)
            ->unique('id')
            ->sortBy('name')
            ->values();

        $kohaMenuConfig = config('koha_menu', []);

        $menuItems = $user->roles
            ->flatMap(fn ($role) => $kohaMenuConfig[$role->name]['menu'] ?? [])
            ->unique('label')
            ->sortBy('label')
            ->values();

        $hasCirculationAccess = $menuItems->contains('label', 'Circulation');

        $taskCounts = TaskDelegation::where('assigned_to', $user->id)
            ->selectRaw('status, count(*) as count')
            ->groupBy('status')
            ->pluck('count', 'status');

        $taskChart = [
            'pending' => (int) ($taskCounts['pending'] ?? 0),
            'in_progress' => (int) ($taskCounts['in_progress'] ?? 0),
            'done' => (int) ($taskCounts['done'] ?? 0),
        ];

        $circulationChart = null;

        if ($hasCirculationAccess) {
            $days = collect(range(6, 0))->map(fn ($i) => now()->subDays($i)->toDateString());

            $rows = ActivityLog::whereIn('action', ['borrow', 'return'])
                ->where('status', 'success')
                ->where('created_at', '>=', now()->subDays(6)->startOfDay())
                ->selectRaw('DATE(created_at) as day, action, count(*) as total')
                ->groupBy('day', 'action')
                ->get();

            $borrowByDay = $rows->where('action', 'borrow')->pluck('total', 'day');
            $returnByDay = $rows->where('action', 'return')->pluck('total', 'day');

            $todayBreakdown = ActivityLog::whereDate('created_at', today())
                ->selectRaw('status, count(*) as total')
                ->groupBy('status')
                ->pluck('total', 'status');

            $circulationChart = [
                'labels' => $days->map(fn ($d) => \Illuminate\Support\Carbon::parse($d)->format('d M'))->values(),
                'borrowed' => $days->map(fn ($d) => (int) ($borrowByDay[$d] ?? 0))->values(),
                'returned' => $days->map(fn ($d) => (int) ($returnByDay[$d] ?? 0))->values(),
                'today_success' => (int) ($todayBreakdown['success'] ?? 0),
                'today_failed' => (int) ($todayBreakdown['failed'] ?? 0),
            ];
        }

        return view('dashboard', [
            'roles' => $user->roles,
            'permissions' => $permissions,
            'menuItems' => $menuItems,
            'hasCirculationAccess' => $hasCirculationAccess,
            'taskChart' => $taskChart,
            'circulationChart' => $circulationChart,
            'kohaStaffUrl' => rtrim((string) config('services.koha.staff_url'), '/'),
        ]);
    }
}