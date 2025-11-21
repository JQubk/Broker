<?php
declare(strict_types=1);

use Bitrix\Main\Loader;
use Bitrix\Crm\Service;

require $_SERVER['DOCUMENT_ROOT'] . '/bitrix/modules/main/include/prolog_before.php';
require_once $_SERVER['DOCUMENT_ROOT'] . '/include/broker_handler.php';
require_once $_SERVER['DOCUMENT_ROOT'] . '/include/broker_units.php';

$broker = broker_current();
if ($broker === null) {
    header('Location: /auth/');
    require $_SERVER['DOCUMENT_ROOT'] . '/bitrix/modules/main/include/epilog_after.php';
    exit;
}

$unitId = isset($_GET['unit_id']) ? (int)$_GET['unit_id'] : 0;
if ($unitId <= 0) {
    http_response_code(400);
    echo 'Invalid unit';
    require $_SERVER['DOCUMENT_ROOT'] . '/bitrix/modules/main/include/epilog_after.php';
    exit;
}

if (!Loader::includeModule('crm')) {
    http_response_code(500);
    echo 'CRM module not installed';
    require $_SERVER['DOCUMENT_ROOT'] . '/bitrix/modules/main/include/epilog_after.php';
    exit;
}

$factory = Service\Container::getInstance()->getFactory(UNITS_ENTITY_TYPE_ID);
if ($factory === null) {
    http_response_code(500);
    echo 'Units factory not found';
    require $_SERVER['DOCUMENT_ROOT'] . '/bitrix/modules/main/include/epilog_after.php';
    exit;
}

$item = $factory->getItem($unitId);
if ($item === null) {
    http_response_code(404);
    echo 'Unit not found';
    require $_SERVER['DOCUMENT_ROOT'] . '/bitrix/modules/main/include/epilog_after.php';
    exit;
}

$data = $item->getData();

$unitTitle = (string)($data['TITLE'] ?? ('Unit-' . $unitId));
$projectRaw = broker_units_field_value($data, UNIT_FIELD_PROJECT);
$project = is_numeric($projectRaw)
    ? broker_units_resolve_project_title($projectRaw)
    : (string)$projectRaw;

$priceRaw = broker_units_field_value($data, UNIT_FIELD_PRICE);
$price = broker_units_format_price($priceRaw);

$filenameBase = 'Sales_Offer_' . preg_replace('/[^A-Za-z0-9_\-]/', '_', $unitTitle) . '.txt';

header('Content-Type: text/plain; charset=UTF-8');
header('Content-Disposition: attachment; filename="' . $filenameBase . '"');

echo "Sales Offer\n\n";
echo "Project: " . $project . "\n";
echo "Unit: " . $unitTitle . "\n";
echo "Price: " . $price . "\n";

require $_SERVER['DOCUMENT_ROOT'] . '/bitrix/modules/main/include/epilog_after.php';
