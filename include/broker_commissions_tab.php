<?php
if (!defined("B_PROLOG_INCLUDED") || B_PROLOG_INCLUDED !== true) {
    die();
}

$commissionRows = [
    [
        'id' => 'c1',
        'columns' => [
            'DEAL_ID'            => 'D-1001',
            'DEAL_CLIENT'        => 'John Smith',
            'DEAL_AMOUNT'        => '1 200 000 AED',
            'COMMISSION_PERCENT' => '5%',
            'COMMISSION_AMOUNT'  => '60 000 AED',
            'COMMISSION_STATUS'  => 'Not requested',
            'ACTIONS'            =>
                '<a href="#" class="btn btn-sm btn-primary" ' .
                'data-role="broker-commission-request" ' .
                'data-deal-id="D-1001" ' .
                'data-commission-amount="60000">Request commission</a>',
        ],
    ],
    [
        'id' => 'c2',
        'columns' => [
            'DEAL_ID'            => 'D-1002',
            'DEAL_CLIENT'        => 'Emma Brown',
            'DEAL_AMOUNT'        => '4 500 000 AED',
            'COMMISSION_PERCENT' => '4%',
            'COMMISSION_AMOUNT'  => '180 000 AED',
            'COMMISSION_STATUS'  => 'Requested',
            'ACTIONS'            => '<span class="text-muted small">Already requested</span>',
        ],
    ],
];

$APPLICATION->IncludeComponent(
    "bitrix:main.ui.filter",
    "",
    [
        "FILTER_ID" => "BROKER_COMMISSIONS_FILTER",
        "GRID_ID"   => "BROKER_COMMISSIONS_GRID",
        "FILTER"    => [
            ["id" => "DEAL_ID",           "name" => "Deal",           "type" => "string"],
            ["id" => "COMMISSION_STATUS", "name" => "Request status", "type" => "list", "items" => [
                "new"       => "Not requested",
                "requested" => "Requested",
                "paid"      => "Paid",
            ]],
        ],
        "ENABLE_LABEL"       => true,
        "ENABLE_LIVE_SEARCH" => true,
    ]
);

$APPLICATION->IncludeComponent(
    "bitrix:main.ui.grid",
    "",
    [
        "GRID_ID" => "BROKER_COMMISSIONS_GRID",
        "COLUMNS" => [
            ["id" => "DEAL_ID",            "name" => "Deal",              "default" => true],
            ["id" => "DEAL_CLIENT",        "name" => "Client",            "default" => true],
            ["id" => "DEAL_AMOUNT",        "name" => "Deal amount",       "default" => true],
            ["id" => "COMMISSION_PERCENT", "name" => "Commission %",      "default" => true],
            ["id" => "COMMISSION_AMOUNT",  "name" => "Commission amount", "default" => true],
            ["id" => "COMMISSION_STATUS",  "name" => "Request status",    "default" => true],
            ["id" => "ACTIONS",            "name" => "Actions",           "default" => true],
        ],
        "ROWS"      => $commissionRows,
        "AJAX_MODE" => "N",
    ]
);
