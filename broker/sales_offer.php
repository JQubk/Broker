<?php
require $_SERVER['DOCUMENT_ROOT'] . '/local/classes/BrokerCabinet/autoload.php';

use BrokerCabinet\UnitService;
use BrokerCabinet\AuthService;

$authService = new AuthService();
$broker = $authService->requireAuth();

$unitId = isset($_GET['unit_id']) ? (int)$_GET['unit_id'] : 0;

if ($unitId <= 0) {
    http_response_code(400);
    echo 'Invalid unit ID';
    exit;
}

try {
    $unitService = new UnitService();
    $unit = $unitService->getById($unitId);

    if ($unit === null) {
        http_response_code(404);
        echo 'Unit not found';
        exit;
    }

    $filename = 'Sales_Offer_' . preg_replace('/[^A-Za-z0-9_\-]/', '_', $unit['TITLE']) . '.txt';

    header('Content-Type: text/plain; charset=UTF-8');
    header('Content-Disposition: attachment; filename="' . $filename . '"');

    echo "SALES OFFER\n";
    echo str_repeat("=", 60) . "\n\n";
    echo "Project: " . $unit['PROJECT_NAME'] . "\n";
    echo "Unit: " . $unit['TITLE'] . "\n";
    echo "Type: " . $unit['PROPERTY_TYPE'] . "\n";
    echo "Area: " . $unit['AREA_FORMATTED'] . "\n";
    echo "Price: " . $unit['PRICE_FORMATTED'] . "\n";
    echo "Status: " . $unit['STATUS'] . "\n";
    echo "\n" . str_repeat("=", 60) . "\n\n";
    echo "Generated: " . date('d.m.Y H:i:s') . "\n";

} catch (\Throwable $e) {
    http_response_code(500);
    echo 'Error: ' . $e->getMessage();
}