<?php
if (!defined("B_PROLOG_INCLUDED") || B_PROLOG_INCLUDED !== true) {
    die();
}

use Bitrix\Main\Loader;
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

$brokerEmail = (string)($broker['EMAIL'] ?? '');
$brokerCommission = 0;

if ($brokerEmail !== '') {
    $brokerItem = broker_find_item_by_email($brokerEmail);
    if ($brokerItem !== null) {
        $bData = $brokerItem->getData();
        $brokerCommission = (float)($bData['UF_CRM_15_COMMISSION'] ?? 0);
    }
}

$select = [
    'ID',
    'TITLE',
    'STAGE_ID',
    'OPPORTUNITY',
    'CURRENCY_ID',
    'UF_CRM_1763708684',
    'UF_CRM_1756215507',
    'UF_CRM_1756215456',
];

$filter = [
    'CATEGORY_ID'         => 0,
    'UF_CRM_1763708684'   => $brokerName,
    'CHECK_PERMISSIONS'   => 'N',
];

$dealsRows = [];

$res = CCrmDeal::GetListEx(
    ['ID' => 'DESC'],
    $filter,
    false,
    ['nTopCount' => 100],
    $select
);

$unitsFactory = Service\Container::getInstance()->getFactory(1032);
$projectsFactory = Service\Container::getInstance()->getFactory(1036);

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

while ($row = $res->Fetch()) {
    $id = (int)$row['ID'];
    $dealCode = 'D-' . $id;
    
    $amount = (float)$row['OPPORTUNITY'];
    $currency = $row['CURRENCY_ID'] ?: 'AED';
    
    if ($amount <= 0) {
        continue;
    }
    
    $commission = ($amount * $brokerCommission) / 100;
    
    $stageId = (string)$row['STAGE_ID'];
    $statusName = $stageMap[$stageId] ?? $stageId;
    
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
    
    $amountFormatted = number_format($amount, 0, '.', ' ') . ' ' . $currency;
    $commissionFormatted = number_format($commission, 2, '.', ' ') . ' ' . $currency;
    
    $requestBtn = '<button type="button" class="btn btn-sm btn-success" 
        onclick="requestCommission(' . $id . ', \'' . htmlspecialcharsbx($dealCode) . '\', ' . $commission . ', \'' . $currency . '\')">
        Request commission
    </button>';
    
    $dealsRows[] = [
        'id'      => $id,
        'columns' => [
            'DEAL_ID'    => $dealCode,
            'PROJECT'    => $projectName,
            'UNIT'       => $unitName,
            'STATUS'     => $statusName,
            'AMOUNT'     => $amountFormatted,
            'COMMISSION' => $commissionFormatted,
            'ACTIONS'    => $requestBtn,
        ],
    ];
}
?>

<div class="mb-3">
    <h6>Commission rate: <?= number_format($brokerCommission, 2) ?>%</h6>
</div>

<?php
$APPLICATION->IncludeComponent(
    "bitrix:main.ui.grid",
    "",
    [
        "GRID_ID" => "BROKER_COMMISSIONS_GRID",
        "COLUMNS" => [
            ["id" => "DEAL_ID",    "name" => "Deal",       "default" => true],
            ["id" => "PROJECT",    "name" => "Project",    "default" => true],
            ["id" => "UNIT",       "name" => "Unit",       "default" => true],
            ["id" => "STATUS",     "name" => "Status",     "default" => true],
            ["id" => "AMOUNT",     "name" => "Amount",     "default" => true],
            ["id" => "COMMISSION", "name" => "Commission", "default" => true],
            ["id" => "ACTIONS",    "name" => "Actions",    "default" => true],
        ],
        "ROWS"      => $dealsRows,
        "AJAX_MODE" => "N",
        "SHOW_ROW_CHECKBOXES" => false,
    ]
);
?>

<script>
function requestCommission(dealId, dealCode, commission, currency) {
    if (!confirm('Request commission for deal ' + dealCode + '?\nCommission amount: ' + commission.toFixed(2) + ' ' + currency)) {
        return;
    }
    
    var form = document.createElement('form');
    form.method = 'POST';
    form.action = '/broker/commission_request.php';
    
    var dealInput = document.createElement('input');
    dealInput.type = 'hidden';
    dealInput.name = 'DEAL_ID';
    dealInput.value = dealId;
    form.appendChild(dealInput);
    
    var sessidInput = document.createElement('input');
    sessidInput.type = 'hidden';
    sessidInput.name = 'sessid';
    sessidInput.value = BX.bitrix_sessid();
    form.appendChild(sessidInput);
    
    document.body.appendChild(form);
    form.submit();
}
</script>