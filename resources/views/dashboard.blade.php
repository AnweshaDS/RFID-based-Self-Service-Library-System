<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 leading-tight">
            {{ __('Dashboard') }}
        </h2>
    </x-slot>

    <div class="py-12">
        <div class="max-w-7xl mx-auto sm:px-6 lg:px-8 space-y-6">

            <div class="bg-white border-2 border-gray-200 rounded-lg shadow p-6">
                <p class="text-base text-gray-800">
                    {{ __("You're logged in as") }} <span class="font-bold">{{ Auth::user()->name }}</span>.
                </p>

                <div class="mt-5">
                    <p class="text-sm font-bold text-gray-600 uppercase tracking-wide">Your role{{ $roles->count() > 1 ? 's' : '' }}</p>
                    <div class="mt-2 flex flex-wrap gap-2">
                        @forelse ($roles as $role)
                            <span class="inline-block rounded-full bg-indigo-600 px-4 py-1.5 text-base font-bold text-white">
                                {{ $role->name }}
                            </span>
                        @empty
                            <span class="text-base text-gray-600">No role assigned yet - contact an administrator.</span>
                        @endforelse
                    </div>
                </div>
            </div>

            <div class="bg-white border-2 border-gray-200 rounded-lg shadow p-5">
    <p class="text-sm font-bold text-gray-600 uppercase tracking-wide">Library System</p>
    <div class="mt-4 space-y-2">
        <a href="{{ route('my-tasks') }}" class="flex items-center gap-3 rounded-md border border-gray-200 bg-gray-50 px-3 py-2.5 text-sm font-medium text-gray-800 transition hover:border-indigo-400 hover:bg-indigo-50">
            <svg class="h-4 w-4 flex-shrink-0 text-indigo-600" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.75" stroke-linecap="round" stroke-linejoin="round"><path d="M9 11l3 3L22 4"/><path d="M21 12v7a2 2 0 01-2 2H5a2 2 0 01-2-2V5a2 2 0 012-2h11"/></svg>
            <span class="flex-1">My Tasks</span>
        </a>
        @can('tasks.assign')
            <a href="{{ route('tasks.index') }}" class="flex items-center gap-3 rounded-md border border-gray-200 bg-gray-50 px-3 py-2.5 text-sm font-medium text-gray-800 transition hover:border-indigo-400 hover:bg-indigo-50">
                <svg class="h-4 w-4 flex-shrink-0 text-indigo-600" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.75" stroke-linecap="round" stroke-linejoin="round"><line x1="12" y1="5" x2="12" y2="19"/><line x1="5" y1="12" x2="19" y2="12"/></svg>
                <span class="flex-1">Assign a Task</span>
            </a>
        @endcan
    </div>
