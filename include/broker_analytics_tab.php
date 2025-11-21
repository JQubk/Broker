<?php
if (!defined("B_PROLOG_INCLUDED") || B_PROLOG_INCLUDED !== true) {
    die();
}

$agentsRows = [
    [
        'id' => 'a1',
        'columns' => [
            'AGENT_NAME'      => 'John Smith',
            'DEALS_SUM'       => '6 900 000 AED',
            'COMMISSIONS_SUM' => '310 000 AED',
        ],
    ],
    [
        'id' => 'a2',
        'columns' => [
            'AGENT_NAME'      => 'Emma Brown',
            'DEALS_SUM'       => '4 500 000 AED',
            'COMMISSIONS_SUM' => '180 000 AED',
        ],
    ],
];

$agenciesRows = [
    [
        'id' => 'ag1',
        'columns' => [
            'AGENCY_NAME'     => 'Example Realty',
            'DEALS_SUM'       => '9 800 000 AED',
            'COMMISSIONS_SUM' => '420 000 AED',
        ],
    ],
    [
        'id' => 'ag2',
        'columns' => [
            'AGENCY_NAME'     => 'Prime Properties',
            'DEALS_SUM'       => '3 200 000 AED',
            'COMMISSIONS_SUM' => '120 000 AED',
        ],
    ],
];
?>

<div class="broker-analytics">
    <div class="row g-4">
            <h6 class="mb-3">Top agents (deals & commissions)</h6>
            <?php
            $APPLICATION->IncludeComponent(
                "bitrix:main.ui.grid",
                "",
                [
                    "GRID_ID" => "BROKER_ANALYTICS_AGENTS_GRID",
                    "COLUMNS" => [
                        ["id" => "AGENT_NAME",      "name" => "Agent",                   "default" => true],
                        ["id" => "DEALS_SUM",       "name" => "Deals amount (AED)",      "default" => true],
                        ["id" => "COMMISSIONS_SUM", "name" => "Commissions amount (AED)", "default" => true],
                    ],
                    "ROWS"      => $agentsRows,
                    "AJAX_MODE" => "N",
                ]
            );
            ?>
    </div>
</div>
<hr class="mt-5 mb-5">
<div class="broker-analytics">
    <div class="row g-4">
            <h6 class="mb-3">Top agencies</h6>
            <?php
            $APPLICATION->IncludeComponent(
                "bitrix:main.ui.grid",
                "",
                [
                    "GRID_ID" => "BROKER_ANALYTICS_AGENCIES_GRID",
                    "COLUMNS" => [
                        ["id" => "AGENCY_NAME",     "name" => "Agency",                  "default" => true],
                        ["id" => "DEALS_SUM",       "name" => "Deals amount (AED)",      "default" => true],
                        ["id" => "COMMISSIONS_SUM", "name" => "Commissions amount (AED)", "default" => true],
                    ],
                    "ROWS"      => $agenciesRows,
                    "AJAX_MODE" => "N",
                ]
            );
            ?>
        </div>
    </div>
</div>
