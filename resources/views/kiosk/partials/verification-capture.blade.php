{{-- Usage: @include('kiosk.partials.verification-capture', ['action' => 'borrow']) --}}

<div class="rounded-2xl bg-paper/8 p-5">
    <p class="text-sm font-semibold text-paper">Identity Verification</p>
    <p class="mt-1 text-xs text-paper/60">Please look straight at the camera. This is required to continue.</p>
    <p class="mt-1 text-xs text-paper/60">If you wear a mask, please lower it for the photo. Glasses and head coverings are fine as long as your face is visible.</p>

    <div class="relative mt-3 rounded-md overflow-hidden bg-black aspect-[4/3]">
        <video id="verifyFeed" autoplay playsinline muted class="w-full h-full object-cover"></video>
        <canvas id="verifyCanvas" class="w-full h-full object-cover" style="display:none;"></canvas>

        <div id="verifyGuide" class="pointer-events-none absolute inset-0 flex items-center justify-center">
            <div class="border-4 border-dashed rounded-full" style="width:55%; aspect-ratio:3/4; border-color:#facc15;"></div>
        </div>
    </div>

    <p id="verifyStatus" class="mt-2 text-sm font-medium text-paper/80">Loading camera...</p>

    <button type="button" id="verifyManualBtn"
            style="display:none; margin-top:10px; width:100%; min-height:48px; border:none; border-radius:12px; background:#E4A72E; color:#16233E; font-size:16px; font-weight:700; cursor:pointer; touch-action:manipulation;">
        Take photo anyway
    </button>

    <input type="hidden" name="verification_capture_id" id="verification_capture_id" @if(!empty($confirmFormId)) form="{{ $confirmFormId }}" @endif>
</div>

<script defer src="https://cdn.jsdelivr.net/npm/face-api.js@0.22.2/dist/face-api.min.js"></script>
<script>
    document.addEventListener('DOMContentLoaded', function () {
        const WEIGHTS_URL = 'https://cdn.jsdelivr.net/gh/justadudewhohacks/face-api.js/weights';
        const POLL_MS = 800;
        const EXTENDED_AFTER_MS = 4000;   // try the stronger detector after this long without a face
        const MANUAL_AFTER_MS = 12000;    // offer the "Take photo anyway" button after this long
        const LIBRARY_WAIT_MS = 8000;     // give up waiting for the face library after this long
        const USE_EXTENDED_DETECTOR = true; // set to false if the stronger detector is too slow on this PC
        const EXTENDED_MIN_FACE_SIZE = 80;  // pixels; a higher number is faster but misses smaller faces

        const video = document.getElementById('verifyFeed');
        const canvas = document.getElementById('verifyCanvas');
        const guide = document.getElementById('verifyGuide');
        const status = document.getElementById('verifyStatus');
        const hiddenInput = document.getElementById('verification_capture_id');
        const manualBtn = document.getElementById('verifyManualBtn');

        let tinyReady = false;
        let mtcnnReady = false;
        let captured = false;
        let detecting = false;
        let pollTimer = null;
        let startedAt = null;
        const libraryWaitStart = Date.now();

        function showManualButton() {
            manualBtn.style.display = 'block';
        }

        function waitForFaceApi() {
            if (typeof faceapi === 'undefined') {
                if (Date.now() - libraryWaitStart > LIBRARY_WAIT_MS) {
                    status.textContent = 'Automatic face detection is not available. Please press the button to take your photo.';
                    showManualButton();
                    return;
                }
                setTimeout(waitForFaceApi, 100);
                return;
            }

            faceapi.nets.tinyFaceDetector.loadFromUri(WEIGHTS_URL)
                .then(() => {
                    tinyReady = true;
                    status.textContent = 'Position your face in the oval.';
                    startPolling();
                    loadExtendedDetector();
                })
                .catch(() => {
                    status.textContent = 'Automatic face detection is not available. Please press the button to take your photo.';
                    showManualButton();
                });
        }
        waitForFaceApi();

        function loadExtendedDetector() {
            if (!USE_EXTENDED_DETECTOR || !faceapi.nets.mtcnn) return;

            faceapi.nets.mtcnn.loadFromUri(WEIGHTS_URL)
                .then(() => { mtcnnReady = true; })
                .catch(() => { /* the fast detector and the button still work */ });
        }

        navigator.mediaDevices.getUserMedia({ video: true })
            .then(stream => { video.srcObject = stream; })
            .catch(err => { status.textContent = 'Camera access denied: ' + err.message; });

        function startPolling() {
            if (pollTimer) clearInterval(pollTimer);

            pollTimer = setInterval(async () => {
                // Skip this tick while an earlier detection is still running
                if (captured || detecting || !tinyReady || video.readyState < 2) return;

                if (startedAt === null) startedAt = Date.now();
                const waited = Date.now() - startedAt;

                if (waited >= EXTENDED_AFTER_MS) {
                    status.textContent = 'Still looking for your face. Please lower your mask if you wear one.';
                }
                if (waited >= MANUAL_AFTER_MS) {
                    status.textContent = 'Having trouble? You can take the photo yourself with the button below.';
                    showManualButton();
                }

                detecting = true;
                try {
                    let found = await faceapi.detectSingleFace(video, new faceapi.TinyFaceDetectorOptions());

                    if (!found && mtcnnReady && waited >= EXTENDED_AFTER_MS && !captured) {
                        found = await faceapi.detectSingleFace(video, new faceapi.MtcnnOptions({ minFaceSize: EXTENDED_MIN_FACE_SIZE }));
                    }

                    // Check again after the wait: another tick, or the button, may have captured already
                    if (found && !captured) {
                        captured = true;
                        clearInterval(pollTimer);
                        manualBtn.style.display = 'none';
                        await captureAndSave(true);
                    }
                } catch (e) {
                    // ignore a single failed detection tick, keep polling
                } finally {
                    detecting = false;
                }
            }, POLL_MS);
        }

        manualBtn.addEventListener('click', async function () {
            if (captured) return;

            if (video.readyState < 2) {
                status.textContent = 'The camera is not ready yet. Please wait a moment and try again.';
                return;
            }

            captured = true;
            if (pollTimer) clearInterval(pollTimer);
            manualBtn.style.display = 'none';
            await captureAndSave(false);
        });

        // After a failed save: show the live camera again and let the patron retry with the button
        function resetAfterFailure(message) {
            status.textContent = message;
            captured = false;
            video.style.display = '';
            canvas.style.display = 'none';
            guide.style.display = '';
            showManualButton();
            if (tinyReady) startPolling();
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
                    resetAfterFailure('Could not save the photo. Please try again.');
                }
            } catch (e) {
                resetAfterFailure('Could not save the photo. Please try again.');
            }
        }
    });
</script>