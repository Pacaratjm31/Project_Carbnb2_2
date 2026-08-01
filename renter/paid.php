<?php
include '../database/db.php';
include __DIR__ . '/../helpers/duplicate_functions.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

$conn = $conn ?? $GLOBALS['conn'] ?? $GLOBALS['pdo'] ?? null;

// Check login
if (!isset($_SESSION['user_id'])) {
    header('Location: ../auth/login.php');
    exit;
}

$user_id = (int) $_SESSION['user_id'];

// Get renter account state
$stmt = $conn->prepare("
    SELECT id, full_name, status, disapproval_reason
    FROM users
    WHERE id = ?
    AND is_deleted = 0
");
$stmt->execute([$user_id]);
$renter = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$renter) {
    session_destroy();
    header('Location: ../auth/login.php');
    exit;
}

// Check renter approval
if (($renter['status'] ?? 'pending') !== 'approved') {
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Payment Restricted | Carbnb</title>
    <link rel="stylesheet" href="css/renter_style.css?v=2">
</head>
<body>

    <div class="payment-container">
        <h2>Payment Restricted</h2>

        <div class="payment-box">
            <h3>
                <?= htmlspecialchars(
                    $renter['status'] === 'disapproved'
                        ? 'Account Disapproved'
                        : 'Pending Admin Approval'
                ) ?>
            </h3>

            <p>
                <?= htmlspecialchars(
                    $renter['status'] === 'disapproved'
                        ? ($renter['disapproval_reason'] ?? 'Your account was disapproved.')
                        : 'Your account is waiting for admin approval. Payment is disabled.'
                ) ?>
            </p>

            <a href="browse.php" class="btn-return">← Back to Browse</a>
        </div>
    </div>

</body>
</html>
<?php
    exit;
}

// Check booking ID
if (!isset($_GET['booking_id'])) {
    die('Invalid request.');
}

$booking_id = (int) $_GET['booking_id'];

// Get booking details
$stmt = $conn->prepare("
    SELECT 
        b.id,
        b.total_price,
        b.status,
        v.name AS vehicle_name,
        v.image AS car_image
    FROM bookings b
    JOIN vehicles v ON b.vehicle_id = v.id
    WHERE b.id = ?
    AND b.renter_id = ?
");
$stmt->execute([$booking_id, $user_id]);
$data = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$data) {
    die('Booking not found.');
}

