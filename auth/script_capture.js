document.addEventListener(
    "DOMContentLoaded",
    async () => {

    const video =
        document.getElementById("video");

    const captureBtn =
        document.getElementById("captureBtn");

    const statusMessage =
        document.getElementById("statusMessage");

    const faceImageInput =
        document.getElementById("faceImage");

    const faceEncodingInput =
        document.getElementById("faceEncoding");

    const faceForm =
        document.getElementById("faceForm");

    let isCapturing = false;

    // Start the camera right away, in parallel with model loading below,
    // instead of waiting for models to finish first. This is what makes
    // the camera preview open immediately after permission is granted.
    startCamera();

    try {

        statusMessage.textContent =
            "Loading face models...";

        // Force the CPU backend before loading models. TensorFlow.js
        // (which face-api.js runs on) defaults to a WebGL backend that
        // can hang forever with no error inside Android WebView - this
        // was the actual cause of getting stuck on "Loading face
        // models..." with the camera never opening.
        if (window.faceapi && faceapi.tf && faceapi.tf.setBackend) {
            try {
                await faceapi.tf.setBackend("cpu");
                await faceapi.tf.ready();
            } catch (backendError) {
                console.warn("Could not force CPU backend:", backendError);
            }
        }

        await faceapi.nets.tinyFaceDetector
            .loadFromUri(
                "../face-api.js-models-master/tiny_face_detector"
            );

        await faceapi.nets.faceLandmark68Net
            .loadFromUri(
                "../face-api.js-models-master/face_landmark_68"
            );

        await faceapi.nets.faceRecognitionNet
            .loadFromUri(
                "../face-api.js-models-master/face_recognition"
            );

        statusMessage.textContent =
            "Models loaded successfully.";

    } catch (error) {

        console.error(error);

        statusMessage.textContent =
            "Failed to load face models.";
    }

    async function startCamera() {

        try {

            const stream =
                await navigator.mediaDevices
                    .getUserMedia({
                        video: {
                            width: 1280,
                            height: 720,
                            facingMode: "user"
                        }
                    });

            video.srcObject = stream;

        } catch (error) {

            console.error(error);

            statusMessage.textContent =
                "Unable to access camera.";
        }
    }

    video.addEventListener(
        "playing",
        () => {

        setInterval(
            async () => {

            const detection =
                await faceapi
                    .detectSingleFace(
                        video,
                        new faceapi
                            .TinyFaceDetectorOptions({
                                inputSize: 416,
                                scoreThreshold: 0.4
                            })
                    )
                    .withFaceLandmarks();

            if (!detection) {

                captureBtn.disabled = true;

                statusMessage.textContent =
                    "No face detected.";

                return;
            }

            const box =
                detection.detection.box;

            const faceWidth =
                box.width;

            const faceHeight =
                box.height;

            if (
                faceWidth < 80 ||
                faceHeight < 80
            ) {

                captureBtn.disabled = true;

                statusMessage.textContent =
                    "Move closer to the camera.";

                return;
            }

            const landmarks =
                detection.landmarks;

            const leftEye =
                landmarks.getLeftEye();

            const rightEye =
                landmarks.getRightEye();

            if (
                leftEye.length === 0 ||
                rightEye.length === 0
            ) {

                captureBtn.disabled = true;

                statusMessage.textContent =
                    "Remove cap or glasses.";

                return;
            }

            captureBtn.disabled = false;

            statusMessage.textContent =
                "Face detected. Ready to capture.";

        }, 500);

    });

    captureBtn.addEventListener(
        "click",
        async () => {

        if (isCapturing) return;
        isCapturing = true;

        captureBtn.disabled = true;

        statusMessage.textContent =
            "Capturing face...";

        const detection =
            await faceapi
                .detectSingleFace(
                    video,
                    new faceapi
                        .TinyFaceDetectorOptions({
                            inputSize: 416,
                            scoreThreshold: 0.4
                        })
                )
                .withFaceLandmarks()
                .withFaceDescriptor();

        if (!detection) {

            captureBtn.disabled = false;

            statusMessage.textContent =
                "Face capture failed.";

            isCapturing = false;

            return;
        }

        const canvas =
            document.createElement(
                "canvas"
            );

        canvas.width =
            video.videoWidth;

        canvas.height =
            video.videoHeight;

        const ctx =
            canvas.getContext("2d");

        ctx.drawImage(
            video,
            0,
            0,
            canvas.width,
            canvas.height
        );

        const imageData =
            canvas.toDataURL(
                "image/png"
            );

        const descriptor =
            Array.from(
                detection.descriptor
            );

        faceImageInput.value =
            imageData;

        faceEncodingInput.value =
            JSON.stringify(
                descriptor
            );

        statusMessage.textContent =
            "Face captured successfully. Saving...";

        setTimeout(
            () => {

            faceForm.submit();

        }, 1000);

    });

});