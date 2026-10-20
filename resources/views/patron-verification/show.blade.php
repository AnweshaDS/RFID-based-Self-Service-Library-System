<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 leading-tight">Patron Verification</h2>
    </x-slot>

    <div class="py-12">
        <div class="max-w-5xl mx-auto sm:px-6 lg:px-8 space-y-6">

            <div class="bg-white border-2 border-gray-200 rounded-lg shadow p-6">
                <form method="POST" action="{{ route('patron-verification.search') }}" class="flex gap-3">
                    @csrf
                    <input type="text" name="cardnumber" placeholder="Enter patron card number"
                           class="flex-1 rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
                    <button type="submit" class="rounded-md bg-indigo-600 px-4 py-2 text-sm font-semibold text-white hover:bg-indigo-700">
                        Look up patron
                    </button>
                </form>
                @error('cardnumber')
                    <p class="mt-2 text-sm text-red-600">{{ $message }}</p>
                @enderror
            </div>

            <div class="grid grid-cols-1 sm:grid-cols-2 gap-6">

                <div class="bg-white border-2 border-gray-200 rounded-lg shadow p-5">
                    <p class="text-sm font-bold text-gray-600 uppercase tracking-wide mb-3">Live Camera</p>
                    <video id="liveFeed" autoplay playsinline class="w-full rounded-md bg-black aspect-[4/3]"></video>
                    <div class="mt-3 flex gap-2">
                        <button type="button" id="captureBtn" class="flex-1 rounded-md bg-gray-800 px-3 py-2 text-sm font-semibold text-white hover:bg-gray-700">
                            Freeze Frame
                        </button>
                        <button type="button" id="resumeBtn" class="flex-1 rounded-md border border-gray-300 px-3 py-2 text-sm font-semibold text-gray-700 hover:bg-gray-50" style="display:none;">
                            Resume
                        </button>
                    </div>
                    <canvas id="captureCanvas" class="w-full rounded-md mt-3" style="display:none;"></canvas>
                    <p id="cameraError" class="mt-2 text-sm text-red-600"></p>
                </div>

                <div class="bg-white border-2 border-gray-200 rounded-lg shadow p-5">
                    <p class="text-sm font-bold text-gray-600 uppercase tracking-wide mb-3">Koha Record</p>

                    @if ($patron)
                        <p class="font-semibold text-gray-900">{{ trim(($patron['firstname'] ?? '') . ' ' . ($patron['surname'] ?? '')) ?: $patron['cardnumber'] }}</p>
                        <p class="text-sm text-gray-600">Card: {{ $patron['cardnumber'] }}</p>
                        <p class="text-sm text-gray-600">Patron ID: {{ $patron['patron_id'] }}</p>

                        <div class="mt-4">
                            @if ($photoAvailable)
                                <img src="{{ route('patron-verification.photo', $patron['patron_id']) }}" alt="Patron photo" class="w-full rounded-md border border-gray-200">
                            @else
                                <div class="flex items-center justify-center rounded-md border-2 border-dashed border-gray-300 aspect-[4/3] text-gray-400 text-sm">
                                    No photo on file in Koha
                                </div>
                            @endif
                        </div>
                    @else
                        <div class="flex items-center justify-center rounded-md border-2 border-dashed border-gray-300 aspect-[4/3] text-gray-400 text-sm">
                            Search for a patron above
                        </div>
                    @endif
                </div>

            </div>
        </div>
    </div>

    <script>
        (function () {
            const video = document.getElementById('liveFeed');
            const canvas = document.getElementById('captureCanvas');
            const captureBtn = document.getElementById('captureBtn');
            const resumeBtn = document.getElementById('resumeBtn');
            const errorEl = document.getElementById('cameraError');

            navigator.mediaDevices.getUserMedia({ video: true })
                .then(stream => { video.srcObject = stream; })
                .catch(err => { errorEl.textContent = 'Camera access denied or unavailable: ' + err.message; });

            captureBtn.addEventListener('click', () => {
                canvas.width = video.videoWidth;
                canvas.height = video.videoHeight;
                canvas.getContext('2d').drawImage(video, 0, 0);
                video.style.display = 'none';
                canvas.style.display = 'block';
                captureBtn.style.display = 'none';
                resumeBtn.style.display = 'block';
            });

            resumeBtn.addEventListener('click', () => {
                video.style.display = 'block';
                canvas.style.display = 'none';
                captureBtn.style.display = 'block';
                resumeBtn.style.display = 'none';
            });
        })();
    </script>
</x-app-layout>