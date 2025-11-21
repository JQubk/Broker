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
    '!UF_CRM_1763708684' => false,
];

$select = [
    'ID',
    'OPPORTUNITY',
    'STAGE_ID',
    'UF_CRM_1763708684',
    'UF_CRM_1763304021510',
];

$agentsData = [];

$res = CCrmDeal::GetListEx(
    [],
    $filter,
    false,
    false,
    $select
);

while ($row = $res->Fetch()) {
    $brokerName = (string)$row['UF_CRM_1763708684'];
    $amount = (float)$row['OPPORTUNITY'];
    $commission = (float)($row['UF_CRM_1763304021510'] ?? 0);
    $stageId = (string)$row['STAGE_ID'];
    
    if ($brokerName === '' || $amount <= 0) {
        continue;
    }
    
    if (!isset($agentsData[$brokerName])) {
        $agentsData[$brokerName] = [
            'name' => $brokerName,
            'dealsSum' => 0,
            'commissionsSum' => 0,
            'dealsCount' => 0,
            'wonDeals' => 0,
            'activeDeals' => 0
        ];
    }
    
    $agentsData[$brokerName]['dealsSum'] += $amount;
    $agentsData[$brokerName]['commissionsSum'] += ($amount * $commission) / 100;
    $agentsData[$brokerName]['dealsCount']++;
    
    if ($stageId === 'WON') {
        $agentsData[$brokerName]['wonDeals']++;
    } elseif (!in_array($stageId, ['WON', 'LOSE'])) {
        $agentsData[$brokerName]['activeDeals']++;
    }
}

usort($agentsData, function($a, $b) {
    return $b['dealsSum'] <=> $a['dealsSum'];
});

$agentsRows = [];
$rank = 1;
foreach ($agentsData as $data) {
    $agentsRows[] = [
        'id' => 'agent_' . md5($data['name']),
        'columns' => [
            'RANK' => $rank++,
            'AGENT_NAME' => $data['name'],
            'DEALS_COUNT' => $data['dealsCount'],
            'ACTIVE_DEALS' => $data['activeDeals'],
            'WON_DEALS' => $data['wonDeals'],
            'DEALS_SUM' => number_format($data['dealsSum'], 0, '.', ' ') . ' AED',
            'COMMISSIONS_SUM' => number_format($data['commissionsSum'], 0, '.', ' ') . ' AED',
        ],
    ];
}

$APPLICATION->IncludeComponent(
    "bitrix:main.ui.grid",
    "",
    [
        "GRID_ID" => "BROKER_DASHBOARD_AGENTS_GRID",
        "COLUMNS" => [
            ["id" => "RANK", "name" => "#", "default" => true, "width" => 60],
            ["id" => "AGENT_NAME", "name" => "Agent", "default" => true],
            ["id" => "DEALS_COUNT", "name" => "Total deals", "default" => true],
            ["id" => "ACTIVE_DEALS", "name" => "Active", "default" => true],
            ["id" => "WON_DEALS", "name" => "Won", "default" => true],
            ["id" => "DEALS_SUM", "name" => "Deals amount", "default" => true],
            ["id" => "COMMISSIONS_SUM", "name" => "Commissions", "default" => true],
        ],
        "ROWS" => $agentsRows,
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
        "TOTAL_ROWS_COUNT" => count($agentsRows),
        "NAV_PARAM_NAME" => "page_agents",
        "PAGE_SIZES" => [
            ["NAME" => "10", "VALUE" => "10"],
            ["NAME" => "20", "VALUE" => "20"],
            ["NAME" => "50", "VALUE" => "50"],
            ["NAME" => "100", "VALUE" => "100"],
        ],
    ]
);