</div>

            <div class="grid grid-cols-1 lg:grid-cols-4 gap-6">

                <div class="lg:col-span-1 bg-white border-2 border-gray-200 rounded-lg shadow p-5">
                    <p class="text-sm font-bold text-gray-600 uppercase tracking-wide">Koha Quick Links</p>
                    <p class="mt-1 text-xs text-gray-500">Opens the Koha staff interface in a new tab. You'll need to be logged into Koha separately.</p>

                    <div class="mt-4 space-y-2">
                        @forelse ($menuItems as $item)
                            @php
                                $icon = match ($item['label']) {
                                    'Circulation' => 'M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15',
                                    'Patrons' => 'M17 20h5v-2a4 4 0 00-3-3.87M9 20H4v-2a4 4 0 013-3.87m6-5.13a4 4 0 100-8 4 4 0 000 8zm6 3a4 4 0 100-8 4 4 0 000 8z',
                                    'Cataloging' => 'M7 7h.01M7 3h5c.512 0 1.024.195 1.414.586l7 7a2 2 0 010 2.828l-7 7a2 2 0 01-2.828 0l-7-7A1.994 1.994 0 013 10V5a2 2 0 012-2z',
                                    'Administration' => 'M10.325 4.317c.426-1.756 2.924-1.756 3.35 0a1.724 1.724 0 002.573 1.066c1.543-.94 3.31.826 2.37 2.37a1.724 1.724 0 001.065 2.572c1.756.426 1.756 2.924 0 3.35a1.724 1.724 0 00-1.066 2.573c.94 1.543-.826 3.31-2.37 2.37a1.724 1.724 0 00-2.572 1.065c-.426 1.756-2.924 1.756-3.35 0a1.724 1.724 0 00-2.573-1.066c-1.543.94-3.31-.826-2.37-2.37a1.724 1.724 0 00-1.065-2.572c-1.756-.426-1.756-2.924 0-3.35a1.724 1.724 0 001.066-2.573c-.94-1.543.826-3.31 2.37-2.37.996.608 2.296.07 2.572-1.065z',
                                    'Reports' => 'M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z',
                                    default => 'M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z',
                                };
                            @endphp
                            <a href="{{ $kohaStaffUrl }}{{ $item['path'] }}" target="_blank" rel="noopener"
                               class="flex items-center gap-3 rounded-md border border-gray-200 bg-gray-50 px-3 py-2.5 text-sm font-medium text-gray-800 transition hover:border-indigo-400 hover:bg-indigo-50">
                                <svg class="h-4 w-4 flex-shrink-0 text-indigo-600" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.75" stroke-linecap="round" stroke-linejoin="round">
                                    <path d="{{ $icon }}"/>
                                </svg>
                                <span class="flex-1">{{ $item['label'] }}</span>
                                <svg class="h-3.5 w-3.5 text-gray-400" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M18 13v6a2 2 0 01-2 2H5a2 2 0 01-2-2V8a2 2 0 012-2h6"/><path d="M15 3h6v6"/><path d="M10 14L21 3"/></svg>
                            </a>
                        @empty
                            <p class="text-sm text-gray-500">No Koha sections configured for your role yet.</p>
                        @endforelse
                    </div>
                </div>

                <div class="lg:col-span-3 space-y-6">

                    <div class="grid grid-cols-1 {{ $hasCirculationAccess ? 'sm:grid-cols-2' : '' }} gap-6">

                        <div class="bg-white border-2 border-gray-200 rounded-lg shadow p-5">
                            <p class="text-sm font-bold text-gray-600 uppercase tracking-wide">My Task Status</p>
                            <div class="mt-3" style="max-width: 280px; margin: 0 auto;">
                                <canvas id="taskChart"></canvas>
                            </div>
                            <div class="mt-3 flex justify-center gap-4 text-xs text-gray-600">
                                <span><span class="inline-block h-2.5 w-2.5 rounded-full bg-yellow-400 mr-1"></span>Pending ({{ $taskChart['pending'] }})</span>
                                <span><span class="inline-block h-2.5 w-2.5 rounded-full bg-blue-400 mr-1"></span>In Progress ({{ $taskChart['in_progress'] }})</span>
                                <span><span class="inline-block h-2.5 w-2.5 rounded-full bg-green-500 mr-1"></span>Done ({{ $taskChart['done'] }})</span>
                            </div>
                        </div>

                        @if ($hasCirculationAccess && $circulationChart)
                            <div class="bg-white border-2 border-gray-200 rounded-lg shadow p-5">
                                <p class="text-sm font-bold text-gray-600 uppercase tracking-wide">Today's Activity</p>
                                <div class="mt-3" style="max-width: 280px; margin: 0 auto;">
                                    <canvas id="todayChart"></canvas>
                                </div>
                                <div class="mt-3 flex justify-center gap-4 text-xs text-gray-600">
                                    <span><span class="inline-block h-2.5 w-2.5 rounded-full bg-green-500 mr-1"></span>Success ({{ $circulationChart['today_success'] }})</span>
                                    <span><span class="inline-block h-2.5 w-2.5 rounded-full bg-red-500 mr-1"></span>Failed ({{ $circulationChart['today_failed'] }})</span>
                                </div>
                            </div>
                        @endif

                        @if ($roleDistributionChart)
    <div class="bg-white border-2 border-gray-200 rounded-lg shadow p-5">
        <p class="text-sm font-bold text-gray-600 uppercase tracking-wide">Staff by Role</p>
        <div class="mt-3">
            <canvas id="roleChart" height="160"></canvas>
        </div>
    </div>
@endif

@if ($delegationChart)
    <div class="bg-white border-2 border-gray-200 rounded-lg shadow p-5">
        <p class="text-sm font-bold text-gray-600 uppercase tracking-wide">Tasks You've Delegated</p>
        <div class="mt-3" style="max-width: 280px; margin: 0 auto;">
            <canvas id="delegationChart"></canvas>
        </div>
        <div class="mt-3 flex justify-center gap-4 text-xs text-gray-600">
            <span><span class="inline-block h-2.5 w-2.5 rounded-full bg-yellow-400 mr-1"></span>Pending ({{ $delegationChart['pending'] }})</span>
            <span><span class="inline-block h-2.5 w-2.5 rounded-full bg-blue-400 mr-1"></span>In Progress ({{ $delegationChart['in_progress'] }})</span>
            <span><span class="inline-block h-2.5 w-2.5 rounded-full bg-green-500 mr-1"></span>Done ({{ $delegationChart['done'] }})</span>
        </div>
    </div>
@endif

