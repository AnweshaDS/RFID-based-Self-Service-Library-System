<x-app-layout>
    <x-slot name="header">
        <div class="flex items-center justify-between">
            <h2 class="font-semibold text-xl text-gray-800 leading-tight">Manage Tasks</h2>
            <a href="{{ route('tasks.create') }}" class="rounded-md bg-indigo-600 px-4 py-2 text-sm font-semibold text-white hover:bg-indigo-700">
                + New Task
            </a>
        </div>
    </x-slot>

    <div class="py-12">
        <div class="max-w-5xl mx-auto sm:px-6 lg:px-8">
            @if (session('status'))
                <p class="mb-4 rounded-md bg-green-100 border border-green-300 px-4 py-2 text-sm text-green-800">{{ session('status') }}</p>
            @endif

            <div class="bg-white border-2 border-gray-200 overflow-hidden shadow rounded-lg">
                <table class="min-w-full divide-y divide-gray-200 text-sm">
                    <thead>
                        <tr class="text-left text-xs uppercase tracking-wide text-gray-600 bg-gray-50">
                            <th class="py-3 px-4">Task</th>
                            <th class="py-3 px-4">Assigned To</th>
                            <th class="py-3 px-4">Assigned By</th>
                            <th class="py-3 px-4">Status</th>
                            <th class="py-3 px-4">Created</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-100">
                        @forelse ($tasks as $task)
                            <tr>
                                <td class="py-3 px-4 font-medium text-gray-900">{{ $task->task }}</td>
                                <td class="py-3 px-4">{{ $task->assignedTo->name ?? '-' }}</td>
                                <td class="py-3 px-4">{{ $task->assignedBy->name ?? '-' }}</td>
                                <td class="py-3 px-4">
                                    <span class="rounded-full px-2 py-0.5 text-xs font-semibold
                                        {{ $task->status === 'done' ? 'bg-green-100 text-green-800' : ($task->status === 'in_progress' ? 'bg-blue-100 text-blue-800' : 'bg-yellow-100 text-yellow-800') }}">
                                        {{ str_replace('_', ' ', $task->status) }}
                                    </span>
                                </td>
                                <td class="py-3 px-4 text-gray-600">{{ $task->created_at->format('d M Y') }}</td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="5" class="py-6 text-center text-gray-500">No tasks yet.</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>

            <div class="mt-4">
                {{ $tasks->links() }}
            </div>
        </div>
    </div>
</x-app-layout>