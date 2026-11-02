<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 leading-tight">Verification History</h2>
    </x-slot>

    <style>
        .vh-card { display: flex; flex-wrap: wrap; gap: 20px; }
        .vh-koha { flex: 0 0 170px; max-width: 100%; }
        .vh-main { flex: 1 1 320px; min-width: 0; }
        .vh-frame { position: relative; width: 100%; aspect-ratio: 1 / 1; border-radius: 6px; overflow: hidden; background: #f3f4f6; border: 1px solid #e5e7eb; }
        .vh-frame img { width: 100%; height: 100%; object-fit: cover; display: block; }
        .vh-missing { display: none; position: absolute; top: 0; right: 0; bottom: 0; left: 0; align-items: center; justify-content: center; text-align: center; padding: 8px; font-size: 11px; color: #6b7280; }
        .vh-missing-on { display: flex; }
        .vh-grid { display: grid; grid-template-columns: repeat(auto-fill, minmax(110px, 1fr)); gap: 10px; }
        .vh-cap { font-size: 11px; line-height: 1.3; margin-top: 4px; }
        .vh-label { font-size: 11px; color: #6b7280; margin-top: 6px; text-align: center; }
    </style>

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

            @forelse ($patrons as $p)
                @php($hasKohaId = ctype_digit($p['patron_id']))

                <div class="bg-white border-2 border-gray-200 rounded-lg shadow p-4">
                    <div class="vh-card">

                        <div class="vh-koha">
                            <div class="vh-frame">
                                @if ($hasKohaId)
                                    <img src="{{ route('patron-verification.photo', $p['patron_id']) }}" alt="Registered photo in Koha" loading="lazy"
                                         onerror="this.style.display='none'; this.nextElementSibling.style.display='flex';">
                                    <div class="vh-missing">No photo on file in Koha</div>
                                @else
                                    <div class="vh-missing vh-missing-on">No Koha patron linked</div>
                                @endif
                            </div>
                            <p class="vh-label">Registered photo (Koha)</p>
                        </div>

                        <div class="vh-main">
                            <div style="margin-bottom: 12px;">
                                <p class="text-sm font-semibold text-gray-900">{{ $p['name'] ?? $p['cardnumber'] ?? 'Unknown patron' }}</p>
                                <p class="text-xs text-gray-500">
                                    Card {{ $p['cardnumber'] ?? 'unknown' }}
                                    - {{ $p['total'] }} {{ \Illuminate\Support\Str::plural('photo', $p['total']) }}
                                    - last on {{ $p['last_at']->format('d M Y, H:i') }}
                                </p>
                            </div>

                            <div class="vh-grid">
                                @foreach ($p['captures'] as $capture)
                                    <div>
                                        <div class="vh-frame">
                                            <img src="{{ route('patron-verification.history.photo', $capture) }}" alt="Verification photo" loading="lazy"
                                                 onerror="this.style.display='none'; this.nextElementSibling.style.display='flex';">
                                            <div class="vh-missing">Photo file is not on this computer</div>
                                        </div>
                                        <p class="vh-cap text-gray-500 capitalize">{{ $capture->action }}@if ($capture->barcode) - {{ $capture->barcode }}@endif</p>
                                        <p class="vh-cap text-gray-400">{{ $capture->created_at->format('d M Y, H:i') }}</p>
                                        @unless ($capture->face_detected)
                                            <p class="vh-cap text-red-600 font-medium">No face detected</p>
                                        @endunless
                                    </div>
                                @endforeach
                            </div>

                            @if ($p['expanded'])
                                <p class="mt-3 text-xs">
                                    <a href="{{ request()->fullUrlWithQuery(['show_all' => null]) }}" class="text-indigo-600 hover:underline">Show only the latest {{ $photosPerCard }}</a>
                                </p>
                            @elseif ($p['total'] > $photosPerCard)
                                <p class="mt-3 text-xs">
                                    <a href="{{ request()->fullUrlWithQuery(['show_all' => $p['patron_id']]) }}" class="text-indigo-600 hover:underline">Show all {{ $p['total'] }} photos</a>
                                </p>
                            @endif
                        </div>

                    </div>
                </div>
            @empty
                <p class="text-sm text-gray-500">No verification photos recorded yet.</p>
            @endforelse

            <div>{{ $patrons->links() }}</div>
        </div>
    </div>
</x-app-layout>