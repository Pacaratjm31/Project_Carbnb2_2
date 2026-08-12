<?php

// Carbnb Mobile APK location
$apkFile = __DIR__ . '/Carbnb_Mobile/Carbnb_Mobile.apk';

// Check if APK exists
if (!file_exists($apkFile)) {
    http_response_code(404);
    die('Carbnb Mobile APK was not found.');
}

// Force APK download
header('Content-Type: application/vnd.android.package-archive');
header('Content-Disposition: attachment; filename="Carbnb_Mobile.apk"');
header('Content-Length: ' . filesize($apkFile));
header('Cache-Control: no-cache, must-revalidate');
header('Pragma: no-cache');

readfile($apkFile);
exit;
?>