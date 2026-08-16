document.addEventListener("DOMContentLoaded", async () => {

    const video = document.getElementById("video");
    const captureBtn = document.getElementById("captureBtn");
    const statusMessage = document.getElementById("statusMessage");
    const faceImageInput = document.getElementById("faceImage");
    const faceEncodingInput = document.getElementById("faceEncoding");
    const faceForm = document.getElementById("faceForm");

    let isCapturing = false;
    let modelsReady = false;
    let cameraReady = false;
    let detectionStarted = false;

    // --- Camera opens immediately, in parallel with model loading -----
    // Previously the camera only opened AFTER all 3 models finished
    // loading. If model loading was slow or stuck, the camera never
    // even started - the video area stayed blank the whole time. Now
    // both start at the same time, so the live camera feed shows up
    // right away regardless of how long model loading takes.
    startCamera();
    loadModelsWithRetry();

    async function startCamera() {
        try {
            const stream = await navigator.mediaDevices.getUserMedia({
                video: {
                    width: 1280,
                    height: 720,
                    facingMode: "user"
                }
            });

            video.srcObject = stream;
            cameraReady = true;
            updateStatus();

        } catch (error) {
            console.error(error);
            statusMessage.textContent = "Unable to access camera.";
        }
    }

    async function loadFaceModelsOnce() {
        // BUG FIX: face-api.js runs on TensorFlow.js, which by default
        // tries to use a WebGL backend. Many Android WebView environments
        // don't support WebGL the way a full mobile browser does, which
        // causes model loading to hang forever with no error - showing
        // exactly what you saw: stuck on "Loading face models..."
        // forever, camera never opening. Forcing the CPU backend avoids
        // WebGL entirely. It's a little slower per frame, but reliable.
        if (window.faceapi && faceapi.tf && faceapi.tf.setBackend) {
            try {
                await faceapi.tf.setBackend("cpu");
                await faceapi.tf.ready();
            } catch (backendError) {
                console.warn("Could not force CPU backend:", backendError);
            }
        }

        await faceapi.nets.tinyFaceDetector.loadFromUri(
            "../face-api.js-models-master/tiny_face_detector"
        );

        await faceapi.nets.faceLandmark68Net.loadFromUri(
            "../face-api.js-models-master/face_landmark_68"
        );

        await faceapi.nets.faceRecognitionNet.loadFromUri(
            "../face-api.js-models-master/face_recognition"
        );
    }

    function withTimeout(promise, ms) {
        return Promise.race([
            promise,
            new Promise((_, reject) =>
                setTimeout(() => reject(new Error("Model loading timed out")), ms)
            )
        ]);
    }

    async function loadModelsWithRetry() {
        removeRetryButton();
        statusMessage.textContent = "Loading face detection models...";

        try {
            // 20 second cap - long enough for a slow connection, short
            // enough that the user isn't stuck staring at a frozen
            // screen with no way forward.
            await withTimeout(loadFaceModelsOnce(), 20000);
            modelsReady = true;
            updateStatus();

        } catch (error) {
            console.error(error);
            showRetryButton("Taking longer than expected. Tap Retry to try again.");
        }
    }

    function showRetryButton(message) {
        statusMessage.textContent = message;

        let retryBtn = document.getElementById("modelRetryBtn");
        if (retryBtn) {
            return;
        }

        retryBtn = document.createElement("button");
        retryBtn.id = "modelRetryBtn";
        retryBtn.type = "button";
        retryBtn.textContent = "Retry";
        retryBtn.style.marginTop = "10px";
        retryBtn.style.display = "block";

        statusMessage.insertAdjacentElement("afterend", retryBtn);

        retryBtn.addEventListener("click", () => {
            loadModelsWithRetry();
        });
    }

    function removeRetryButton() {
        const retryBtn = document.getElementById("modelRetryBtn");
        if (retryBtn) {
            retryBtn.remove();
        }
    }

    function updateStatus() {
        if (modelsReady && cameraReady && !detectionStarted) {
            statusMessage.textContent = "Position your face in the frame.";
        } else if (modelsReady && !cameraReady) {
            statusMessage.textContent = "Waiting for camera access...";
        } else if (!modelsReady && cameraReady) {
            statusMessage.textContent = "Camera ready. Loading face detection...";
        }
    }

    video.addEventListener("playing", () => {
        if (detectionStarted) {
            return;
        }
        detectionStarted = true;

        setInterval(async () => {

            if (!modelsReady) {
                return;
            }

            const detection = await faceapi
                .detectSingleFace(
                    video,
                    new faceapi.TinyFaceDetectorOptions({
                        inputSize: 416,
                        scoreThreshold: 0.4
                    })
                )
                .withFaceLandmarks();

            if (!detection) {
                captureBtn.disabled = true;
                statusMessage.textContent = "No face detected.";
                return;
            }

            const box = detection.detection.box;
            const faceWidth = box.width;
            const faceHeight = box.height;

            if (faceWidth < 80 || faceHeight < 80) {
                captureBtn.disabled = true;
                statusMessage.textContent = "Move closer to the camera.";
                return;
            }

            const landmarks = detection.landmarks;
            const leftEye = landmarks.getLeftEye();
            const rightEye = landmarks.getRightEye();

            if (leftEye.length === 0 || rightEye.length === 0) {
                captureBtn.disabled = true;
                statusMessage.textContent = "Remove cap or glasses.";
                return;
            }

            captureBtn.disabled = false;
            statusMessage.textContent = "Face detected. Ready to capture.";

        }, 500);
    });

    captureBtn.addEventListener("click", async () => {

        if (isCapturing) {
            return;
        }
        isCapturing = true;

        captureBtn.disabled = true;
        statusMessage.textContent = "Capturing face...";

        const detection = await faceapi
            .detectSingleFace(
                video,
                new faceapi.TinyFaceDetectorOptions({
                    inputSize: 416,
                    scoreThreshold: 0.4
                })
            )
            .withFaceLandmarks()
            .withFaceDescriptor();

        if (!detection) {
            captureBtn.disabled = false;
            statusMessage.textContent = "Face capture failed.";
            isCapturing = false;
            return;
        }

        const canvas = document.createElement("canvas");
        canvas.width = video.videoWidth;
        canvas.height = video.videoHeight;

        const ctx = canvas.getContext("2d");
        ctx.drawImage(video, 0, 0, canvas.width, canvas.height);

        const imageData = canvas.toDataURL("image/png");
        const descriptor = Array.from(detection.descriptor);

        faceImageInput.value = imageData;
        faceEncodingInput.value = JSON.stringify(descriptor);

        statusMessage.textContent = "Face captured successfully. Saving...";

        setTimeout(() => {
            faceForm.submit();
        }, 1000);

    });

});