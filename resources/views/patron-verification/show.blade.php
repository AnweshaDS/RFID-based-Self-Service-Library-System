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
                    <p class="text-sm font-bold text-gray-600 uppercase tracking-wide mb-3">Live Capture</p>

                    <div class="relative rounded-md overflow-hidden bg-black aspect-[4/3]">
                        <video id="liveFeed" autoplay playsinline muted class="w-full h-full object-cover"></video>
                        <canvas id="captureCanvas" class="w-full h-full object-cover" style="display:none;"></canvas>

                        <div id="guideOverlay" class="pointer-events-none absolute inset-0 flex items-center justify-center">
                            <div class="border-4 border-dashed border-signal-amber rounded-full" style="width:55%; aspect-ratio:3/4; border-color:#facc15;"></div>
                        </div>
                        <p id="guideText" class="pointer-events-none absolute bottom-2 left-0 right-0 text-center text-xs font-medium text-white bg-black/50 py-1">
                            Position your face in the oval and look straight at the camera
                        </p>
                    </div>

                    <p id="modelStatus" class="mt-2 text-xs text-gray-500">Loading face detection...</p>
                    <p id="resultMessage" class="mt-2 text-sm font-medium"></p>

                    <div class="mt-3 flex gap-2">
                        <button type="button" id="captureBtn" disabled class="flex-1 rounded-md bg-gray-800 px-3 py-2 text-sm font-semibold text-white hover:bg-gray-700 disabled:opacity-50">
                            Capture Photo
                        </button>
                        <button type="button" id="retakeBtn" class="flex-1 rounded-md border border-gray-300 px-3 py-2 text-sm font-semibold text-gray-700 hover:bg-gray-50" style="display:none;">
                            Retake
                        </button>
                    </div>
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

    <script defer src="https://cdn.jsdelivr.net/npm/face-api.js@0.22.2/dist/face-api.min.js"></script>
    <script>
        document.addEventListener('DOMContentLoaded', function () {
            const video = document.getElementById('liveFeed');
            const canvas = document.getElementById('captureCanvas');
            const captureBtn = document.getElementById('captureBtn');
            const retakeBtn = document.getElementById('retakeBtn');
            const guideOverlay = document.getElementById('guideOverlay');
            const guideText = document.getElementById('guideText');
            const modelStatus = document.getElementById('modelStatus');
            const resultMessage = document.getElementById('resultMessage');
            const errorEl = document.getElementById('cameraError');

            let modelReady = false;

            function waitForFaceApi() {
                if (typeof faceapi === 'undefined') {
                    setTimeout(waitForFaceApi, 100);
                    return;
                }
                faceapi.nets.tinyFaceDetector.loadFromUri('https://cdn.jsdelivr.net/gh/justadudewhohacks/face-api.js/weights')
                    .then(() => {
                        modelReady = true;
                        modelStatus.textContent = 'Face detection ready.';
                        captureBtn.disabled = false;
                    })
                    .catch(() => {
                        modelStatus.textContent = 'Face detection could not load (offline?). Capture will still work, without validation.';
                        captureBtn.disabled = false;
                    });
            }
            waitForFaceApi();

            navigator.mediaDevices.getUserMedia({ video: true })
                .then(stream => { video.srcObject = stream; })
                .catch(err => { errorEl.textContent = 'Camera access denied or unavailable: ' + err.message; });

            captureBtn.addEventListener('click', async () => {
                canvas.width = video.videoWidth;
                canvas.height = video.videoHeight;
                canvas.getContext('2d').drawImage(video, 0, 0, canvas.width, canvas.height);

                video.style.display = 'none';
                canvas.style.display = 'block';
                guideOverlay.style.display = 'none';
                guideText.style.display = 'none';
                captureBtn.style.display = 'none';
                retakeBtn.style.display = 'block';
                resultMessage.textContent = 'Checking photo...';
                resultMessage.className = 'mt-2 text-sm font-medium text-gray-600';

                if (!modelReady) {
                    resultMessage.textContent = 'Photo captured (face detection unavailable).';
                    return;
                }

                try {
                    const detection = await faceapi.detectSingleFace(canvas, new faceapi.TinyFaceDetectorOptions());
                    if (detection) {
                        resultMessage.textContent = '✓ Face detected.';
                        resultMessage.className = 'mt-2 text-sm font-medium text-green-700';
                    } else {
                        resultMessage.textContent = '✗ No face detected. Please look straight at the camera and try again.';
                        resultMessage.className = 'mt-2 text-sm font-medium text-red-600';
                    }
                } catch (e) {
                    resultMessage.textContent = 'Could not run face detection.';
                    resultMessage.className = 'mt-2 text-sm font-medium text-gray-600';
                }
            });

            retakeBtn.addEventListener('click', () => {
                video.style.display = 'block';
                canvas.style.display = 'none';
                guideOverlay.style.display = 'flex';
                guideText.style.display = 'block';
                captureBtn.style.display = 'block';
                retakeBtn.style.display = 'none';
                resultMessage.textContent = '';
            });
        });
    </script>
</x-app-layout>