<?php
// ============================================================
// CARBNB MOBILE APK INSTALLER
// ============================================================
// This script finds Carbnb_Mobile.apk on the server and
// forces the user's Android device to download it.
// ============================================================

// Do not display PHP warnings/errors inside the APK response.
ini_set('display_errors', '0');
error_reporting(0);

// Possible locations of the APK.
$possibleFiles = [
    __DIR__ . '/Carbnb_Mobile/Carbnb_Mobile.apk',
    __DIR__ . '/Carbnb_Mobile.apk',
    __DIR__ . '/mobile/Carbnb_Mobile.apk',
];

// Find the first APK that actually exists.
$apkFile = null;

foreach ($possibleFiles as $file) {
    if (is_file($file) && is_readable($file)) {
        $apkFile = $file;
        break;
    }
}

// APK was not found.
if ($apkFile === null) {
    http_response_code(404);

    header('Content-Type: text/html; charset=UTF-8');

    echo '<!DOCTYPE html>';
    echo '<html>';
    echo '<head>';
    echo '<meta name="viewport" content="width=device-width, initial-scale=1.0">';
    echo '<title>Carbnb Mobile</title>';
    echo '<style>';
    echo 'body{margin:0;background:#1e1e1e;color:#fff;font-family:Arial,sans-serif;';
    echo 'display:flex;align-items:center;justify-content:center;min-height:100vh;text-align:center;}';
    echo '.box{max-width:420px;padding:35px;}';
    echo 'h1{color:#00bfff;font-size:28px;}';
    echo 'p{color:#ccc;font-size:16px;line-height:1.6;}';
    echo '.error{color:#ff8c00;}';
    echo '</style>';
    echo '</head>';
    echo '<body>';
    echo '<div class="box">';
    echo '<h1>Carbnb Mobile</h1>';
    echo '<h2 class="error">APK Not Available</h2>';
    echo '<p>The Carbnb Mobile APK has not been uploaded to the server yet.</p>';
    echo '<p>Please try again later.</p>';
    echo '</div>';
    echo '</body>';
    echo '</html>';

    exit;
}

// Get the APK file size.
$fileSize = filesize($apkFile);

// Make sure the file has a valid size.
if ($fileSize === false || $fileSize <= 0) {
    http_response_code(500);
    header('Content-Type: text/plain; charset=UTF-8');
    echo 'Carbnb Mobile APK is invalid or empty.';
    exit;
}

// Clear any existing output buffers.
while (ob_get_level() > 0) {
    ob_end_clean();
}

// APK download headers.
header('Content-Type: application/vnd.android.package-archive');
header('Content-Disposition: attachment; filename="Carbnb_Mobile.apk"');
header('Content-Length: ' . $fileSize);
header('Content-Transfer-Encoding: binary');
header('Accept-Ranges: bytes');
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
header('Pragma: no-cache');
header('Expires: 0');

// Send APK to the user's device.
$handle = fopen($apkFile, 'rb');

if ($handle === false) {
    http_response_code(500);
    exit;
}

while (!feof($handle)) {
    echo fread($handle, 1024 * 1024);

    // Flush output so large APKs can download progressively.
    if (function_exists('ob_flush')) {
        @ob_flush();
    }

    flush();
}

fclose($handle);
exit;
?>