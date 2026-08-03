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

// Check for an existing payment record
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

// ============================================
// SINGLE FUNCTION TO RENDER PAYMENT UI
// ============================================
function renderPaymentUI($paymentStatus, $imagePath, $vehicleName, $totalPrice, $bookingId) {
    // Determine button text
    $buttonText = 'Submit Payment Proof';
    if ($paymentStatus === 'disapproved') {
        $buttonText = 'Submit New Proof';
    } elseif ($paymentStatus === 'pending') {
        $buttonText = 'Upload Proof';
    }
    
    ob_start();
    ?>
    <div class="payment-container">
        <?php if ($paymentStatus === 'verified'): ?>
            <!-- ======================================== -->
            <!-- STATUS: VERIFIED - FULLY LOCKED         -->
            <!-- ======================================== -->
            <h2>Payment Verified Successfully</h2>
            <div class="payment-box">
                <div style="background:#d4edda;color:#155724;padding:15px;border-radius:5px;margin-bottom:20px;border:1px solid #c3e6cb;">
                    ✅ Payment Verified Successfully.
                </div>
                <img src="<?= htmlspecialchars($imagePath) ?>" class="payment-image" onerror="this.src='../uploads/vehicles/default-car.svg'">
                <p><strong>Vehicle:</strong> <?= htmlspecialchars($vehicleName) ?></p>
                <p><strong>Total:</strong> ₱<?= htmlspecialchars((string) $totalPrice) ?></p>
            </div>
            <!-- NO FORMS SHOWN FOR VERIFIED -->

        <?php else: ?>
            <!-- ======================================== -->
            <!-- STATUS: NULL, PENDING, or DISAPPROVED    -->
            <!-- ======================================== -->
            <h2>Payment</h2>
            
            <?php if ($paymentStatus === 'pending'): ?>
                <!-- PENDING: Show message and keep upload form -->
                <div style="background:#fff3cd;color:#856404;padding:15px;border-radius:5px;margin-bottom:20px;border:1px solid #ffeeba;">
                    ⏳ Payment is still pending. Please upload payment proof or wait for verification.
                </div>
            <?php elseif ($paymentStatus === 'disapproved'): ?>
                <!-- DISAPPROVED: Show message and allow re-upload -->
                <div style="background:#f8d7da;color:#721c24;padding:15px;border-radius:5px;margin-bottom:20px;border:1px solid #f5c6cb;">
                    ⚠️ Payment Disapproved. Please check your payment and try again.
                </div>
            <?php endif; ?>
            
            <!-- Vehicle Details -->
            <div class="payment-box">
                <img src="<?= htmlspecialchars($imagePath) ?>" class="payment-image" onerror="this.src='../uploads/vehicles/default-car.svg'">
                <p><strong>Vehicle:</strong> <?= htmlspecialchars($vehicleName) ?></p>
                <p><strong>Total:</strong> ₱<?= htmlspecialchars((string) $totalPrice) ?></p>
            </div>
            
            <!-- ======================================== -->
            <!-- PAYMENT BUTTONS - Only show for NULL     -->
            <!-- ======================================== -->
            <?php if ($paymentStatus === null): ?>
                <div class="payment-form">
                    <button class="btn" type="button" id="payWithXendit">Pay with Xendit</button>
                </div>
            <?php endif; ?>
            
            <!-- ======================================== -->
            <!-- PROOF UPLOAD FORM - Only after a payment attempt exists -->
            <!-- (i.e. NOT shown together with the "Pay with Xendit" button) -->
            <!-- ======================================== -->
            <?php if ($paymentStatus !== null): ?>
            <div class="payment-form" style="margin-top:20px;border-top:1px solid #ddd;padding-top:20px;">
                <p style="margin-bottom:10px;color:#666;font-weight:bold;">
                    <?php if ($paymentStatus === 'pending'): ?>
                        📤 Upload Payment Proof (Required for verification)
                    <?php elseif ($paymentStatus === 'disapproved'): ?>
                        📤 Upload New Payment Proof
                    <?php endif; ?>
                </p>
                <form id="manualPaymentForm" enctype="multipart/form-data">
                    <input type="file" name="proof_image" id="proof_image" accept="image/jpeg,image/png,image/webp" required>
                    <button class="btn" type="submit" id="submitPaymentBtn" style="margin-top:10px;" data-default-text="<?= $buttonText ?>">
                        <?= $buttonText ?>
                    </button>
                </form>
                <!-- Status message area for upload feedback -->
                <div id="uploadStatusMessage" style="margin-top:10px;"></div>
            </div>
            <?php endif; ?>
            
        <?php endif; ?>
        
        <!-- ======================================== -->
        <!-- RETURN BUTTON - Show in ALL states       -->
        <!-- ======================================== -->
        <div style="margin-top:30px;padding-top:20px;border-top:1px solid #eee;text-align:center;">
            <a href="browse.php" class="btn-return">← Back to Browse</a>
        </div>
        
    </div>
    <?php
    return ob_get_clean();
}

