<?php

namespace App\Http\Controllers;

use App\Models\TaskDelegation;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;

class TaskController extends Controller
{
    public function index(): View
    {
        $tasks = TaskDelegation::with(['assignedTo', 'assignedBy'])
            ->latest()
            ->paginate(20);

        return view('tasks.index', ['tasks' => $tasks]);
    }

    public function create(): View
    {
        $staff = User::whereHas('roles')->orderBy('name')->get();

        return view('tasks.create', ['staff' => $staff]);
    }

    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'assigned_to' => ['required', 'exists:users,id'],
            'task' => ['required', 'string', 'max:255'],
            'notes' => ['nullable', 'string', 'max:1000'],
        ]);

        TaskDelegation::create([
            'task' => $validated['task'],
            'assigned_by' => $request->user()->id,
            'assigned_to' => $validated['assigned_to'],
            'status' => 'pending',
            'notes' => $validated['notes'] ?? null,
        ]);

        return redirect()->route('tasks.index')->with('status', 'Task assigned successfully.');
    }

    public function updateStatus(Request $request, TaskDelegation $task): RedirectResponse
    {
        if ($task->assigned_to !== $request->user()->id) {
            abort(403);
        }

        $validated = $request->validate([
            'status' => ['required', 'in:pending,in_progress,done'],
        ]);

        $task->update(['status' => $validated['status']]);

        return redirect()->route('my-tasks')->with('status', 'Task status updated.');
    }
}