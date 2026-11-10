<?php

namespace App\Http\Controllers;

use App\Models\ActivityLog;
use App\Models\Department;
use App\Models\Role;
use App\Models\TaskDelegation;
use App\Models\User;
use App\Services\KohaService;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\View\View;

class AdminController extends Controller
{
    public function __construct(protected KohaService $kohaService)
    {
    }

    public function index(Request $request): View
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

        $request->validate([
            'from' => ['nullable', 'date'],
            'to' => ['nullable', 'date'],
        ]);

        $query = ActivityLog::query();

        if ($request->filled('action')) {
            $query->where('action', $request->input('action'));
        }
        if ($request->filled('status')) {
            $query->where('status', $request->input('status'));
        }
        if ($request->filled('kiosk')) {
            $request->input('kiosk') === 'none'
                ? $query->whereNull('kiosk_id')
                : $query->where('kiosk_id', $request->input('kiosk'));
        }
        if ($request->filled('patron')) {
            $query->where('patron_id', trim($request->input('patron')));
        }
        if ($request->filled('barcode')) {
            $query->where('barcode', trim($request->input('barcode')));
        }
        if ($request->filled('message')) {
            $query->where('message', 'like', '%' . trim($request->input('message')) . '%');
        }
        if ($request->filled('from')) {
            $query->where('created_at', '>=', Carbon::parse($request->input('from'), 'Asia/Dhaka')->startOfDay()->utc());
        }
        if ($request->filled('to')) {
            $query->where('created_at', '<=', Carbon::parse($request->input('to'), 'Asia/Dhaka')->endOfDay()->utc());
        }

        $sortable = ['action', 'status', 'message', 'patron_id', 'barcode', 'kiosk_id', 'created_at'];
        $sort = in_array($request->input('sort'), $sortable, true) ? $request->input('sort') : 'created_at';
        $dir = $request->input('dir') === 'asc' ? 'asc' : 'desc';

        $recentActivities = $query->orderBy($sort, $dir)->orderByDesc('id')->paginate(20)->withQueryString();

        $actionOptions = ActivityLog::select('action')->distinct()->orderBy('action')->pluck('action');
        $statusOptions = ActivityLog::select('status')->distinct()->orderBy('status')->pluck('status');
        $kioskOptions = ActivityLog::whereNotNull('kiosk_id')->select('kiosk_id')->distinct()->orderBy('kiosk_id')->pluck('kiosk_id');

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
            'actionOptions' => $actionOptions,
            'statusOptions' => $statusOptions,
            'kioskOptions' => $kioskOptions,
        ]);
    }
}