// ============================================
// AJAX endpoint
// ============================================
$ajax = isset($_GET['ajax']) && $_GET['ajax'] === '1';
if ($ajax && ($_GET['section'] ?? '') === 'payment-status') {
    echo renderPaymentUI($paymentStatus, $imagePath, $data['vehicle_name'], $data['total_price'], $booking_id);
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

    <div id="renter-payment-status" 
         data-live-refresh="paid.php?ajax=1&section=payment-status&booking_id=<?= (int) $booking_id ?>" 
         data-live-target="#renter-payment-status">
        <?= renderPaymentUI($paymentStatus, $imagePath, $data['vehicle_name'], $data['total_price'], $booking_id) ?>
    </div>

    <script>
    // Track if upload is in progress or completed
    var uploadInProgress = false;
    var uploadCompleted = false;

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
                // Reset upload state after refresh
                uploadInProgress = false;
                uploadCompleted = false;
            })
            .catch(function(error) {
                console.log('Payment status refresh failed:', error);
            });
    }

    // Helper function to show upload status messages
    function showUploadStatus(message, type = 'info', permanent = false) {
        const statusDiv = document.getElementById('uploadStatusMessage');
        if (!statusDiv) return;
        
        let bgColor, textColor, borderColor;
        if (type === 'success') {
            bgColor = '#d4edda';
            textColor = '#155724';
            borderColor = '#c3e6cb';
        } else if (type === 'error') {
            bgColor = '#f8d7da';
            textColor = '#721c24';
            borderColor = '#f5c6cb';
        } else {
            bgColor = '#d1ecf1';
            textColor = '#0c5460';
            borderColor = '#bee5eb';
        }
        
        statusDiv.style.cssText = 'background:' + bgColor + ';color:' + textColor + ';padding:12px;border-radius:5px;border:1px solid ' + borderColor + ';';
        statusDiv.textContent = message;
        statusDiv.style.display = 'block';
        
        // If not permanent, auto-hide after 10 seconds
        if (!permanent) {
            setTimeout(function() {
                if (statusDiv) {
                    statusDiv.style.opacity = '0';
                    statusDiv.style.transition = 'opacity 0.5s';
                    setTimeout(function() {
                        if (statusDiv) {
                            statusDiv.style.display = 'none';
                            statusDiv.style.opacity = '1';
                        }
                    }, 500);
                }
            }, 10000);
        }
    }

    // XENDIT BUTTON (Event Delegation)
    document.addEventListener('click', async function(e) {
        if (e.target && e.target.id === 'payWithXendit') {
            const button = e.target;
            
            // Open new tab immediately
            const xenditWindow = window.open('', '_blank');
            
            if (!xenditWindow) {
                showUploadStatus('Popup blocked by browser. Please allow popups for this site.', 'error');
                return;
            }

            button.disabled = true;
            button.textContent = 'Connecting to Xendit...';

            const formData = new FormData();
            formData.append('booking_id', '<?= (int) $booking_id ?>');

            try {
                const response = await fetch('payment_gateway.php', {
                    method: 'POST',
                    body: formData
                });

                const text = await response.text();
                console.log('Xendit Response:', text);
                
                const result = JSON.parse(text);

                if (result.success && result.checkout_url) {
                    xenditWindow.location.href = result.checkout_url;
                    showUploadStatus('Redirecting to Xendit payment gateway...', 'info');
                } else {
                    xenditWindow.close();
                    showUploadStatus(result.message || 'Unable to create Xendit payment.', 'error');
                }
            } catch(error) {
                console.error('Xendit Error:', error);
                xenditWindow.close();
                showUploadStatus('Xendit payment connection failed. Please try again.', 'error');
            }

            button.disabled = false;
            button.textContent = 'Pay with Xendit';
        }
    });

    // MANUAL PAYMENT UPLOAD
    document.addEventListener('submit', async function(e) {
        if (e.target && e.target.id === 'manualPaymentForm') {
            e.preventDefault();
            
            // Prevent multiple submissions
            if (uploadInProgress || uploadCompleted) {
                showUploadStatus('Upload already in progress or completed. Please wait.', 'info');
                return;
            }
            
            const form = e.target;
            const submitBtn = document.getElementById('submitPaymentBtn');
            const defaultText = submitBtn.getAttribute('data-default-text') || 'Submit Payment Proof';
            const fileInput = document.getElementById('proof_image');
            
            // Check if file is selected
            if (!fileInput || !fileInput.files || fileInput.files.length === 0) {
                showUploadStatus('Please select a file to upload.', 'error');
                return;
            }

            // Check file size (max 5MB)
            const fileSize = fileInput.files[0].size;
            const maxSize = 5 * 1024 * 1024; // 5MB
            if (fileSize > maxSize) {
                showUploadStatus('File is too large. Please upload an image smaller than 5MB.', 'error');
                fileInput.value = '';
                return;
            }

            // Set upload state
            uploadInProgress = true;
            submitBtn.disabled = true;
            submitBtn.textContent = 'Uploading...';
            
            // Show uploading status
            showUploadStatus('⏳ Uploading your payment proof... Please wait.', 'info', true);

            const formData = new FormData(form);
            formData.append('booking_id', '<?= (int) $booking_id ?>');

            try {
                const response = await fetch('payment_api.php', {
                    method: 'POST',
                    body: formData
                });

                const result = await response.json();

                if (result.success) {
                    // Mark as completed
                    uploadCompleted = true;
                    
                    // Show permanent success message
                    showUploadStatus('✅ ' + (result.message || 'Payment proof uploaded successfully! Waiting for admin verification.'), 'success', true);
                    
                    // Disable the button permanently
                    submitBtn.disabled = true;
                    submitBtn.textContent = '✓ Uploaded';
                    submitBtn.style.backgroundColor = '#28a745';
                    submitBtn.style.color = 'white';
                    
                    // DO NOT clear the file input - keep it showing the selected file
                    // fileInput.value = ''; // REMOVED - this was causing the "disappearing" issue
                    
                    // Refresh payment status after a delay to show pending status
                    setTimeout(function() {
                        refreshPaymentStatus();
                    }, 3000);
                    
                } else {
                    // Show error message
                    showUploadStatus(result.message || 'Unable to submit payment proof. Please try again.', 'error');
                    
                    // Reset upload state
                    uploadInProgress = false;
                    submitBtn.disabled = false;
                    submitBtn.textContent = defaultText;
                }
            } catch(error) {
                console.error('Upload Error:', error);
                showUploadStatus('Payment proof submission failed. Please try again.', 'error');
                
                // Reset upload state
                uploadInProgress = false;
                submitBtn.disabled = false;
                submitBtn.textContent = defaultText;
            }
        }
    });

    // AUTO PAYMENT STATUS REFRESH
    (function() {
        const liveTargets = document.querySelectorAll('[data-live-refresh]');

        liveTargets.forEach(function(node) {
            const refreshUrl = node.dataset.liveRefresh;
            const targetSelector = node.dataset.liveTarget || '#' + node.id;

            function refreshSection() {
                // Skip this refresh cycle if the user currently has a file chosen
                // (or an upload is in flight) - otherwise the innerHTML swap below
                // replaces the <input type="file"> and instantly clears it.
                const fileInput = document.getElementById('proof_image');
                const hasPendingSelection = fileInput && fileInput.files && fileInput.files.length > 0;
                if (uploadInProgress || (hasPendingSelection && !uploadCompleted)) {
                    return;
                }

                fetch(refreshUrl)
                    .then(function(response) {
                        return response.text();
                    })
                    .then(function(html) {
                        const targetNode = document.querySelector(targetSelector);
                        if (targetNode) {
                            targetNode.innerHTML = html;
                            // Reset upload state after refresh
                            uploadInProgress = false;
                            uploadCompleted = false;
                        }
                    })
                    .catch(function(error) {
                        console.log('Live refresh failed:', error);
                    });
            }

            // Initial refresh
            refreshSection();
            
            // Set interval for auto-refresh
            setInterval(refreshSection, 8000);
        });
    })();
    </script>

</body>
</html>