@if ($newPatronsChart)
    <div class="bg-white border-2 border-gray-200 rounded-lg shadow p-5 flex flex-col items-center justify-center text-center">
        <p class="text-sm font-bold text-gray-600 uppercase tracking-wide">New Patrons (7 days)</p>
        <p class="mt-3 font-display text-5xl text-indigo-600">{{ $newPatronsChart['count'] }}</p>
    </div>
@endif
                    </div>

                    @if ($hasCirculationAccess && $circulationChart)
                        <div class="bg-white border-2 border-gray-200 rounded-lg shadow p-5">
                            <p class="text-sm font-bold text-gray-600 uppercase tracking-wide">Borrows &amp; Returns - Last 7 Days</p>
                            <div class="mt-4">
                                <canvas id="weeklyChart" height="90"></canvas>
                            </div>
                        </div>
                    @endif
                </div>
            </div>

            @if ($permissions->isNotEmpty())
                <div class="bg-white border-2 border-gray-200 rounded-lg shadow p-6">
                    <p class="text-sm font-bold text-gray-600 uppercase tracking-wide">What you can do</p>
                    <div class="mt-3 grid grid-cols-1 sm:grid-cols-2 gap-2">
                        @foreach ($permissions as $permission)
                            <div class="flex items-start gap-2 rounded-md border border-gray-200 bg-gray-50 px-3 py-2.5 text-sm">
                                <span class="mt-0.5 text-green-700 font-bold">&#10003;</span>
                                <div>
                                    <p class="font-semibold text-gray-900">{{ $permission->name }}</p>
                                    @if ($permission->description)
                                        <p class="text-gray-600 text-xs mt-0.5">{{ $permission->description }}</p>
                                    @endif
                                </div>
                            </div>
                        @endforeach
                    </div>
                </div>
            @endif

        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.1/dist/chart.umd.min.js"></script>
    <script>
        new Chart(document.getElementById('taskChart'), {
            type: 'doughnut',
            data: {
                labels: ['Pending', 'In Progress', 'Done'],
                datasets: [{
                    data: [{{ $taskChart['pending'] }}, {{ $taskChart['in_progress'] }}, {{ $taskChart['done'] }}],
                    backgroundColor: ['#facc15', '#60a5fa', '#22c55e'],
                }],
            },
            options: { plugins: { legend: { display: false } } },
        });

        @if ($roleDistributionChart)
    new Chart(document.getElementById('roleChart'), {
        type: 'bar',
        data: {
            labels: @json($roleDistributionChart->keys()),
            datasets: [{
                label: 'Staff',
                data: @json($roleDistributionChart->values()),
                backgroundColor: '#6366f1',
            }],
        },
        options: {
            indexAxis: 'y',
            plugins: { legend: { display: false } },
            scales: { x: { beginAtZero: true, ticks: { stepSize: 1 } } },
        },
    });
@endif

@if ($delegationChart)
    new Chart(document.getElementById('delegationChart'), {
        type: 'doughnut',
        data: {
            labels: ['Pending', 'In Progress', 'Done'],
            datasets: [{
                data: [{{ $delegationChart['pending'] }}, {{ $delegationChart['in_progress'] }}, {{ $delegationChart['done'] }}],
                backgroundColor: ['#facc15', '#60a5fa', '#22c55e'],
            }],
        },
        options: { plugins: { legend: { display: false } } },
    });
@endif

        @if ($hasCirculationAccess && $circulationChart)
            new Chart(document.getElementById('todayChart'), {
                type: 'doughnut',
                data: {
                    labels: ['Success', 'Failed'],
                    datasets: [{
                        data: [{{ $circulationChart['today_success'] }}, {{ $circulationChart['today_failed'] }}],
                        backgroundColor: ['#22c55e', '#ef4444'],
                    }],
                },
                options: { plugins: { legend: { display: false } } },
            });

            new Chart(document.getElementById('weeklyChart'), {
                type: 'line',
                data: {
                    labels: @json($circulationChart['labels']),
                    datasets: [
                        {
                            label: 'Borrowed',
                            data: @json($circulationChart['borrowed']),
                            borderColor: '#E4A72E',
                            backgroundColor: 'rgba(228,167,46,0.15)',
                            tension: 0.3,
                            fill: true,
                        },
                        {
                            label: 'Returned',
                            data: @json($circulationChart['returned']),
                            borderColor: '#1F6F78',
                            backgroundColor: 'rgba(31,111,120,0.15)',
                            tension: 0.3,
                            fill: true,
                        },
                    ],
                },
                options: {
                    scales: { y: { beginAtZero: true, ticks: { stepSize: 1 } } },
                },
            });
        @endif
    </script>
</x-app-layout>