{{-- Usage: @include('kiosk.partials.verification-capture', ['action' => 'borrow']) --}}

<div class="rounded-2xl bg-paper/8 p-5">
    <p class="text-sm font-semibold text-paper">Identity Verification</p>
    <p class="mt-1 text-xs text-paper/60">Please look straight at the camera. This is required to continue.</p>

    <div class="relative mt-3 rounded-md overflow-hidden bg-black aspect-[4/3]">
        <video id="verifyFeed" autoplay playsinline muted class="w-full h-full object-cover"></video>
        <canvas id="verifyCanvas" class="w-full h-full object-cover" style="display:none;"></canvas>

        <div id="verifyGuide" class="pointer-events-none absolute inset-0 flex items-center justify-center">
            <div class="border-4 border-dashed rounded-full" style="width:55%; aspect-ratio:3/4; border-color:#facc15;"></div>
        </div>
    </div>

    <p id="verifyStatus" class="mt-2 text-sm font-medium text-paper/80">Loading camera...</p>
    <input type="hidden" name="verification_capture_id" id="verification_capture_id" @if(!empty($confirmFormId)) form="{{ $confirmFormId }}" @endif>
</div>

<script defer src="https://cdn.jsdelivr.net/npm/face-api.js@0.22.2/dist/face-api.min.js"></script>
<script>
    document.addEventListener('DOMContentLoaded', function () {
        const video = document.getElementById('verifyFeed');
        const canvas = document.getElementById('verifyCanvas');
        const guide = document.getElementById('verifyGuide');
        const status = document.getElementById('verifyStatus');
        const hiddenInput = document.getElementById('verification_capture_id');

        let modelReady = false;
        let captured = false;
        let pollTimer = null;
        let elapsedMs = 0;

        function waitForFaceApi() {
            if (typeof faceapi === 'undefined') {
                setTimeout(waitForFaceApi, 100);
                return;
            }
            faceapi.nets.tinyFaceDetector.loadFromUri('https://cdn.jsdelivr.net/gh/justadudewhohacks/face-api.js/weights')
                .then(() => {
                    modelReady = true;
                    status.textContent = 'Position your face in the oval.';
                    startPolling();
                })
                .catch(() => {
                    status.textContent = 'Face detection unavailable. Please contact library staff.';
                });
        }
        waitForFaceApi();

        navigator.mediaDevices.getUserMedia({ video: true })
            .then(stream => { video.srcObject = stream; })
            .catch(err => { status.textContent = 'Camera access denied: ' + err.message; });

        function startPolling() {
            pollTimer = setInterval(async () => {
                if (captured || !modelReady || video.readyState < 2) return;

                elapsedMs += 800;
                if (elapsedMs > 15000) {
                    status.textContent = 'Still waiting - please center your face in the oval.';
                }

                try {
                    const detection = await faceapi.detectSingleFace(video, new faceapi.TinyFaceDetectorOptions());
                    if (detection) {
                        captured = true;
                        clearInterval(pollTimer);
                        await captureAndSave(true);
                    }
                } catch (e) {
                    // ignore a single failed detection tick, keep polling
                }
            }, 800);
        }

        async function captureAndSave(faceDetected) {
            canvas.width = video.videoWidth;
            canvas.height = video.videoHeight;
            canvas.getContext('2d').drawImage(video, 0, 0, canvas.width, canvas.height);

            video.style.display = 'none';
            canvas.style.display = 'block';
            guide.style.display = 'none';
            status.textContent = 'Saving verification photo...';

            const dataUrl = canvas.toDataURL('image/jpeg', 0.85);

            try {
                const response = await fetch("{{ route('kiosk.verification.capture', ['action' => $action]) }}", {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content,
                        'Accept': 'application/json',
                    },
                    body: JSON.stringify({ image: dataUrl, face_detected: faceDetected }),
                });
                const result = await response.json();

                if (response.ok && result.ok) {
                    hiddenInput.value = result.capture_id;
                    status.textContent = '✓ Identity photo captured.';
                    status.className = 'mt-2 text-sm font-medium text-green-400';
                    document.dispatchEvent(new CustomEvent('verification-captured', { detail: { captureId: result.capture_id } }));
                    const confirmBtn = document.getElementById('confirmSubmitBtn');
                    if (confirmBtn) {
                        confirmBtn.disabled = false;
                        confirmBtn.style.opacity = '1';
                    }
                } else {
                    status.textContent = 'Could not save photo. Please contact library staff.';
                }
            } catch (e) {
                status.textContent = 'Could not save photo. Please contact library staff.';
            }
        }
    });
</script>