<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 leading-tight">
            {{ __('My Tasks') }}
        </h2>
    </x-slot>

    <div class="py-12">
        <div class="max-w-4xl mx-auto sm:px-6 lg:px-8 space-y-6">

            @if (session('status'))
                <p class="rounded-md bg-green-100 border border-green-300 px-4 py-2 text-sm text-green-800">{{ session('status') }}</p>
            @endif

            <div class="bg-white border-2 border-gray-200 overflow-hidden shadow rounded-lg p-6">
                <p class="text-sm font-bold text-gray-600 uppercase tracking-wide">Assigned to me</p>

                <div class="mt-4 space-y-2">
                    @forelse ($assignedToMe as $task)
                        <div class="rounded-md border border-gray-200 bg-gray-50 px-4 py-3 text-sm">
                            <div class="flex items-center justify-between">
                                <span class="font-semibold text-gray-900">{{ $task->task }}</span>
                                <span class="rounded-full px-2 py-0.5 text-xs font-semibold
                                    {{ $task->status === 'done' ? 'bg-green-100 text-green-800' : ($task->status === 'in_progress' ? 'bg-blue-100 text-blue-800' : 'bg-yellow-100 text-yellow-800') }}">
                                    {{ str_replace('_', ' ', $task->status) }}
                                </span>
                            </div>
                            <p class="mt-1 text-xs text-gray-600">
                                Assigned by {{ $task->assignedBy->name ?? '-' }} on {{ $task->created_at->format('d M Y') }}
                            </p>
                            @if ($task->notes)
                                <p class="mt-1 text-xs text-gray-700">{{ $task->notes }}</p>
                            @endif

                            @if ($task->status !== 'done')
                                <form method="POST" action="{{ route('tasks.update-status', $task) }}" class="mt-2 flex items-center gap-2">
                                    @csrf
                                    @method('PATCH')
                                    <select name="status" class="rounded-md border-gray-300 text-xs py-1">
                                        <option value="pending" @selected($task->status === 'pending')>Pending</option>
                                        <option value="in_progress" @selected($task->status === 'in_progress')>In progress</option>
                                        <option value="done">Done</option>
                                    </select>
                                    <button type="submit" class="rounded-md bg-indigo-600 px-3 py-1 text-xs font-semibold text-white hover:bg-indigo-700">
                                        Update
                                    </button>
                                </form>
                            @endif
                        </div>
                    @empty
                        <p class="text-sm text-gray-600">No tasks assigned to you yet.</p>
                    @endforelse
                </div>
            </div>

            @if ($delegatedByMe->isNotEmpty())
                <div class="bg-white border-2 border-gray-200 overflow-hidden shadow rounded-lg p-6">
                    <p class="text-sm font-bold text-gray-600 uppercase tracking-wide">Tasks you've delegated</p>

                    <div class="mt-4 space-y-2">
                        @foreach ($delegatedByMe as $task)
                            <div class="rounded-md border border-gray-200 bg-gray-50 px-4 py-3 text-sm">
                                <div class="flex items-center justify-between">
                                    <span class="font-semibold text-gray-900">{{ $task->task }}</span>
                                    <span class="rounded-full px-2 py-0.5 text-xs font-semibold
                                        {{ $task->status === 'done' ? 'bg-green-100 text-green-800' : ($task->status === 'in_progress' ? 'bg-blue-100 text-blue-800' : 'bg-yellow-100 text-yellow-800') }}">
                                        {{ str_replace('_', ' ', $task->status) }}
                                    </span>
                                </div>
                                <p class="mt-1 text-xs text-gray-600">
                                    Assigned to {{ $task->assignedTo->name ?? '-' }} on {{ $task->created_at->format('d M Y') }}
                                </p>
                            </div>
                        @endforeach
                    </div>
                </div>
            @endif

        </div>
    </div>
</x-app-layout>