<?php
if (!defined("B_PROLOG_INCLUDED") || B_PROLOG_INCLUDED !== true) {
    die();
}

$dealsRows = [
    [
        'id' => 'd1',
        'columns' => [
            'DEAL_ID' => 'D-1001',
            'CLIENT'  => 'John Smith',
            'PROJECT' => 'Sunrise Residence',
            'UNIT'    => 'A-101',
            'STATUS'  => 'In progress',
            'AMOUNT'  => '1 200 000 AED',
            'ACTIONS' =>
                '<a href="/broker/deal_edit.php?DEAL_ID=D-1001" ' .
                'class="btn btn-sm btn-outline-primary me-1" ' .
                'data-broker-sidepanel="Y">Edit</a>' .
                '<button type="button" class="btn btn-sm btn-outline-danger" disabled>Delete</button>',
        ],
    ],
    [
        'id' => 'd2',
        'columns' => [
            'DEAL_ID' => 'D-1002',
            'CLIENT'  => 'Emma Brown',
            'PROJECT' => 'Harbour Villas',
            'UNIT'    => 'V-07',
            'STATUS'  => 'Booking',
            'AMOUNT'  => '4 500 000 AED',
            'ACTIONS' =>
                '<a href="/broker/deal_edit.php?DEAL_ID=D-1002" ' .
                'class="btn btn-sm btn-outline-primary me-1" ' .
                'data-broker-sidepanel="Y">Edit</a>' .
                '<button type="button" class="btn btn-sm btn-outline-danger" disabled>Delete</button>',
        ],
    ],
];
?>

<div class="d-flex justify-content-between align-items-center mb-2">
    <h6 class="mb-0">Deals list</h6>
    <a href="/broker/deal_edit.php?action=add"
       class="btn btn-sm btn-primary"
       data-broker-sidepanel="Y">
        Add deal
    </a>
</div>

<?php
$APPLICATION->IncludeComponent(
    "bitrix:main.ui.filter",
    "",
    [
        "FILTER_ID" => "BROKER_DEALS_FILTER",
        "GRID_ID"   => "BROKER_DEALS_GRID",
        "FILTER"    => [
            ["id" => "DEAL_ID", "name" => "Deal",   "type" => "string"],
            ["id" => "CLIENT",  "name" => "Client", "type" => "string"],
            ["id" => "PROJECT", "name" => "Project","type" => "string"],
            [
                "id"    => "STATUS",
                "name"  => "Status",
                "type"  => "list",
                "items" => [
                    "in_progress" => "In progress",
                    "booking"     => "Booking",
                    "spa"         => "SPA / Investment agreement",
                    "lost"        => "Lost",
                ],
            ],
            [
                "id"   => "DATE_CLOSE",
                "name" => "Close date",
                "type" => "date",
            ],
        ],
        "ENABLE_LABEL"       => true,
        "ENABLE_LIVE_SEARCH" => true,
    ]
);

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
