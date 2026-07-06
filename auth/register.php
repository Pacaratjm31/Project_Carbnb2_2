<?php
session_start();
require_once __DIR__ . '/../database/db.php';

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    $full_name = trim($_POST['full_name'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';
    $confirm_password = $_POST['confirm_password'] ?? '';
    $role = $_POST['role'] ?? '';

    if (
        empty($full_name) ||
        empty($email) ||
        empty($password) ||
        empty($confirm_password) ||
        empty($role)
    ) {

        $error = "Please fill in all fields.";

    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {

        $error = "Invalid email address.";

    } elseif ($password !== $confirm_password) {

        $error = "Passwords do not match.";

    } else {

        try {

            $checkUser = $pdo->prepare("
                SELECT id
                FROM users
                WHERE email = ?
                LIMIT 1
            ");

            $checkUser->execute([$email]);

            if ($checkUser->fetch()) {

                $error = "Email already exists.";

            } else {

                $pdo->beginTransaction();

                $hashed_password = password_hash(
                    $password,
                    PASSWORD_DEFAULT
                );

                $insertUser = $pdo->prepare("
                    INSERT INTO users
                    (
                        full_name,
                        email,
                        password,
                        role
                    )
                    VALUES
                    (
                        ?,
                        ?,
                        ?,
                        ?
                    )
                ");

                $insertUser->execute([
                    $full_name,
                    $email,
                    $hashed_password,
                    $role
                ]);

                $user_id = $pdo->lastInsertId();

                $upload_paths = [

                    'id1' => __DIR__ . '/../uploads/renters/id1/',
                    'id2' => __DIR__ . '/../uploads/renters/id2/',
                    'proof_of_billing' => __DIR__ . '/../uploads/renters/proof_of_billing/',

                    'drivers_license' => __DIR__ . '/../uploads/owners/drivers_license/',
                    'nbi_clearance' => __DIR__ . '/../uploads/owners/nbi_clearance/',
                    'intro_video' => __DIR__ . '/../uploads/owners/intro_video/'

                ];

                foreach ($upload_paths as $folder) {

                    if (!is_dir($folder)) {

                        mkdir($folder, 0777, true);

                    }

                }

                if ($role === 'renter') {

                    $requiredDocs = [
                        'id1',
                        'id2',
                        'proof_of_billing'
                    ];

                } elseif ($role === 'owner') {

                    $requiredDocs = [
                        'drivers_license',
                        'nbi_clearance',
                        'intro_video'
                    ];

                } else {

                    throw new Exception("Invalid role selected.");

                }

                foreach ($requiredDocs as $docType) {

                    if (
                        !isset($_FILES[$docType]) ||
                        $_FILES[$docType]['error'] !== UPLOAD_ERR_OK
                    ) {

                        throw new Exception(
                            ucfirst(str_replace('_', ' ', $docType))
                            . " is required."
                        );

                    }

                    $extension = strtolower(
                        pathinfo(
                            $_FILES[$docType]['name'],
                            PATHINFO_EXTENSION
                        )
                    );

                    $filename =
                        $user_id . "_" .
                        time() . "_" .
                        $docType . "." .
                        $extension;

                    $destination =
                        $upload_paths[$docType] .
                        $filename;

                    if (
                        !move_uploaded_file(
                            $_FILES[$docType]['tmp_name'],
                            $destination
                        )
                    ) {

                        throw new Exception(
                            "Failed to upload " . $docType
                        );

                    }

                    $relativePath = str_replace(
                        __DIR__ . '/../',
                        '',
                        $destination
                    );

                    $insertDocument = $pdo->prepare("
                        INSERT INTO user_documents
                        (
                            user_id,
                            document_type,
                            file_path
                        )
                        VALUES
                        (
                            ?,
                            ?,
                            ?
                        )
                    ");

                    $insertDocument->execute([
                        $user_id,
                        $docType,
                        $relativePath
                    ]);

                }

                 $pdo->commit();

                 if ($role === 'renter') {

                     $_SESSION['face_registration_user_id'] = $user_id;
                     $_SESSION['registration_success'] = "Registration successful! Your account is pending admin approval. Please complete face registration.";

                     header("Location: face_register.php");
                     exit;

                 } else {

                     $_SESSION['registration_success'] = "Registration successful! Your owner account is pending admin approval. You will be notified once verified.";
                     header("Location: login.php?registered=1");
                     exit;

                 }

            }

        } catch (Exception $e) {

            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }

            $error = "Registration failed: " . $e->getMessage();

        }

    }

}
?>

<!DOCTYPE html>
<html lang="en">

<head>

<meta charset="UTF-8">

<meta
    name="viewport"
    content="width=device-width, initial-scale=1.0"
>

<title>Register</title>

<link
    rel="stylesheet"
    href="register_style.css"
>
<style>
    .navigate {
         display: flex;
         justify-content: center;
         align-items: center;
         height: 50px;
        color: lightskyblue;
    }
</style>

</head>

<body>

<div class="register-wrapper">

    <!-- LEFT SIDE -->

    <div class="register-left">

        <h2>Create Account</h2>

        <?php if(!empty($error)): ?>

            <div class="error-message">
                <?= htmlspecialchars($error) ?>
            </div>

        <?php endif; ?>

        <form
            method="POST"
            enctype="multipart/form-data"
            id="registerForm"
        >

            <input
                type="text"
                name="full_name"
                placeholder="Full Name"
                required
            >

            <input
                type="email"
                name="email"
                placeholder="Email Address"
                required
            >

            <input
                type="password"
                name="password"
                placeholder="Password"
                required
            >

            <input
                type="password"
                name="confirm_password"
                placeholder="Confirm Password"
                required
            >

            <select
                name="role"
                id="role"
                required
            >
                <option value="">
                    Select Role
                </option>

                <option value="renter">
                    Renter
                </option>

                <option value="owner">
                    Vehicle Owner
                </option>
            </select>

    </div>

    <!-- RIGHT SIDE -->

    <div class="register-right">

        <h2>Required Documents</h2>

        <!-- RENTER -->

        <div
            id="renterFields"
            class="document-group"
        >

            <h3>Renter Requirements</h3>

            <label>
                Valid ID #1
            </label>

            <input
                type="file"
                name="id1"
                accept=".jpg,.jpeg,.png,.pdf"
            >

            <label>
                Valid ID #2
            </label>

            <input
                type="file"
                name="id2"
                accept=".jpg,.jpeg,.png,.pdf"
            >

            <label>
                Proof of Billing
            </label>

            <input
                type="file"
                name="proof_of_billing"
                accept=".jpg,.jpeg,.png,.pdf"
            >

            <p>
                Face registration will be required
                after successful registration.
            </p>

        </div>

        <!-- OWNER -->

        <div
            id="ownerFields"
            class="document-group"
        >

            <h3>Vehicle Owner Requirements</h3>

            <label>
                Driver's License
            </label>

            <input
                type="file"
                name="drivers_license"
                accept=".jpg,.jpeg,.png,.pdf"
            >

            <label>
                NBI Clearance
            </label>

            <input
                type="file"
                name="nbi_clearance"
                accept=".jpg,.jpeg,.png,.pdf"
            >

            <p>
                <strong>Video Intro Guide:</strong>
                Please record a short introduction video where you introduce yourself,
                show your valid ID, and briefly describe the car or vehicle you will rent out.
            </p>

            <label>
                Introduction Video
            </label>

            <input
                type="file"
                name="intro_video"
                accept="video/*"
            >

            <small>

                Video Guide:
                Show your face,
                present your valid ID,
                and show proof of vehicle ownership.

            </small>

        </div>

        <button type="submit">
            Create Account
        </button>

        </form>
        <br><br>
        <a href="login.php" class="navigate">Already have account login!</a>
    </div>
</div>

<script>

document.addEventListener(
    'DOMContentLoaded',
    function() {

        const roleSelect =
            document.getElementById('role');

        const renterFields =
            document.getElementById('renterFields');

        const ownerFields =
            document.getElementById('ownerFields');

        function updateFields() {

            renterFields.style.display = 'none';
            ownerFields.style.display = 'none';

            renterFields
                .querySelectorAll('input')
                .forEach(input => {
                    input.required = false;
                });

            ownerFields
                .querySelectorAll('input')
                .forEach(input => {
                    input.required = false;
                });

            if (
                roleSelect.value === 'renter'
            ) {

                renterFields.style.display = 'block';

                renterFields
                    .querySelectorAll('input')
                    .forEach(input => {
                        input.required = true;
                    });
            }

            if (
                roleSelect.value === 'owner'
            ) {

                ownerFields.style.display = 'block';

                ownerFields
                    .querySelectorAll('input')
                    .forEach(input => {
                        input.required = true;
                    });
            }
        }

        updateFields();

        roleSelect.addEventListener(
            'change',
            updateFields
        );

    });

</script>

</body>
</html>