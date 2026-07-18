document.addEventListener("DOMContentLoaded", async () => {

const video = document.getElementById("video");
const verifyBtn = document.getElementById("verifyBtn");
const statusMessage = document.getElementById("statusMessage");
const registeredFaceDescriptor = window.registeredFaceDescriptor || [];
const registeredFaceImage = window.registeredFaceImage || "";

let faceDetected = false;

if (!registeredFaceDescriptor.length) {
    statusMessage.textContent = "No registered face template found.";
    verifyBtn.disabled = true;
}

try {
    statusMessage.textContent = "Loading face models...";

    await faceapi.nets.tinyFaceDetector.loadFromUri("../face-api.js-models-master/tiny_face_detector");
    await faceapi.nets.faceLandmark68Net.loadFromUri("../face-api.js-models-master/face_landmark_68");
    await faceapi.nets.faceRecognitionNet.loadFromUri("../face-api.js-models-master/face_recognition");

    statusMessage.textContent = "Models loaded successfully.";
    await startCamera();
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
            return;
        }

        if (!registeredFaceDescriptor.length) {
            statusMessage.textContent = "No registered face template available.";
            verifyBtn.disabled = false;
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
            statusMessage.textContent = "Face verified successfully.";
            const redirectUrl = result.redirect || "../renter/browse.php";
            setTimeout(() => {
                window.location.href = redirectUrl;
            }, 1000);
        } else {
            statusMessage.textContent = result.message || "Face does not match the registered profile.";
            verifyBtn.disabled = false;
        }
    } catch (error) {
        console.error(error);
        statusMessage.textContent = "Verification error.";
        verifyBtn.disabled = false;
    }
});
});
