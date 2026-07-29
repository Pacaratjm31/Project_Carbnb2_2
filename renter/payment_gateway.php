<?php
require_once '../database/db.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

header('Content-Type: application/json');

if (!isset($_SESSION['user_id'])) {
    http_response_code(401);

    echo json_encode([
        'success' => false,
        'message' => 'You must be logged in.'
    ]);

    exit;
}


$conn = $GLOBALS['conn'] ?? $GLOBALS['pdo'] ?? null;

if (!$conn) {
    echo json_encode([
        'success' => false,
        'message' => 'Database connection failed.'
    ]);

    exit;
}


$user_id = (int) $_SESSION['user_id'];

$booking_id = (int) ($_POST['booking_id'] ?? 0);


if ($booking_id <= 0) {

    echo json_encode([
        'success' => false,
        'message' => 'Invalid booking ID.'
    ]);

    exit;
}



// Get booking information
$stmt = $conn->prepare("
    SELECT 
        b.id,
        b.total_price,
        v.name AS vehicle_name
    FROM bookings b
    JOIN vehicles v 
        ON b.vehicle_id = v.id
    WHERE b.id = ?
    AND b.renter_id = ?
");

$stmt->execute([
    $booking_id,
    $user_id
]);

$booking = $stmt->fetch(PDO::FETCH_ASSOC);


if (!$booking) {

    echo json_encode([
        'success' => false,
        'message' => 'Booking not found.'
    ]);

    exit;
}



// Xendit Secret Key
$secret_key = "xnd_development_FjSOCj6vtWAm33KqtQ2P3UfneRCI3VRc6f5quqX5wUZCHq1AJYkInOUdYZFA";



// Create Xendit invoice
$payload = [

    "external_id" => "CARBNB-" . $booking_id,

    "amount" => (float) $booking['total_price'],

    "description" =>
        "Carbnb rental payment - " . $booking['vehicle_name'],

    "invoice_duration" => 86400,


    "success_redirect_url" =>
        "http://localhost/Carbnb_project2_2/renter/paid.php?booking_id=" . $booking_id,


    "failure_redirect_url" =>
        "http://localhost/Carbnb_project2_2/renter/paid.php?booking_id=" . $booking_id
];



$ch = curl_init(
    "https://api.xendit.co/v2/invoices"
);


curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);

curl_setopt($ch, CURLOPT_POST, true);

curl_setopt(
    $ch,
    CURLOPT_POSTFIELDS,
    json_encode($payload)
);


curl_setopt(
    $ch,
    CURLOPT_HTTPHEADER,
    [
        "Content-Type: application/json",
        "Authorization: Basic " . base64_encode($secret_key . ":")
    ]
);



$response = curl_exec($ch);


$curl_error = curl_error($ch);


$http_code = curl_getinfo(
    $ch,
    CURLINFO_HTTP_CODE
);


curl_close($ch);



$result = json_decode(
    $response,
    true
);



// Xendit failed
if (
    $http_code >= 400 ||
    empty($result['invoice_url'])
) {

    echo json_encode([

        'success' => false,

        'message' => 'Unable to create Xendit payment.',

        'http_code' => $http_code,

        'curl_error' => $curl_error,

        'xendit_response' => $result,

        'raw_response' => $response

    ]);

    exit;
}




// Save pending payment
try {


    $check = $conn->prepare(
        "SELECT id FROM payments WHERE booking_id = ?"
    );

    $check->execute([
        $booking_id
    ]);


    if ($check->rowCount() > 0) {


        $update = $conn->prepare("
            UPDATE payments SET

                amount = ?,

                payment_method = 'xendit',

                transaction_reference = ?,

                gateway_response = ?,

                status = 'pending'

            WHERE booking_id = ?
        ");


        $update->execute([

            $booking['total_price'],

            $result['id'],

            json_encode($result),

            $booking_id

        ]);



    } else {


        $insert = $conn->prepare("
            INSERT INTO payments
            (
                booking_id,
                amount,
                payment_method,
                transaction_reference,
                gateway_response,
                status
            )

            VALUES
            (
                ?,
                ?,
                'xendit',
                ?,
                ?,
                'pending'
            )
        ");



        $insert->execute([

            $booking_id,

            $booking['total_price'],

            $result['id'],

            json_encode($result)

        ]);

    }



} catch (PDOException $e) {


    echo json_encode([

        'success' => false,

        'message' =>
            'Payment created but database update failed.',

        'error' => $e->getMessage()

    ]);

    exit;

}




// Send checkout URL back to paid.php

echo json_encode([

    'success' => true,

    'checkout_url' =>
        $result['invoice_url'],

    'invoice_id' =>
        $result['id']

]);

?>

// Send checkout URL back to paid.php

echo json_encode([

    'success' => true,

    'checkout_url' =>
        $result['invoice_url'],

    'invoice_id' =>
        $result['id']

]);

?>