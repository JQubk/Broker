<?php

if (!defined("B_PROLOG_INCLUDED") || B_PROLOG_INCLUDED !== true) {
    die();
}

use Bitrix\Main\Loader;

if (!Loader::includeModule('crm')) {
    echo '<div class="alert alert-danger">CRM module is not available.</div>';
    return;
}

$filter = [
    'CATEGORY_ID' => 0,
    'CHECK_PERMISSIONS' => 'N',
    '!UF_CRM_1756215544' => false,
];

$select = [
    'ID',
    'OPPORTUNITY',
    'STAGE_ID',
    'UF_CRM_1756215544',
    'UF_CRM_1763304021510',
];

$agenciesData = [];

$res = CCrmDeal::GetListEx(
    [],
    $filter,
    false,
    false,
    $select
);

while ($row = $res->Fetch()) {
    $agencyName = (string)$row['UF_CRM_1756215544'];
    $amount = (float)$row['OPPORTUNITY'];
    $commission = (float)($row['UF_CRM_1763304021510'] ?? 0);
    $stageId = (string)$row['STAGE_ID'];
    
    if ($agencyName === '' || $amount <= 0) {
        continue;
    }
    
    if (!isset($agenciesData[$agencyName])) {
        $agenciesData[$agencyName] = [
            'name' => $agencyName,
            'dealsSum' => 0,
            'commissionsSum' => 0,
            'dealsCount' => 0,
            'wonDeals' => 0,
            'activeDeals' => 0,
            'agentsCount' => []
        ];
    }
    
    $agenciesData[$agencyName]['dealsSum'] += $amount;
    $agenciesData[$agencyName]['commissionsSum'] += ($amount * $commission) / 100;
    $agenciesData[$agencyName]['dealsCount']++;
    
    if ($stageId === 'WON') {
        $agenciesData[$agencyName]['wonDeals']++;
    } elseif (!in_array($stageId, ['WON', 'LOSE'])) {
        $agenciesData[$agencyName]['activeDeals']++;
    }
}

usort($agenciesData, function($a, $b) {
    return $b['dealsSum'] <=> $a['dealsSum'];
});

$agenciesRows = [];
$rank = 1;
foreach ($agenciesData as $data) {
    $avgDealSize = $data['dealsCount'] > 0 
        ? $data['dealsSum'] / $data['dealsCount'] 
        : 0;
    
    $agenciesRows[] = [
        'id' => 'agency_' . md5($data['name']),
        'columns' => [
            'RANK' => $rank++,
            'AGENCY_NAME' => $data['name'],
            'DEALS_COUNT' => $data['dealsCount'],
            'ACTIVE_DEALS' => $data['activeDeals'],
            'WON_DEALS' => $data['wonDeals'],
            'DEALS_SUM' => number_format($data['dealsSum'], 0, '.', ' ') . ' AED',
            'AVG_DEAL_SIZE' => number_format($avgDealSize, 0, '.', ' ') . ' AED',
            'COMMISSIONS_SUM' => number_format($data['commissionsSum'], 0, '.', ' ') . ' AED',
        ],
    ];
}

$APPLICATION->IncludeComponent(
    "bitrix:main.ui.grid",
    "",
    [
        "GRID_ID" => "BROKER_DASHBOARD_AGENCIES_GRID",
        "COLUMNS" => [
            ["id" => "RANK", "name" => "#", "default" => true, "width" => 60],
            ["id" => "AGENCY_NAME", "name" => "Agency", "default" => true],
            ["id" => "DEALS_COUNT", "name" => "Total deals", "default" => true],
            ["id" => "ACTIVE_DEALS", "name" => "Active", "default" => true],
            ["id" => "WON_DEALS", "name" => "Won", "default" => true],
            ["id" => "DEALS_SUM", "name" => "Total amount", "default" => true],
            ["id" => "AVG_DEAL_SIZE", "name" => "Avg deal size", "default" => true],
            ["id" => "COMMISSIONS_SUM", "name" => "Total commissions", "default" => true],
        ],
        "ROWS" => $agenciesRows,
        "AJAX_MODE" => "N",
        "SHOW_ROW_CHECKBOXES" => false,
        "SHOW_CHECK_ALL_CHECKBOXES" => false,
        "SHOW_ACTION_PANEL" => false,
        "ALLOW_COLUMNS_SORT" => true,
        "ALLOW_COLUMNS_RESIZE" => true,
        "SHOW_GRID_SETTINGS_MENU" => true,
        "SHOW_NAVIGATION_PANEL" => true,
        "SHOW_PAGINATION" => true,
        "SHOW_SELECTED_COUNTER" => false,
        "SHOW_TOTAL_COUNTER" => true,
        "TOTAL_ROWS_COUNT" => count($agenciesRows),
        "NAV_PARAM_NAME" => "page_agencies",
        "PAGE_SIZES" => [
            ["NAME" => "10", "VALUE" => "10"],
            ["NAME" => "20", "VALUE" => "20"],
            ["NAME" => "50", "VALUE" => "50"],
            ["NAME" => "100", "VALUE" => "100"],
        ],
    ]
);