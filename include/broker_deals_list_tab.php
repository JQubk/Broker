<?php
if (!defined("B_PROLOG_INCLUDED") || B_PROLOG_INCLUDED !== true) {
    die();
}

use Bitrix\Main\Loader;
use Bitrix\Main\UI\PageNavigation;
use Bitrix\Crm\Service;

global $APPLICATION;

require_once $_SERVER['DOCUMENT_ROOT'] . '/include/broker_handler.php';

$broker = broker_current();
if ($broker === null) {
    echo '<div class="alert alert-danger">Broker is not authorized.</div>';
    return;
}

if (!Loader::includeModule('crm')) {
    echo '<div class="alert alert-danger">CRM module is not available.</div>';
    return;
}

$brokerName = trim((string)($broker['NAME'] ?? '') . ' ' . (string)($broker['LAST_NAME'] ?? ''));
if ($brokerName === '') {
    echo '<div class="alert alert-warning">Broker name is empty in profile.</div>';
    return;
}

$gridId = 'BROKER_DEALS_GRID';

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

$select = [
    'ID',
    'TITLE',
    'STAGE_ID',
    'OPPORTUNITY',
    'CURRENCY_ID',
    'CONTACT_ID',
    'UF_CRM_1763708684',
    'UF_CRM_1756215456',
    'UF_CRM_1756215507',
];

$filter = [
    'CATEGORY_ID'         => 0,
    'UF_CRM_1763708684'   => $brokerName,
    'CHECK_PERMISSIONS'   => 'N',
];

$totalCount = (int)CCrmDeal::GetListEx([], $filter, []);

$nav->setRecordCount($totalCount);

$dealsRows = [];
$deals     = [];
$contactIds = [];

$navParams = [
    'iNumPage' => $currentPage,
    'nPageSize' => $pageSize,
];

$res = CCrmDeal::GetListEx(
    ['ID' => 'DESC'],
    $filter,
    false,
    $navParams,
    $select
);

while ($row = $res->Fetch()) {
    $id = (int)$row['ID'];
    $deals[$id] = $row;
    if ((int)$row['CONTACT_ID'] > 0) {
        $contactIds[(int)$row['CONTACT_ID']] = true;
    }
}

$contactsMap = [];
if (!empty($contactIds)) {
    $rs = CCrmContact::GetListEx(
        [],
        ['ID' => array_keys($contactIds)],
        false,
        false,
        ['ID', 'NAME', 'LAST_NAME', 'SECOND_NAME']
    );
    while ($c = $rs->Fetch()) {
        $name = trim($c['NAME'] . ' ' . $c['LAST_NAME']);
        if ($name === '') {
            $name = 'Contact #' . $c['ID'];
        }
        $contactsMap[(int)$c['ID']] = $name;
    }
}

$stageMap = [
    'NEW'                => 'Offer preparation',
    'PREPARATION'        => 'Payment confirmation',
    'UC_U0R404'          => 'EOI',
    'PREPAYMENT_INVOICE' => 'Booking',
    'EXECUTING'          => 'SPA / Investment agreement',
    'UC_IGBPMT'          => 'OQOOD',
    'FINAL_INVOICE'      => 'Handover',
    'WON'                => 'Won',
    'LOSE'               => 'Lost',
    'APOLOGY'            => 'Resale',
];

$unitsFactory = Service\Container::getInstance()->getFactory(1032);
$projectsFactory = Service\Container::getInstance()->getFactory(1036);

foreach ($deals as $id => $row) {
    $dealCode = 'D-' . $id;

    $clientName = '';
    $contactId = (int)$row['CONTACT_ID'];
    if ($contactId > 0 && isset($contactsMap[$contactId])) {
        $clientName = $contactsMap[$contactId];
    }

    $stageId = (string)$row['STAGE_ID'];
    $statusName = $stageMap[$stageId] ?? $stageId;

    $amount = '';
    if ((float)$row['OPPORTUNITY'] > 0) {
        $amount = number_format((float)$row['OPPORTUNITY'], 0, '.', ' ')
            . ' ' . ($row['CURRENCY_ID'] ?: 'AED');
    }

    $projectName = '';
    $projectBinding = (string)($row['UF_CRM_1756215456'] ?? '');
    if ($projectBinding !== '' && strpos($projectBinding, 'T40c_') === 0) {
        $projectId = (int)substr($projectBinding, 5);
        if ($projectId > 0 && $projectsFactory !== null) {
            $projectItem = $projectsFactory->getItem($projectId);
            if ($projectItem !== null) {
                $projectName = (string)$projectItem->getTitle();
            }
        }
    }

    $unitName = '';
    $unitBinding = (string)($row['UF_CRM_1756215507'] ?? '');
    if ($unitBinding !== '' && strpos($unitBinding, 'T408_') === 0) {
        $unitId = (int)substr($unitBinding, 5);
        if ($unitId > 0 && $unitsFactory !== null) {
            $unitItem = $unitsFactory->getItem($unitId);
            if ($unitItem !== null) {
                $unitName = (string)$unitItem->getTitle();
            }
        }
    }

    $editUrl = '/broker/deal_edit.php?DEAL_ID=' . $id;

    $actionsHtml =
        '<a href="' . htmlspecialcharsbx($editUrl) . '" ' .
        'class="btn btn-sm btn-outline-primary me-1" ' .
        'data-broker-sidepanel="Y">Edit</a>' .
        '<button type="button" ' .
        'class="btn btn-sm btn-outline-danger" ' .
        'data-role="broker-deal-delete" ' .
        'data-row-id="' . (int)$id . '">Delete</button>';

    $dealsRows[] = [
        'id'      => $id,
        'columns' => [
            'DEAL_ID' => $dealCode,
            'CLIENT'  => $clientName,
            'PROJECT' => $projectName,
            'UNIT'    => $unitName,
            'STATUS'  => $statusName,
            'AMOUNT'  => $amount,
            'ACTIONS' => $actionsHtml,
        ],
    ];
}

$currentUrl = strtok($_SERVER['REQUEST_URI'], '?');
$baseQuery = $_GET;
unset($baseQuery['per_page']);
?>

<div class="d-flex justify-content-between align-items-center mb-2">
    <h6 class="mb-0">Deals list</h6>
    <a href="/broker/deal_edit.php?action=add"
       class="btn btn-sm btn-primary"
       data-broker-sidepanel="Y">
        Register deal
    </a>
</div>

<?php
$APPLICATION->IncludeComponent(
    "bitrix:main.ui.grid",
    "",
    [
        "GRID_ID"                 => $gridId,
        "COLUMNS"                 => [
            ["id" => "DEAL_ID", "name" => "Deal",         "default" => true],
            ["id" => "CLIENT",  "name" => "Client",       "default" => true],
            ["id" => "PROJECT", "name" => "Project",      "default" => true],
            ["id" => "UNIT",    "name" => "Unit",         "default" => true],
            ["id" => "STATUS",  "name" => "Status",       "default" => true],
            ["id" => "AMOUNT",  "name" => "Amount (AED)", "default" => true],
        ],
        "ROWS"                    => $dealsRows,
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