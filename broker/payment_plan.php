<?php
require $_SERVER['DOCUMENT_ROOT'] . '/local/classes/BrokerCabinet/autoload.php';

use BrokerCabinet\UnitService;
use BrokerCabinet\AuthService;
use BrokerCabinet\Format;

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

    $filename = 'Payment_Plan_' . preg_replace('/[^A-Za-z0-9_\-]/', '_', $unit['TITLE']) . '.txt';

    header('Content-Type: text/plain; charset=UTF-8');
    header('Content-Disposition: attachment; filename="' . $filename . '"');

    $price = $unit['PRICE'];
    $currency = 'AED';

    echo "PAYMENT PLAN\n";
    echo str_repeat("=", 60) . "\n\n";
    echo "Project: " . $unit['PROJECT_NAME'] . "\n";
    echo "Unit: " . $unit['TITLE'] . "\n";
    echo "Type: " . $unit['PROPERTY_TYPE'] . "\n";
    echo "Total Price: " . $unit['PRICE_FORMATTED'] . "\n";
    echo "\n" . str_repeat("-", 60) . "\n";
    echo "PAYMENT SCHEDULE\n";
    echo str_repeat("-", 60) . "\n\n";

    $payments = [
        ['stage' => 'Booking', 'percent' => 10, 'amount' => $price * 0.10],
        ['stage' => 'Down Payment', 'percent' => 20, 'amount' => $price * 0.20],
        ['stage' => 'During Construction', 'percent' => 40, 'amount' => $price * 0.40],
        ['stage' => 'On Handover', 'percent' => 30, 'amount' => $price * 0.30],
    ];

    foreach ($payments as $payment) {
        echo sprintf(
            "%-25s %5d%%  %s\n",
            $payment['stage'],
            $payment['percent'],
            Format::price($payment['amount'], $currency)
        );
    }

    echo "\n" . str_repeat("=", 60) . "\n";
    echo sprintf("%-25s %5s  %s\n", "TOTAL", "100%", Format::price($price, $currency));
    echo str_repeat("=", 60) . "\n\n";
    echo "Generated: " . date('d.m.Y H:i:s') . "\n";

} catch (\Throwable $e) {
    http_response_code(500);
    echo 'Error: ' . $e->getMessage();
}