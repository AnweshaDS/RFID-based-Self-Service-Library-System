<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 leading-tight">
            {{ __('My Tasks') }}
        </h2>
    </x-slot>

    <div class="py-12">
        <div class="max-w-4xl mx-auto sm:px-6 lg:px-8 space-y-6">

            <div class="bg-white border-2 border-gray-200 overflow-hidden shadow rounded-lg p-6">
                <p class="text-sm font-bold text-gray-600 uppercase tracking-wide">Assigned to me</p>

                <div class="mt-4 space-y-2">
                    @forelse ($assignedToMe as $task)
                        <div class="rounded-md bg-gray-50 px-4 py-3 text-sm">
                            <div class="flex items-center justify-between">
                                <span class="font-medium">{{ $task->task }}</span>
                                <span class="rounded-full px-2 py-0.5 text-xs font-medium {{ $task->status === 'done' ? 'bg-green-100 text-green-700' : 'bg-yellow-100 text-yellow-700' }}">
                                    {{ $task->status }}
                                </span>
                            </div>
                            <p class="mt-1 text-xs text-gray-500">
                                Assigned by {{ $task->assignedBy->name ?? '-' }} on {{ $task->created_at->format('d M Y') }}
                            </p>
                            @if ($task->notes)
                                <p class="mt-1 text-xs text-gray-600">{{ $task->notes }}</p>
                            @endif
                        </div>
                    @empty
                        <p class="text-sm text-gray-500">No tasks assigned to you yet.</p>
                    @endforelse
                </div>
            </div>

            @if ($delegatedByMe->isNotEmpty())
                <div class="bg-white border-2 border-gray-200 overflow-hidden shadow rounded-lg p-6">
                    <p class="text-sm font-bold text-gray-600 uppercase tracking-wide">Tasks you've delegated</p>

                    <div class="mt-4 space-y-2">
                        @foreach ($delegatedByMe as $task)
                            <div class="rounded-md bg-gray-50 px-4 py-3 text-sm">
                                <div class="flex items-center justify-between">
                                    <span class="font-medium">{{ $task->task }}</span>
                                    <span class="rounded-full px-2 py-0.5 text-xs font-medium {{ $task->status === 'done' ? 'bg-green-100 text-green-700' : 'bg-yellow-100 text-yellow-700' }}">
                                        {{ $task->status }}
                                    </span>
                                </div>
                                <p class="mt-1 text-xs text-gray-500">
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