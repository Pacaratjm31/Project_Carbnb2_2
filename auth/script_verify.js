(async () => {

const video = document.getElementById("video");
const verifyBtn = document.getElementById("verifyBtn");
const statusMessage = document.getElementById("statusMessage");
const registeredFaceDescriptor = window.registeredFaceDescriptor || [];
const registeredFaceImage = window.registeredFaceImage || "";

let faceDetected = false;
let isVerifying = false;

// BUG FIX: previously waited for the "DOMContentLoaded" event before
// running anything. Capacitor injects its own bridge code into every
// page, which can affect exactly when that event fires inside the
// app's WebView versus a normal browser tab - if it fires before this
// listener attaches, the listener simply never runs. Since this script
// tag sits at the bottom of <body>, after video/verifyBtn/statusMessage
// already exist, the DOM is guaranteed ready by the time this runs, so
// waiting for that event isn't needed here.

if (!registeredFaceDescriptor.length) {
    statusMessage.textContent = "No registered face template found.";
    verifyBtn.disabled = true;
}

// Start the camera right away, in parallel with model loading below,
// instead of waiting for models to finish first. This is what makes
// the camera preview open immediately after permission is granted.
startCamera();

try {
    statusMessage.textContent = "Loading face models...";

    // Force the CPU backend before loading models. TensorFlow.js
    // (which face-api.js runs on) defaults to a WebGL backend that
    // can hang forever with no error inside Android WebView.
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

    statusMessage.textContent = "Models loaded successfully.";
} catch (error) {
    console.error(error);
    statusMessage.textContent = "Failed to load face models.";
}

async function startCamera() {
    try {
        const stream = await navigator.mediaDevices.getUserMedia({ video: true });
        video.srcObject = stream;
    } catch (error) {
        console.error(error);
        statusMessage.textContent = "Unable to access camera.";
    }
}

video.addEventListener("playing", () => {
    setInterval(async () => {
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

})();