<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 leading-tight">
            {{ __('Dashboard') }}
        </h2>
    </x-slot>

    <div class="py-12">
        <div class="max-w-4xl mx-auto sm:px-6 lg:px-8 space-y-6">

            <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg p-6">
                <p class="text-base text-gray-700">
                    {{ __("You're logged in as") }} <span class="font-semibold">{{ Auth::user()->name }}</span>.
                </p>

                <div class="mt-4">
                    <p class="text-sm font-semibold text-gray-500 uppercase tracking-wide">Your role{{ $roles->count() > 1 ? 's' : '' }}</p>
                    <div class="mt-2 flex flex-wrap gap-2">
                        @forelse ($roles as $role)
                            <span class="inline-block rounded-full bg-indigo-100 px-4 py-1.5 text-base font-semibold text-indigo-800">
                                {{ $role->name }}
                            </span>
                        @empty
                            <span class="text-base text-gray-500">No role assigned yet — contact an administrator.</span>
                        @endforelse
                    </div>
                </div>
            </div>

            @if ($permissions->isNotEmpty())
                <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg p-6">
                    <p class="text-sm font-semibold text-gray-500 uppercase tracking-wide">What you can do</p>
                    <div class="mt-3 grid grid-cols-1 sm:grid-cols-2 gap-2">
                        @foreach ($permissions as $permission)
                            <div class="flex items-start gap-2 rounded-md bg-gray-50 px-3 py-2 text-sm">
                                <span class="mt-0.5 text-green-600">&#10003;</span>
                                <div>
                                    <p class="font-medium text-gray-800">{{ $permission->name }}</p>
                                    @if ($permission->description)
                                        <p class="text-gray-500 text-xs mt-0.5">{{ $permission->description }}</p>
                                    @endif
                                </div>
                            </div>
                        @endforeach
                    </div>
                </div>
            @endif

        </div>
    </div>
</x-app-layout>