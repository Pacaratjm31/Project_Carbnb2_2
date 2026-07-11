<?php
header('Content-Type: application/json');
echo json_encode([
    'success' => true,
    'service' => 'Carbnb API',
    'status' => 'online'
]);
