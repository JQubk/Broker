<?php
if (!defined("B_PROLOG_INCLUDED") || B_PROLOG_INCLUDED !== true) {
    die();
}

use Bitrix\Main\Loader;

global $APPLICATION;

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

$select = [
    'ID',
    'TITLE',
    'STAGE_ID',
    'OPPORTUNITY',
    'CURRENCY_ID',
    'CONTACT_ID',
    'UF_CRM_1763708684',
];

$filter = [
    'CATEGORY_ID'         => 0,
    'UF_CRM_1763708684'   => $brokerName,
    'CHECK_PERMISSIONS'   => 'N',
];

$dealsRows = [];
$deals     = [];
$contactIds = [];

$res = CCrmDeal::GetListEx(
    ['ID' => 'DESC'],
    $filter,
    false,
    ['nTopCount' => 200],
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
            'PROJECT' => '',
            'UNIT'    => '',
            'STATUS'  => $statusName,
            'AMOUNT'  => $amount,
            'ACTIONS' => $actionsHtml,
        ],
    ];
}
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
        "GRID_ID" => "BROKER_DEALS_GRID",
        "COLUMNS" => [
            ["id" => "DEAL_ID", "name" => "Deal",         "default" => true],
            ["id" => "CLIENT",  "name" => "Client",       "default" => true],
            ["id" => "PROJECT", "name" => "Project",      "default" => true],
            ["id" => "UNIT",    "name" => "Unit",         "default" => true],
            ["id" => "STATUS",  "name" => "Status",       "default" => true],
            ["id" => "AMOUNT",  "name" => "Amount (AED)", "default" => true],
        ],
        "ROWS"      => $dealsRows,
        "AJAX_MODE" => "N",
    ]
);
