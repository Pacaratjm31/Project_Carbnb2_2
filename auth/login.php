<?php
session_start();
require_once __DIR__ . '/../database/db.php';

$error = '';
$success = '';
$remaining = 0;

// Check for registration success message
if (isset($_GET['registered']) && $_GET['registered'] == 1) {
    $success = "Registration successful! Your account is pending admin approval. Please log in.";
}

// Check for face registration success message
if (isset($_GET['face_registered']) && $_GET['face_registered'] == 1) {
    $success = "Face registration complete! Your account is pending admin approval. Please log in.";
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    $email = trim($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';

    if (empty($email) || empty($password)) {

        $error = "Please enter your email and password.";

    } else {

        $stmt = $pdo->prepare("
            SELECT *
            FROM users
            WHERE email = ?
            AND is_deleted = 0
        ");

        $stmt->execute([$email]);

        $user = $stmt->fetch();

        if (!$user) {

            $error = "Invalid email or password.";

        } else {

            /*
            |--------------------------------------------------------------------------
            | ACCOUNT LOCK CHECK
            |--------------------------------------------------------------------------
            */

            if (
                !empty($user['locked_until']) &&
                strtotime($user['locked_until']) > time()
            ) {

                $remaining =
                    strtotime($user['locked_until']) - time();

                $error =
                    "Account temporarily locked. Please wait.";

            } else {

                /*
                |--------------------------------------------------------------------------
                | PASSWORD CHECK
                |--------------------------------------------------------------------------
                */

                if (
                    password_verify(
                        $password,
                        $user['password']
                    )
                ) {

                    /*
                    |--------------------------------------------------------------------------
                    | RESET LOGIN ATTEMPTS
                    |--------------------------------------------------------------------------
                    */

                    $reset = $pdo->prepare("
                        UPDATE users
                        SET
                            login_attempts = 0,
                            locked_until = NULL
                        WHERE id = ?
                    ");

                    $reset->execute([
                        $user['id']
                    ]);

                    /*
                    |--------------------------------------------------------------------------
                    | DISAPPROVED ACCOUNT - BLOCK ACCESS
                    |--------------------------------------------------------------------------
                    */

                    if ($user['status'] === 'disapproved') {

                        $reason =
                            !empty($user['disapproval_reason'])
                            ? $user['disapproval_reason']
                            : 'No reason provided.';

                        $error =
                            "Account disapproved. " . $reason;

                        session_destroy();

                    } else {

                        $_SESSION['user_id']
                            = $user['id'];

                        $_SESSION['full_name']
                            = $user['full_name'];

                        $_SESSION['email']
                            = $user['email'];

                        $_SESSION['role']
                            = $user['role'];

                        $_SESSION['status']
                            = $user['status'];

                        $_SESSION['approval_status']
                            = $user['status'];

                        $_SESSION['approval_reason']
                            = $user['disapproval_reason'] ?? '';

                        /*
                        |--------------------------------------------------------------------------
                        | RENTER
                        |--------------------------------------------------------------------------
                        | Pending  -> Face Verify (limited access)
                        | Approved -> Face Verify (full access)
                        */

if (
    $user['role'] === 'renter'
) {

    // Skip face verification if user already has face registered and verified
    if (
        !empty($user['face_image']) &&
        $user['face_verified'] == 1
    ) {
        header(
            "Location: ../renter/browse.php"
        );
    } else {
        header(
            "Location: face_verify.php"
        );
    }

    exit();
}

                        /*
                        |--------------------------------------------------------------------------
                        | OWNER
                        |--------------------------------------------------------------------------
                        | Pending  -> Dashboard (limited access)
                        | Approved -> Dashboard (full access)
                        */

                        elseif (
                            $user['role'] === 'owner'
                        ) {

                            header(
                                "Location: ../owner/owner_dashboard.php"
                            );

                            exit();
                        }

                        /*
                        |--------------------------------------------------------------------------
                        | ADMIN
                        |--------------------------------------------------------------------------
                        */

                        elseif (
                            $user['role'] === 'admin'
                        ) {

                            header(
                                "Location: ../admin/admin_dashboard.php"
                            );

                            exit();
                        }

                        else {

                            session_destroy();

                            $error =
                                "Invalid account role.";
                        }
                    }

                } else {

                    /*
                    |--------------------------------------------------------------------------
                    | WRONG PASSWORD
                    |--------------------------------------------------------------------------
                    */

                    $attempts =
                        $user['login_attempts'] + 1;

                    if (
                        $attempts >= 3
                    ) {

                        $locked_until = date(
                            'Y-m-d H:i:s',
                            strtotime('+60 seconds')
                        );

                        $lock = $pdo->prepare("
                            UPDATE users
                            SET
                                login_attempts = ?,
                                locked_until = ?
                            WHERE id = ?
                        ");

                        $lock->execute([
                            $attempts,
                            $locked_until,
                            $user['id']
                        ]);

                        $remaining = 60;

                        $error =
                            "Too many failed login attempts.";

                    } else {

                        $update = $pdo->prepare("
                            UPDATE users
                            SET login_attempts = ?
                            WHERE id = ?
                        ");

                        $update->execute([
                            $attempts,
                            $user['id']
                        ]);

                        $left =
                            3 - $attempts;

                        $error =
                            "Invalid password. "
                            . $left .
                            " attempt(s) remaining.";
                    }
                }
            }
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

<title>Login</title>

<link
    rel="stylesheet"
    href="login_style.css"
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

<div class="login-container">

<h2>Login</h2>

<?php if (!empty($success)): ?>

    <div class="success-message" style="background-color: #d4edda; color: #155724; padding: 15px; border-radius: 5px; margin-bottom: 15px;">

        <?= htmlspecialchars($success) ?>

    </div>

<?php endif; ?>

<?php if (!empty($error)): ?>

    <div class="error-message">

        <?= htmlspecialchars($error) ?>

        <?php if ($remaining > 0): ?>

            <br><br>

            Try again in

            <span id="countdown">
                <?= $remaining ?>
            </span>

            second(s).

        <?php endif; ?>

    </div>

<?php endif; ?>

<form method="POST">

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

    <button type="submit">
        Login
    </button>
    <br><br>
</form>

<a href="register.php" class="navigate">Don't have account register!</a>

</div>

<script>

const countdown =
document.getElementById('countdown');

if (countdown) {

    let seconds =
        parseInt(
            countdown.textContent
        );

    const timer =
        setInterval(() => {

            seconds--;

            countdown.textContent =
                seconds;

            if (seconds <= 0) {

                clearInterval(timer);

                location.reload();
            }

        }, 1000);
}

</script>

</body>
</html>