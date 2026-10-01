<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\View\View;

class StaffController extends Controller
{
    public function myTasks(Request $request): View
    {
        $user = $request->user();

        $assignedToMe = $user->assignedTasks()
            ->with('assignedBy')
            ->latest()
            ->get();

        $delegatedByMe = $user->delegatedTasks()
            ->with('assignedTo')
            ->latest()
            ->get();

        return view('staff.my-tasks', [
            'assignedToMe' => $assignedToMe,
            'delegatedByMe' => $delegatedByMe,
        ]);
    }
}