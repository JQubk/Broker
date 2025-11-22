<?php
use BrokerCabinet\UnitService;
use BrokerCabinet\Format;

$unitService = new UnitService();

$gridId = 'BROKER_UNITS_GRID';
$pageSize = isset($_GET['per_page']) ? (int)$_GET['per_page'] : 20;
$page = isset($_GET['page']) ? max(1, (int)$_GET['page']) : 1;

$totalCount = 0;
$units = $unitService->getList([], $page, $pageSize, $totalCount);

$rows = [];
foreach ($units as $unit) {
    $layoutBtn = '';
    if (!empty($unit['LAYOUT_URL'])) {
        $layoutBtn = '<a href="' . htmlspecialcharsbx($unit['LAYOUT_URL']) . '" class="btn btn-link btn-sm" target="_blank">View</a>';
    }

    $salesOfferUrl = '/broker/sales_offer.php?unit_id=' . $unit['ID'];
    $paymentPlanUrl = '/broker/payment_plan.php?unit_id=' . $unit['ID'];

    $rows[] = [
        'id' => $unit['ID'],
        'columns' => [
            'PROJECT' => $unit['PROJECT_NAME'],
            'UNIT' => $unit['TITLE'],
            'TYPE' => $unit['PROPERTY_TYPE'],
            'AREA' => $unit['AREA_FORMATTED'],
            'PRICE' => $unit['PRICE_FORMATTED'],
            'STATUS' => $unit['STATUS'],
            'LAYOUTS' => $layoutBtn,
            'ACTIONS' => 
                '<a href="' . htmlspecialcharsbx($salesOfferUrl) . '" target="_blank" class="btn btn-sm btn-outline-primary">Sales Offer</a> ' .
                '<a href="' . htmlspecialcharsbx($paymentPlanUrl) . '" target="_blank" class="btn btn-sm btn-outline-secondary">Payment Plan</a>',
        ],
    ];
}

$APPLICATION->IncludeComponent(
    "bitrix:main.ui.grid",
    "",
    [
        "GRID_ID" => $gridId,
        "COLUMNS" => [
            ["id" => "PROJECT", "name" => "Project", "default" => true],
            ["id" => "UNIT", "name" => "Unit", "default" => true],
            ["id" => "TYPE", "name" => "Type", "default" => true],
            ["id" => "AREA", "name" => "Area", "default" => true],
            ["id" => "PRICE", "name" => "Price", "default" => true],
            ["id" => "STATUS", "name" => "Status", "default" => true],
            ["id" => "LAYOUTS", "name" => "Layouts", "default" => true],
            ["id" => "ACTIONS", "name" => "Actions", "default" => true],
        ],
        "ROWS" => $rows,
        "SHOW_ROW_CHECKBOXES" => false,
        "NAV_OBJECT" => new \Bitrix\Main\UI\PageNavigation($gridId),
        "AJAX_MODE" => "Y",
        "AJAX_ID" => \CAjax::GetComponentID("bitrix:main.ui.grid", ".default", ""),
        "PAGE_SIZES" => [
            ["NAME" => "10", "VALUE" => "10"],
            ["NAME" => "20", "VALUE" => "20"],
            ["NAME" => "50", "VALUE" => "50"],
        ],
        "AJAX_OPTION_JUMP" => "N",
        "SHOW_CHECK_ALL_CHECKBOXES" => false,
        "SHOW_ROW_ACTIONS_MENU" => true,
        "SHOW_GRID_SETTINGS_MENU" => true,
        "SHOW_NAVIGATION_PANEL" => true,
        "SHOW_PAGINATION" => true,
        "SHOW_SELECTED_COUNTER" => false,
        "SHOW_TOTAL_COUNTER" => true,
        "SHOW_PAGESIZE" => true,
        "SHOW_ACTION_PANEL" => false,
        "ALLOW_COLUMNS_SORT" => true,
        "ALLOW_COLUMNS_RESIZE" => true,
        "ALLOW_HORIZONTAL_SCROLL" => true,
        "ALLOW_SORT" => true,
        "ALLOW_PIN_HEADER" => true,
        "AJAX_OPTION_HISTORY" => "N",
        "TOTAL_ROWS_COUNT" => $totalCount,
    ]
);