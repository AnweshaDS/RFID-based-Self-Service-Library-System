<?php

namespace App\Http\Controllers;

use App\Models\ActivityLog;
use App\Models\Department;
use App\Models\Role;
use App\Models\TaskDelegation;
use App\Models\User;
use App\Services\KohaService;
use Illuminate\View\View;

class AdminController extends Controller
{
    public function __construct(protected KohaService $kohaService)
    {
    }

    public function index(): View
    {
        $today = today();

        $stats = [
            'total_users' => User::count(),
            'total_departments' => Department::count(),
            'total_roles' => Role::count(),
            'activities_today' => ActivityLog::whereDate('created_at', $today)->count(),
            'successful_borrows_today' => ActivityLog::where('action', 'borrow')
                ->where('status', 'success')
                ->whereDate('created_at', $today)
                ->count(),
            'failed_operations_today' => ActivityLog::where('status', 'failed')
                ->whereDate('created_at', $today)
                ->count(),
        ];

        $recentActivities = ActivityLog::latest()->take(10)->get();

        // Local activity records, not a live Koha query — see spec section 5.
        $recentBorrows = ActivityLog::where('action', 'borrow')
            ->where('status', 'success')
            ->latest()
            ->take(5)
            ->get();

        $taskDelegations = TaskDelegation::with(['assignedTo', 'assignedBy'])
            ->latest()
            ->take(5)
            ->get();

        $kohaConnected = $this->kohaService->checkStatus();

        return view('admin.dashboard', [
            'stats' => $stats,
            'recentActivities' => $recentActivities,
            'recentBorrows' => $recentBorrows,
            'taskDelegations' => $taskDelegations,
            'kohaConnected' => $kohaConnected,
        ]);
    }
}