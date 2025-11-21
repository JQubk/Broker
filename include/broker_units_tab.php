<?php
if (!defined("B_PROLOG_INCLUDED") || B_PROLOG_INCLUDED !== true) {
    die();
}

use Bitrix\Main\UI\PageNavigation;

require_once $_SERVER['DOCUMENT_ROOT'] . '/include/broker_handler.php';
require_once $_SERVER['DOCUMENT_ROOT'] . '/include/broker_units.php';

global $APPLICATION;

$broker = broker_current();

$gridId = 'BROKER_UNITS_GRID';

$allowedPageSizes = [10, 20, 50, 100];
$defaultPageSize = 20;

$pageSize = $defaultPageSize;
if (isset($_GET['per_page'])) {
    $tmp = (int)$_GET['per_page'];
    if (in_array($tmp, $allowedPageSizes, true)) {
        $pageSize = $tmp;
    }
}

$nav = new PageNavigation($gridId);
$nav->allowAllRecords(false)
    ->setPageSize($pageSize)
    ->initFromUri();

$currentPage = max(1, $nav->getCurrentPage());

$totalCount = 0;

try {
    $units = broker_units_get_list($broker, $currentPage, $pageSize, $totalCount);
} catch (\Throwable $e) {
    echo '<div class="alert alert-danger">Failed to load units: '
        . htmlspecialcharsbx($e->getMessage()) . '</div>';
    return;
}

$nav->setRecordCount($totalCount);

$rows = [];

foreach ($units as $unit) {
    $layoutBtn = '';
    if (!empty($unit['LAYOUT_URL'])) {
        $layoutBtn =
            '<a href="' . htmlspecialcharsbx($unit['LAYOUT_URL']) . '" ' .
            'class="btn btn-link btn-sm" target="_blank">View layout</a>';
    }

    $salesOfferUrl = '/broker/sales_offer.php?unit_id=' . (int)$unit['ID'];
    $paymentPlanUrl = '/broker/payment_plan.php?unit_id=' . (int)$unit['ID'];

    $salesOfferBtn =
        '<a href="' . htmlspecialcharsbx($salesOfferUrl) . '" ' .
        'class="btn btn-sm btn-outline-primary" target="_blank">Sales offer</a>';

    $paymentPlanBtn =
        '<a href="' . htmlspecialcharsbx($paymentPlanUrl) . '" ' .
        'class="btn btn-sm btn-outline-secondary" target="_blank">Payment plan</a>';

    $rows[] = [
        'id'      => $unit['ID'],
        'columns' => [
            'PROJECT' => $unit['PROJECT_NAME'],
            'UNIT'    => $unit['UNIT_TITLE'],
            'TYPE'    => $unit['TYPE_NAME'],
            'AREA'    => $unit['AREA_SQFT'],
            'PRICE'   => $unit['PRICE_FORMATTED'],
            'STATUS'  => $unit['STATUS_NAME'],
            'LAYOUTS' => $layoutBtn,
            'ACTIONS' => $salesOfferBtn . ' ' . $paymentPlanBtn,
        ],
    ];
}

$currentUrl = strtok($_SERVER['REQUEST_URI'], '?');
$baseQuery = $_GET;
unset($baseQuery['per_page']);
?>

<?php
$APPLICATION->IncludeComponent(
    "bitrix:main.ui.grid",
    "",
    [
        "GRID_ID"                 => $gridId,
        "COLUMNS"                 => [
            ["id" => "PROJECT", "name" => "Project", "default" => true],
            ["id" => "UNIT",    "name" => "Unit #",  "default" => true],
            ["id" => "TYPE",    "name" => "Type",    "default" => true],
            ["id" => "AREA",    "name" => "Area",    "default" => true],
            ["id" => "PRICE",   "name" => "Price",   "default" => true],
            ["id" => "STATUS",  "name" => "Status",  "default" => true],
            ["id" => "LAYOUTS", "name" => "Layouts", "default" => true],
            ["id" => "ACTIONS", "name" => "Actions", "default" => true],
        ],
        "ROWS"                    => $rows,
        "NAV_OBJECT"              => $nav,
        "TOTAL_ROWS_COUNT"        => $totalCount,

        "SHOW_NAVIGATION_PANEL"   => false,
        "SHOW_PAGINATION"         => false,
        "SHOW_PAGESIZE"           => false,

        "AJAX_MODE"               => "Y",
        "AJAX_OPTION_JUMP"        => "N",
        "AJAX_OPTION_STYLE"       => "N",
        "SHOW_ROW_CHECKBOXES"     => false,
        "SHOW_GRID_SETTINGS_MENU" => true,
        "SHOW_SELECTED_COUNTER"   => false,
        "SHOW_TOTAL_COUNTER"      => false,
    ]
);
?>

<div class="d-flex justify-content-between align-items-center mt-3">
    <div class="main-ui-pagination">
        <?php
        $APPLICATION->IncludeComponent(
            "bitrix:main.pagenavigation",
            "grid",
            [
                "NAV_OBJECT" => $nav,
                "SEF_MODE"   => "N",
            ],
            false
        );
        ?>
    </div>
    <div class="main-ui-pagination">
        <span class="main-ui-pagination-label">Rows per page:</span>
        <?php foreach ($allowedPageSizes as $size): ?>
            <?php
            $query = $baseQuery;
            $query['per_page'] = $size;
            $href = $currentUrl . '?' . http_build_query($query);
            $active = $size === $pageSize;
            ?>
            <a href="<?= htmlspecialcharsbx($href) ?>"
               class="main-ui-pagination-page text-center<?= $active ? ' main-ui-pagination-page-active' : '' ?>">
                <?= (int)$size ?>
            </a>
        <?php endforeach; ?>
    </div>
</div>