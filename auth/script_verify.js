document.addEventListener("DOMContentLoaded", async () => {

    const video = document.getElementById("video");
    const verifyBtn = document.getElementById("verifyBtn");
    const statusMessage = document.getElementById("statusMessage");
    const registeredFaceDescriptor = window.registeredFaceDescriptor || [];
    const registeredFaceImage = window.registeredFaceImage || "";

    let faceDetected = false;
    let isVerifying = false;
    let modelsReady = false;
    let cameraReady = false;
    let detectionStarted = false;

    if (!registeredFaceDescriptor.length) {
        statusMessage.textContent = "No registered face template found.";
        verifyBtn.disabled = true;
        return;
    }

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
            const stream = await navigator.mediaDevices.getUserMedia({ video: true });
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
        // causes model loading to hang forever with no error. Forcing
        // the CPU backend avoids WebGL entirely - a little slower per
        // frame, but reliable inside the app.
        if (window.faceapi && faceapi.tf && faceapi.tf.setBackend) {
            try {
                await faceapi.tf.setBackend("cpu");
                await faceapi.tf.ready();
            } catch (backendError) {
                console.warn("Could not force CPU backend:", backendError);
            }
        }

        await faceapi.nets.tinyFaceDetector.loadFromUri("../face-api.js-models-master/tiny_face_detector");
        await faceapi.nets.faceLandmark68Net.loadFromUri("../face-api.js-models-master/face_landmark_68");
        await faceapi.nets.faceRecognitionNet.loadFromUri("../face-api.js-models-master/face_recognition");
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

            const detections = await faceapi.detectAllFaces(
                video,
                new faceapi.TinyFaceDetectorOptions({ inputSize: 416, scoreThreshold: 0.4 })
            );

            if (detections.length === 1) {
                const box = detections[0].box;
                const minWidth = 80;
                const minHeight = 80;

                if (box.width >= minWidth && box.height >= minHeight) {
                    faceDetected = true;
                    verifyBtn.disabled = false;
                    statusMessage.textContent = "Face detected. Ready to verify.";
                } else {
                    faceDetected = false;
                    verifyBtn.disabled = true;
                    statusMessage.textContent = "Move closer to the camera.";
                }
            } else if (detections.length > 1) {
                faceDetected = false;
                verifyBtn.disabled = true;
                statusMessage.textContent = "Multiple faces detected.";
            } else {
                faceDetected = false;
                verifyBtn.disabled = true;
                statusMessage.textContent = "No face detected.";
            }
        }, 500);
    });

    verifyBtn.addEventListener("click", async () => {
        if (isVerifying) return;
        isVerifying = true;

        verifyBtn.disabled = true;
        statusMessage.textContent = "Verifying face...";

        try {
            const detection = await faceapi
                .detectSingleFace(
                    video,
                    new faceapi.TinyFaceDetectorOptions({ inputSize: 416, scoreThreshold: 0.4 })
                )
                .withFaceLandmarks()
                .withFaceDescriptor();

            if (!detection) {
                statusMessage.textContent = "No face detected.";
                verifyBtn.disabled = false;
                isVerifying = false;
                return;
            }

            if (!registeredFaceDescriptor.length) {
                statusMessage.textContent = "No registered face template available.";
                verifyBtn.disabled = false;
                isVerifying = false;
                return;
            }

            const response = await fetch("face_verify.php", {
                method: "POST",
                headers: {
                    "Content-Type": "application/json"
                },
                body: JSON.stringify({
                    descriptor: Array.from(detection.descriptor)
                })
            });

            const result = await response.json();

            if (result.success) {
                statusMessage.textContent = "✅ Face verified successfully! Redirecting...";
                const redirectUrl = result.redirect || "../renter/browse.php";
                setTimeout(() => {
                    window.location.href = redirectUrl;
                }, 1000);
            } else {
                statusMessage.textContent = result.message || "Face does not match the registered profile.";
                verifyBtn.disabled = false;
                isVerifying = false;
            }
        } catch (error) {
            console.error(error);
            statusMessage.textContent = "Verification error.";
            verifyBtn.disabled = false;
            isVerifying = false;
        }
    });
});