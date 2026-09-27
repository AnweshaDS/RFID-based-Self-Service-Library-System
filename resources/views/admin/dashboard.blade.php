<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 leading-tight">
            {{ __('Admin Dashboard') }}
        </h2>
    </x-slot>

    <div class="py-12">
        <div class="max-w-7xl mx-auto sm:px-6 lg:px-8 space-y-6">

            <div class="grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-3">
                <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg p-6">
                    <p class="text-sm text-gray-500">Total Users</p>
                    <p class="mt-1 text-3xl font-semibold text-gray-900">{{ $stats['total_users'] }}</p>
                </div>
                <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg p-6">
                    <p class="text-sm text-gray-500">Departments</p>
                    <p class="mt-1 text-3xl font-semibold text-gray-900">{{ $stats['total_departments'] }}</p>
                </div>
                <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg p-6">
                    <p class="text-sm text-gray-500">Roles</p>
                    <p class="mt-1 text-3xl font-semibold text-gray-900">{{ $stats['total_roles'] }}</p>
                </div>
                <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg p-6">
                    <p class="text-sm text-gray-500">Activity Today</p>
                    <p class="mt-1 text-3xl font-semibold text-gray-900">{{ $stats['activities_today'] }}</p>
                </div>
                <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg p-6">
                    <p class="text-sm text-gray-500">Successful Borrows Today</p>
                    <p class="mt-1 text-3xl font-semibold text-green-600">{{ $stats['successful_borrows_today'] }}</p>
                </div>
                <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg p-6">
                    <p class="text-sm text-gray-500">Failed Operations Today</p>
                    <p class="mt-1 text-3xl font-semibold text-red-600">{{ $stats['failed_operations_today'] }}</p>
                </div>
            </div>

            <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg p-6">
                <p class="text-sm text-gray-500">Koha Connection</p>
                <p class="mt-1 flex items-center gap-2 text-lg font-semibold">
                    <span class="h-2.5 w-2.5 rounded-full {{ $kohaConnected ? 'bg-green-500' : 'bg-red-500' }}"></span>
                    <span class="{{ $kohaConnected ? 'text-green-700' : 'text-red-700' }}">
                        {{ $kohaConnected ? 'Connected' : 'Unavailable' }}
                    </span>
                </p>
            </div>

            <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg p-6">
                <p class="text-sm font-semibold text-gray-700">Recent Activity</p>

                <div class="mt-4 overflow-x-auto">
                    <table class="min-w-full divide-y divide-gray-200 text-sm">
                        <thead>
                            <tr class="text-left text-xs uppercase tracking-wide text-gray-500">
                                <th class="py-2 pr-4">Action</th>
                                <th class="py-2 pr-4">Status</th>
                                <th class="py-2 pr-4">Message</th>
                                <th class="py-2 pr-4">Patron ID</th>
                                <th class="py-2 pr-4">Barcode</th>
                                <th class="py-2 pr-4">Time</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-100">
                            @forelse ($recentActivities as $activity)
                                <tr>
                                    <td class="py-2 pr-4 capitalize">{{ str_replace('_', ' ', $activity->action) }}</td>
                                    <td class="py-2 pr-4">
                                        <span class="rounded-full px-2 py-0.5 text-xs font-medium {{ $activity->status === 'success' ? 'bg-green-100 text-green-700' : 'bg-red-100 text-red-700' }}">
                                            {{ $activity->status }}
                                        </span>
                                    </td>
                                    <td class="py-2 pr-4 text-gray-600">{{ $activity->message }}</td>
                                    <td class="py-2 pr-4">{{ $activity->patron_id ?? '-' }}</td>
                                    <td class="py-2 pr-4 font-mono">{{ $activity->barcode ?? '-' }}</td>
                                    <td class="py-2 pr-4 text-gray-500">{{ $activity->created_at->format('d M Y, H:i') }}</td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="6" class="py-4 text-center text-gray-500">No activity recorded yet.</td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>

            <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg p-6">
                <p class="text-sm font-semibold text-gray-700">Current Borrowing Overview</p>
                <p class="mt-1 text-xs text-gray-400">Based on local activity records, not a live Koha query.</p>

                <div class="mt-4 space-y-2">
                    @forelse ($recentBorrows as $borrow)
                        <div class="flex items-center justify-between rounded-md bg-gray-50 px-4 py-2.5 text-sm">
                            <span>{{ $borrow->message ?? 'Book borrowed' }}</span>
                            <span class="text-gray-500">{{ $borrow->created_at->format('d M Y, H:i') }}</span>
                        </div>
                    @empty
                        <p class="text-sm text-gray-500">No borrow activity recorded yet.</p>
                    @endforelse
                </div>
            </div>

            <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg p-6">
                <p class="text-sm font-semibold text-gray-700">Task Delegations</p>

                <div class="mt-4 overflow-x-auto">
                    <table class="min-w-full divide-y divide-gray-200 text-sm">
                        <thead>
                            <tr class="text-left text-xs uppercase tracking-wide text-gray-500">
                                <th class="py-2 pr-4">Task</th>
                                <th class="py-2 pr-4">Assigned To</th>
                                <th class="py-2 pr-4">Assigned By</th>
                                <th class="py-2 pr-4">Status</th>
                                <th class="py-2 pr-4">Created</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-100">
                            @forelse ($taskDelegations as $task)
                                <tr>
                                    <td class="py-2 pr-4">{{ $task->task }}</td>
                                    <td class="py-2 pr-4">{{ $task->assignedTo->name ?? '-' }}</td>
                                    <td class="py-2 pr-4">{{ $task->assignedBy->name ?? '-' }}</td>
                                    <td class="py-2 pr-4 capitalize">{{ $task->status }}</td>
                                    <td class="py-2 pr-4 text-gray-500">{{ $task->created_at->format('d M Y') }}</td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="5" class="py-4 text-center text-gray-500">No task delegations yet.</td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>

        </div>
    </div>
</x-app-layout>