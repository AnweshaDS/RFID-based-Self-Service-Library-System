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
                <p class="text-sm font-semibold text-gray-700">Activity Log</p>

                @php
                    $curSort = request('sort', 'created_at');
                    $curDir = request('dir', 'desc');
                    $sortUrl = fn ($col) => request()->fullUrlWithQuery([
                        'sort' => $col,
                        'dir' => ($curSort === $col && $curDir === 'asc') ? 'desc' : 'asc',
                        'page' => null,
                    ]);
                    $arrow = fn ($col) => $curSort === $col ? ($curDir === 'asc' ? ' &uarr;' : ' &darr;') : '';
                @endphp

                <form method="GET" action="{{ route('admin.dashboard') }}" class="mt-4 grid grid-cols-1 gap-3 text-sm sm:grid-cols-2 lg:grid-cols-4">
                    <input type="hidden" name="sort" value="{{ $curSort }}">
                    <input type="hidden" name="dir" value="{{ $curDir }}">

                    <select name="action" class="rounded-md border-gray-300 text-sm">
                        <option value="">All actions</option>
                        @foreach ($actionOptions as $opt)
                            <option value="{{ $opt }}" @selected(request('action') === $opt)>{{ ucfirst(str_replace('_', ' ', $opt)) }}</option>
                        @endforeach
                    </select>

                    <select name="status" class="rounded-md border-gray-300 text-sm">
                        <option value="">All statuses</option>
                        @foreach ($statusOptions as $opt)
                            <option value="{{ $opt }}" @selected(request('status') === $opt)>{{ ucfirst($opt) }}</option>
                        @endforeach
                    </select>

                    <select name="kiosk" class="rounded-md border-gray-300 text-sm">
                        <option value="">All kiosks</option>
                        @foreach ($kioskOptions as $opt)
                            <option value="{{ $opt }}" @selected(request('kiosk') === $opt)>{{ $opt }}</option>
                        @endforeach
                        <option value="none" @selected(request('kiosk') === 'none')>Before tracking</option>
                    </select>

                    <input type="text" name="patron" value="{{ request('patron') }}" placeholder="Patron ID (e.g. STU002)" class="rounded-md border-gray-300 text-sm">
                    <input type="text" name="barcode" value="{{ request('barcode') }}" placeholder="Barcode (e.g. BOOK001)" class="rounded-md border-gray-300 text-sm">
                    <input type="text" name="message" value="{{ request('message') }}" placeholder="Message contains..." class="rounded-md border-gray-300 text-sm">
                    <input type="date" name="from" value="{{ request('from') }}" class="rounded-md border-gray-300 text-sm" title="From date">
                    <input type="date" name="to" value="{{ request('to') }}" class="rounded-md border-gray-300 text-sm" title="To date">

                    <div class="flex gap-2 sm:col-span-2 lg:col-span-4">
                        <button type="submit" class="rounded-md bg-gray-800 px-4 py-2 text-xs font-semibold uppercase tracking-wide text-white">Filter</button>
                        <a href="{{ route('admin.dashboard') }}" class="rounded-md border border-gray-300 px-4 py-2 text-xs font-semibold uppercase tracking-wide text-gray-700">Clear</a>
                    </div>
                </form>

                <div class="mt-4">
                    {{ $recentActivities->links() }}
                </div>

                <div class="mt-4 overflow-x-auto">
                    <table class="min-w-full divide-y divide-gray-200 text-sm">
                        <thead>
                            <tr class="text-left text-xs uppercase tracking-wide text-gray-500">
                                <th class="py-2 pr-4"><a href="{{ $sortUrl('action') }}">Action{!! $arrow('action') !!}</a></th>
                                <th class="py-2 pr-4"><a href="{{ $sortUrl('status') }}">Status{!! $arrow('status') !!}</a></th>
                                <th class="py-2 pr-4"><a href="{{ $sortUrl('message') }}">Message{!! $arrow('message') !!}</a></th>
                                <th class="py-2 pr-4"><a href="{{ $sortUrl('patron_id') }}">Patron ID{!! $arrow('patron_id') !!}</a></th>
                                <th class="py-2 pr-4"><a href="{{ $sortUrl('barcode') }}">Barcode{!! $arrow('barcode') !!}</a></th>
                                <th class="py-2 pr-4"><a href="{{ $sortUrl('kiosk_id') }}">Kiosk{!! $arrow('kiosk_id') !!}</a></th>
                                <th class="py-2 pr-4"><a href="{{ $sortUrl('created_at') }}">Time{!! $arrow('created_at') !!}</a></th>
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
                                    <td class="py-2 pr-4">{{ $activity->kiosk_id ?? 'Before tracking' }}</td>
                                    <td class="py-2 pr-4 text-gray-500">{{ $activity->created_at->timezone('Asia/Dhaka')->format('d M Y, H:i') }}</td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="7" class="py-4 text-center text-gray-500">No activity recorded yet.</td>
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
                            <span class="text-gray-500">{{ $borrow->created_at->timezone('Asia/Dhaka')->format('d M Y, H:i') }}</span>
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