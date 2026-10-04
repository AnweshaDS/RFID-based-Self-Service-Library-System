<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 leading-tight">
            {{ __('Dashboard') }}
        </h2>
    </x-slot>

    <div class="py-12">
        <div class="max-w-4xl mx-auto sm:px-6 lg:px-8 space-y-6">

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
                            <span class="text-base text-gray-600">No role assigned yet — contact an administrator.</span>
                        @endforelse
                    </div>
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

            @auth
                @if (Auth::user()->roles->isNotEmpty())
                    <div class="bg-white border-2 border-gray-200 rounded-lg shadow p-6 flex items-center justify-between">
                        <div>
                            <p class="text-sm font-bold text-gray-600 uppercase tracking-wide">Your tasks</p>
                            <p class="mt-1 text-sm text-gray-600">See what's been assigned to you.</p>
                        </div>
                        <a href="{{ route('my-tasks') }}" class="rounded-md bg-indigo-600 px-4 py-2 text-sm font-semibold text-white hover:bg-indigo-700">
                            View My Tasks
                        </a>
                    </div>
                @endif
            @endauth

        </div>
    </div>
</x-app-layout>