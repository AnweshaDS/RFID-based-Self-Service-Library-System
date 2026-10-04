<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 leading-tight">Assign a New Task</h2>
    </x-slot>

    <div class="py-12">
        <div class="max-w-2xl mx-auto sm:px-6 lg:px-8">
            <div class="bg-white border-2 border-gray-200 shadow rounded-lg p-6">
                <form method="POST" action="{{ route('tasks.store') }}" class="space-y-5">
                    @csrf

                    <div>
                        <label for="assigned_to" class="block text-sm font-semibold text-gray-700">Assign to</label>
                        <select id="assigned_to" name="assigned_to" required
                                class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
                            <option value="">Select staff member</option>
                            @foreach ($staff as $person)
                                <option value="{{ $person->id }}" @selected(old('assigned_to') == $person->id)>
                                    {{ $person->name }}
                                </option>
                            @endforeach
                        </select>
                        @error('assigned_to')
                            <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                        @enderror
                    </div>

                    <div>
                        <label for="task" class="block text-sm font-semibold text-gray-700">Task</label>
                        <input type="text" id="task" name="task" value="{{ old('task') }}" required
                               class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500"
                               placeholder="e.g. Shelve returned books, Section B">
                        @error('task')
                            <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                        @enderror
                    </div>

                    <div>
                        <label for="notes" class="block text-sm font-semibold text-gray-700">Notes (optional)</label>
                        <textarea id="notes" name="notes" rows="3"
                                  class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500">{{ old('notes') }}</textarea>
                    </div>

                    <div class="flex items-center gap-3">
                        <button type="submit" class="rounded-md bg-indigo-600 px-4 py-2 text-sm font-semibold text-white hover:bg-indigo-700">
                            Assign Task
                        </button>
                        <a href="{{ route('tasks.index') }}" class="text-sm text-gray-600 hover:text-gray-900">Cancel</a>
                    </div>
                </form>
            </div>
        </div>
    </div>
</x-app-layout>