<?php
require $_SERVER['DOCUMENT_ROOT'] . '/local/classes/BrokerCabinet/autoload.php';

use BrokerCabinet\AuthService;
use BrokerCabinet\DashboardService;
use BrokerCabinet\Format;

$authService = new AuthService();
$broker = $authService->requireAuth();

$dashboardService = new DashboardService();

$filter = [];
if (!empty($_GET['period'])) {
    $filter['period'] = $_GET['period'];
}
if (!empty($_GET['date_from'])) {
    $filter['date_from'] = $_GET['date_from'];
}
if (!empty($_GET['date_to'])) {
    $filter['date_to'] = $_GET['date_to'];
}

$stats = $dashboardService->getStats($broker, $filter);

require $_SERVER['DOCUMENT_ROOT'] . '/bitrix/header.php';

global $APPLICATION;
$APPLICATION->SetTitle('Broker Cabinet');
?>

<div class="container-fluid">
    <div class="row mb-3">
        <div class="col">
            <h4>Broker / Partner Cabinet</h4>
        </div>
        <div class="col-auto">
            <?php
            $APPLICATION->IncludeComponent(
                "bitrix:main.ui.filter",
                "",
                [
                    "FILTER_ID" => "BROKER_DASHBOARD_FILTER",
                    "GRID_ID" => "BROKER_DASHBOARD_GRID",
                    "FILTER" => [
                        [
                            "id" => "period",
                            "name" => "Period",
                            "type" => "list",
                            "items" => [
                                "current_month" => "Current month",
                                "last_month" => "Last month",
                                "quarter" => "Quarter",
                                "year" => "Year",
                            ],
                            "default" => true,
                        ],
                    ],
                    "ENABLE_LABEL" => true,
                ]
            );
            ?>
        </div>
    </div>

    <div class="row mb-4 g-3">
        <div class="col-md-3">
            <div class="card h-100">
                <div class="card-body text-center">
                    <div class="text-muted small mb-2">Deals in Progress</div>
                    <h4 class="mb-0"><?= Format::price($stats['in_progress'], $broker['CURRENCY']) ?></h4>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card h-100">
                <div class="card-body text-center">
                    <div class="text-muted small mb-2">Deals in Booking</div>
                    <h4 class="mb-0"><?= Format::price($stats['booking'], $broker['CURRENCY']) ?></h4>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card h-100">
                <div class="card-body text-center">
                    <div class="text-muted small mb-2">Deals in SPA</div>
                    <h4 class="mb-0"><?= Format::price($stats['spa'], $broker['CURRENCY']) ?></h4>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card h-100">
                <div class="card-body text-center">
                    <div class="text-muted small mb-2">Paid Commissions</div>
                    <h4 class="mb-0"><?= Format::price($stats['commissions'], $broker['CURRENCY']) ?></h4>
                </div>
            </div>
        </div>
    </div>

    <ul class="nav nav-tabs mb-3" role="tablist">
        <li class="nav-item">
            <a class="nav-link active" data-bs-toggle="tab" href="#units">Units Availability</a>
        </li>
        <li class="nav-item">
            <a class="nav-link" data-bs-toggle="tab" href="#deals">Deals</a>
        </li>
        <li class="nav-item">
            <a class="nav-link" data-bs-toggle="tab" href="#commissions">Commission Request</a>
        </li>
        <li class="nav-item">
            <a class="nav-link" data-bs-toggle="tab" href="#analytics">Analytics</a>
        </li>
    </ul>

    <div class="tab-content">
        <div class="tab-pane fade show active" id="units">
            <?php include __DIR__ . '/broker/units_tab.php'; ?>
        </div>
        <div class="tab-pane fade" id="deals">
            <?php include __DIR__ . '/broker/deals_tab.php'; ?>
        </div>
        <div class="tab-pane fade" id="commissions">
            <?php include __DIR__ . '/broker/commissions_tab.php'; ?>
        </div>
        <div class="tab-pane fade" id="analytics">
            <?php include __DIR__ . '/broker/analytics_tab.php'; ?>
        </div>
    </div>
</div>

<?php
require $_SERVER['DOCUMENT_ROOT'] . '/bitrix/footer.php';