// Check for an existing payment record and its admin-approval status
$paymentStmt = $conn->prepare("
    SELECT status
    FROM payments
    WHERE booking_id = ?
");
$paymentStmt->execute([$booking_id]);
$existingPayment = $paymentStmt->fetch(PDO::FETCH_ASSOC);

$paymentStatus = $existingPayment['status'] ?? null; // null, 'pending', 'verified', or 'disapproved'

// Build vehicle image path
function build_vehicle_image_path($value): string
{
    if (empty($value)) {
        return '../uploads/vehicles/default-car.svg';
    }

    if (preg_match('#^https?://#i', $value)) {
        return $value;
    }

    if (preg_match('#^uploads/#', $value)) {
        return '../' . $value;
    }

    if (strpos($value, '../') === 0 || strpos($value, '/') === 0) {
        return $value;
    }

    return '../uploads/vehicles/' . basename($value);
}

$imagePath = build_vehicle_image_path($data['car_image'] ?? '');

$ajax = isset($_GET['ajax']) && $_GET['ajax'] === '1';
if ($ajax && ($_GET['section'] ?? '') === 'payment-status') {
    if ($paymentStatus === 'verified' || $paymentStatus === 'pending') {
        // Intentionally left blank: no message, no payment form, no upload form.
    } else {
        if ($paymentStatus === 'disapproved') {
            echo '<div class="payment-box">';
            echo '<h3>Payment Disapproved</h3>';
            echo '<p>Your previous payment was disapproved by the admin. Please try paying again below.</p>';
            echo '</div>';
        }

        echo '<div class="payment-box">';
        echo '<h3>Pay with Xendit</h3>';
        echo '<p>Continue your secure automatic payment through Xendit.</p>';
        echo '</div>';

        echo '<div class="payment-form">';
        echo '<button class="btn" type="button" id="payWithXendit">Pay with Xendit</button>';
        echo '</div>';

        echo '<div class="payment-form">';
        echo '<p>Send your payment receipt here for admin verification.</p>';
        echo '<form id="manualPaymentForm" enctype="multipart/form-data">';
        echo '<input type="file" name="proof_image" id="proof_image" accept="image/jpeg,image/png,image/webp" required>';
        echo '<button class="btn" type="submit" id="submitPaymentBtn" style="margin-top:10px;">Submit Payment</button>';
        echo '</form>';
        echo '</div>';

        echo '<div class="payment-form">';
        echo '<a href="javascript:history.back()" class="btn-return">← Return</a>';
        echo '</div>';
    }
    exit;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Payment | Carbnb</title>
    <link rel="stylesheet" href="css/renter_style.css?v=2">
</head>
<body>

    <div class="payment-container">
        <h2>Payment</h2>

        <div class="payment-box">
            <img src="<?= htmlspecialchars($imagePath) ?>"
                 class="payment-image"
                 onerror="this.src='../uploads/vehicles/default-car.svg'">

            <p><strong>Vehicle:</strong> <?= htmlspecialchars($data['vehicle_name']) ?></p>
            <p><strong>Total:</strong> ₱<?= htmlspecialchars((string) $data['total_price']) ?></p>
        </div>

        <div id="renter-payment-status" data-live-refresh="paid.php?ajax=1&section=payment-status&booking_id=<?= (int) $booking_id ?>" data-live-target="#renter-payment-status">
            <?php if ($paymentStatus === 'verified' || $paymentStatus === 'pending'): ?>

                <?php /* Intentionally left blank: no message, no payment form, no upload form. */ ?>

            <?php else: ?>

                <?php if ($paymentStatus === 'disapproved'): ?>
                <div class="payment-box">
                    <h3>Payment Disapproved</h3>
                    <p>Your previous payment was disapproved by the admin. Please try paying again below.</p>
                </div>
                <?php endif; ?>

                <div class="payment-box">
                    <h3>Pay with Xendit</h3>
                    <p>Continue your secure automatic payment through Xendit.</p>
                </div>

                <div class="payment-form">
                    <button class="btn" type="button" id="payWithXendit">Pay with Xendit</button>
                </div>

                <div class="payment-form">
                    <p>Send your payment receipt here for admin verification.</p>
                    <form id="manualPaymentForm" enctype="multipart/form-data">
                        <input type="file" name="proof_image" id="proof_image" accept="image/jpeg,image/png,image/webp" required>

                        <button class="btn" type="submit" id="submitPaymentBtn" style="margin-top:10px;">Submit Payment</button>
                    </form>
                </div>

                <div class="payment-form">
                    <a href="javascript:history.back()" class="btn-return">← Return</a>
                </div>

            <?php endif; ?>
        </div>

    </div>

    <script>

function refreshPaymentStatus() {

    const paymentStatusNode = document.getElementById('renter-payment-status');

    if (!paymentStatusNode || !paymentStatusNode.dataset.liveRefresh) {
        return;
    }


    fetch(paymentStatusNode.dataset.liveRefresh)

        .then(function(response) {
            return response.text();
        })

        .then(function(html) {

            paymentStatusNode.innerHTML = html;

        })

        .catch(function(error) {

            console.log(
                'Payment status refresh failed:',
                error
            );

        });

}




// XENDIT BUTTON (Event Delegation)
// Works even after AJAX refresh

document.addEventListener(
'click',
async function(e) {


    if (
        e.target &&
        e.target.id === 'payWithXendit'
    ) {


        const button = e.target;



        // Open new tab immediately
        const xenditWindow =
            window.open(
                '',
                '_blank'
            );



        if (!xenditWindow) {


            alert(
                'Please allow popups for this website.'
            );


            return;

        }



        button.disabled = true;

        button.textContent =
            'Connecting to Xendit...';



        const formData =
            new FormData();



        formData.append(
            'booking_id',
            '<?= (int) $booking_id ?>'
        );




        try {


            const response =
                await fetch(
                    'payment_gateway.php',
                    {
                        method: 'POST',
                        body: formData
                    }
                );



            const text =
                await response.text();



            console.log(
                'Xendit Response:',
                text
            );



            const result =
                JSON.parse(text);




            if (
                result.success &&
                result.checkout_url
            ) {


                xenditWindow.location.href =
                    result.checkout_url;



            } else {


                xenditWindow.close();



                alert(
                    result.message ||
                    'Unable to create Xendit payment.'
                );


            }




        } catch(error) {


            console.error(
                'Xendit Error:',
                error
            );



            xenditWindow.close();



            alert(
                'Xendit payment connection failed.'
            );



        }



        button.disabled = false;

        button.textContent =
            'Pay with Xendit';



    }


});





// MANUAL PAYMENT UPLOAD

document.addEventListener(
'submit',
async function(e) {


    if (
        e.target &&
        e.target.id === 'manualPaymentForm'
    ) {


        e.preventDefault();



        const form =
            e.target;



        const submitBtn =
            document.getElementById(
                'submitPaymentBtn'
            );



        submitBtn.disabled = true;

        submitBtn.textContent =
            'Submitting...';




        const formData =
            new FormData(form);



        formData.append(
            'booking_id',
            '<?= (int) $booking_id ?>'
        );




        try {


            const response =
                await fetch(
                    'payment_api.php',
                    {
                        method: 'POST',
                        body: formData
                    }
                );



            const result =
                await response.json();




            if(result.success) {


                alert(
                    result.message ||
                    'Payment submitted.'
                );


                refreshPaymentStatus();



            } else {


                alert(
                    result.message ||
                    'Unable to submit payment.'
                );


            }



        } catch(error) {


            console.error(error);


            alert(
                'Payment submission failed.'
            );


        }




        submitBtn.disabled = false;

        submitBtn.textContent =
            'Submit Payment';



    }


});






// AUTO PAYMENT STATUS REFRESH

(function(){


    const liveTargets =
        document.querySelectorAll(
            '[data-live-refresh]'
        );



    liveTargets.forEach(
    function(node){



        const refreshUrl =
            node.dataset.liveRefresh;



        const targetSelector =
            node.dataset.liveTarget ||
            '#' + node.id;




        function refreshSection(){



            fetch(refreshUrl)


            .then(function(response){

                return response.text();

            })


            .then(function(html){


                const targetNode =
                    document.querySelector(
                        targetSelector
                    );



                if(targetNode){

                    targetNode.innerHTML =
                        html;

                }



            })


            .catch(function(error){


                console.log(
                    'Live refresh failed:',
                    error
                );


            });


        }




        refreshSection();



        setInterval(
            refreshSection,
            8000
        );



    });



})();


</script>

</body>
</html>
