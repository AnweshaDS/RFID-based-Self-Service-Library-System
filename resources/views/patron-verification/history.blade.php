<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 leading-tight">Verification History</h2>
    </x-slot>

    <div class="py-12">
        <div class="max-w-6xl mx-auto sm:px-6 lg:px-8 space-y-6">

            <div class="bg-white border-2 border-gray-200 rounded-lg shadow p-5">
                <form method="GET" class="flex flex-wrap gap-3">
                    <input type="text" name="cardnumber" value="{{ request('cardnumber') }}" placeholder="Card number"
                           class="rounded-md border-gray-300 text-sm">
                    <select name="action" class="rounded-md border-gray-300 text-sm">
                        <option value="">Any action</option>
                        <option value="borrow" @selected(request('action') === 'borrow')>Borrow</option>
                        <option value="return" @selected(request('action') === 'return')>Return</option>
                    </select>
                    <input type="date" name="date" value="{{ request('date') }}" class="rounded-md border-gray-300 text-sm">
                    <button type="submit" class="rounded-md bg-indigo-600 px-4 py-2 text-sm font-semibold text-white hover:bg-indigo-700">
                        Filter
                    </button>
                </form>
            </div>

            <div class="grid grid-cols-2 sm:grid-cols-4 gap-4">
                @forelse ($captures as $capture)
                    <div class="bg-white border-2 border-gray-200 rounded-lg shadow p-3">
                        <img src="{{ route('patron-verification.history.photo', $capture) }}" alt="Verification photo" class="w-full rounded-md aspect-square object-cover">
                        <p class="mt-2 text-xs font-semibold text-gray-900 truncate">{{ $capture->patron_name ?? $capture->cardnumber }}</p>
                        <p class="text-xs text-gray-500 capitalize">{{ $capture->action }} @if($capture->barcode) - {{ $capture->barcode }} @endif</p>
                        <p class="text-xs text-gray-400">{{ $capture->created_at->format('d M Y, H:i') }}</p>
                        @unless ($capture->face_detected)
                            <p class="text-xs text-red-600 font-medium">No face detected</p>
                        @endunless
                    </div>
                @empty
                    <p class="col-span-full text-sm text-gray-500">No verification photos recorded yet.</p>
                @endforelse
            </div>

            <div>{{ $captures->links() }}</div>
        </div>
    </div>
</x-app-layout>