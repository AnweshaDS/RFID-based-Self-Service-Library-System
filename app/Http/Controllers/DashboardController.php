<?php

namespace App\Http\Controllers;

use App\Models\ActivityLog;
use App\Models\Role;
use App\Models\TaskDelegation;
use App\Services\KohaService;
use Illuminate\Http\Request;
use Illuminate\View\View;

class DashboardController extends Controller
{
    public function __construct(protected KohaService $kohaService)
    {
    }

    public function index(Request $request): View
    {
        $user = $request->user()->load('roles.permissions');
        $roleNames = $user->roles->pluck('name');

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
        $hasPatronAccess = $menuItems->contains('label', 'Patrons');
        $canAssignTasks = $user->hasPermission('tasks.assign');
        $isAdmin = $roleNames->contains('Admin');
        $isSubController = $roleNames->contains('Sub-Controller');

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
            $circulationChart = $this->buildCirculationChart();
        }

        $roleDistributionChart = null;
        if ($isAdmin) {
            $roleDistributionChart = Role::withCount('users')
                ->get()
                ->mapWithKeys(fn ($role) => [$role->name => $role->users_count]);
        }

        $delegationChart = null;
        if ($canAssignTasks) {
            $counts = TaskDelegation::where('assigned_by', $user->id)
                ->selectRaw('status, count(*) as count')
                ->groupBy('status')
                ->pluck('count', 'status');

            $delegationChart = [
                'pending' => (int) ($counts['pending'] ?? 0),
                'in_progress' => (int) ($counts['in_progress'] ?? 0),
                'done' => (int) ($counts['done'] ?? 0),
            ];
        }

        $newPatronsChart = null;
        if ($isAdmin || $isSubController) {
            try {
                $newPatronsChart = [
                    'count' => $this->kohaService->countNewPatronsSince(now()->subDays(7)),
                ];
            } catch (\Throwable $e) {
                // Koha unreachable, or the query shape needs adjusting - don't break the dashboard
                $newPatronsChart = null;
            }
        }

                $taskCompletionChart = null;
        if ($roleNames->isNotEmpty()) {
            $days = collect(range(13, 0))->map(fn ($i) => now()->subDays($i)->toDateString());

            $completions = TaskDelegation::where('assigned_to', $user->id)
                ->where('status', 'done')
                ->where('updated_at', '>=', now()->subDays(13)->startOfDay())
                ->selectRaw('DATE(updated_at) as day, count(*) as total')
                ->groupBy('day')
                ->pluck('total', 'day');

            $taskCompletionChart = [
                'labels' => $days->map(fn ($d) => \Illuminate\Support\Carbon::parse($d)->format('d M'))->values(),
                'counts' => $days->map(fn ($d) => (int) ($completions[$d] ?? 0))->values(),
            ];
        }

        return view('dashboard', [
            'roles' => $user->roles,
            'permissions' => $permissions,
            'menuItems' => $menuItems,
            'hasCirculationAccess' => $hasCirculationAccess,
            'canAssignTasks' => $canAssignTasks,
            'taskChart' => $taskChart,
            'circulationChart' => $circulationChart,
            'roleDistributionChart' => $roleDistributionChart,
            'delegationChart' => $delegationChart,
            'newPatronsChart' => $newPatronsChart,
            'taskCompletionChart' => $taskCompletionChart,
            'kohaStaffUrl' => rtrim((string) config('services.koha.staff_url'), '/'),
        ]);
    }

    protected function buildCirculationChart(): array
    {
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

        return [
            'labels' => $days->map(fn ($d) => \Illuminate\Support\Carbon::parse($d)->format('d M'))->values(),
            'borrowed' => $days->map(fn ($d) => (int) ($borrowByDay[$d] ?? 0))->values(),
            'returned' => $days->map(fn ($d) => (int) ($returnByDay[$d] ?? 0))->values(),
            'today_success' => (int) ($todayBreakdown['success'] ?? 0),
            'today_failed' => (int) ($todayBreakdown['failed'] ?? 0),
        ];
    }
}