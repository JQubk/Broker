<?php
require($_SERVER["DOCUMENT_ROOT"] . "/bitrix/header.php");
$APPLICATION->SetTitle("Broker cabinet");
?>

<div class="broker-wrapper">
    <div class="broker-header d-flex justify-content-between align-items-center mb-3">
        <div class="broker-header__title h6 mb-0">
            Broker / partner cabinet
        </div>
        <div class="broker-dashboard__filters">
                <?php
                $APPLICATION->IncludeComponent(
                    "bitrix:main.ui.filter",
                    "",
                    [
                        "FILTER_ID" => "BROKER_DASHBOARD_PERIOD_FILTER",
                        "GRID_ID" => "BROKER_DASHBOARD_PERIOD_GRID",
                        "FILTER" => [
                            [
                                "id" => "PERIOD_PRESET",
                                "name" => "Period",
                                "type" => "list",
                                "items" => [
                                    "current_month" => "Current month",
                                    "last_month" => "Last month",
                                    "quarter" => "Quarter",
                                    "year" => "Year",
                                    "custom" => "Custom period",
                                ],
                                "default" => true,
                            ],
                            [
                                "id" => "PERIOD",
                                "name" => "Date range",
                                "type" => "date",
                            ],
                        ],
                        "ENABLE_LABEL" => true,
                        "ENABLE_LIVE_SEARCH" => false,
                    ]
                );
                ?>
            </div>
    </div>

    <div class="broker-dashboard mb-3">
        <div class="broker-dashboard__row d-flex flex-column flex-xl-row">
            <div class="broker-dashboard__summary flex-grow-1">
                <div class="row row-cols-1 row-cols-md-4 g-3">
                    <div class="col">
                        <div class="p-3 border rounded h-100 d-flex flex-column justify-content-center align-items-center">
                            <div class="small text-muted text-center">Deals in progress</div>
                            <div class="h5 mb-0" id="broker-sum-in-progress">0 AED</div>
                        </div>
                    </div>
                    <div class="col">
                        <div class="p-3 border rounded h-100 d-flex flex-column justify-content-center align-items-center">
                            <div class="small text-muted text-center">Deals in booking</div>
                            <div class="h5 mb-0" id="broker-sum-booking">0 AED</div>
                        </div>
                    </div>
                    <div class="col">
                        <div class="p-3 border rounded h-100 d-flex flex-column justify-content-center align-items-center">
                            <div class="small text-muted text-center">Deals in SPA / Investment agreement</div>
                            <div class="h5 mb-0" id="broker-sum-spa">0 AED</div>
                        </div>
                    </div>
                    <div class="col">
                        <div class="p-3 border rounded h-100 d-flex flex-column justify-content-center align-items-center">
                            <div class="small text-muted text-center">Paid commissions</div>
                            <div class="h5 mb-0" id="broker-sum-commissions">0 AED</div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="broker-tabs">
        <ul class="nav nav-tabs mb-3" id="brokerTabs" role="tablist">
            <li class="nav-item" role="presentation">
                <button class="nav-link active"
                        id="units-tab"
                        data-bs-toggle="tab"
                        data-bs-target="#units"
                        type="button"
                        role="tab">
                    Units availability
                </button>
            </li>
            <li class="nav-item" role="presentation">
                <button class="nav-link"
                        id="deal-tab"
                        data-bs-toggle="tab"
                        data-bs-target="#deal"
                        type="button"
                        role="tab">
                    Deal registration
                </button>
            </li>
            <li class="nav-item" role="presentation">
                <button class="nav-link"
                        id="commissions-tab"
                        data-bs-toggle="tab"
                        data-bs-target="#commissions"
                        type="button"
                        role="tab">
                    Commission request
                </button>
            </li>
            <li class="nav-item" role="presentation">
                <button class="nav-link"
                        id="analytics-tab"
                        data-bs-toggle="tab"
                        data-bs-target="#analytics"
                        type="button"
                        role="tab">
                    Analytics
                </button>
            </li>
        </ul>

        <div class="tab-content">
            <div class="tab-pane fade show active" id="units" role="tabpanel">
                <?php include $_SERVER["DOCUMENT_ROOT"] . "/include/broker_units_tab.php"; ?>
            </div>

            <div class="tab-pane fade" id="deal" role="tabpanel">
                <?php include $_SERVER["DOCUMENT_ROOT"] . "/include/broker_deal_tab.php"; ?>
            </div>

            <div class="tab-pane fade" id="commissions" role="tabpanel">
                <?php include $_SERVER["DOCUMENT_ROOT"] . "/include/broker_commissions_tab.php"; ?>
            </div>

            <div class="tab-pane fade" id="analytics" role="tabpanel">
                <?php include $_SERVER["DOCUMENT_ROOT"] . "/include/broker_analytics_tab.php"; ?>
            </div>
        </div>
    </div>

    <div class="modal fade" id="commissionRequestModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Commission request</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <p>
                        Your commission for deal №
                        <span data-broker-commission-deal-id></span>
                        is
                        <span data-broker-commission-amount></span>
                        AED.
                    </p>
                    <p>Confirm commission payout request?</p>
                </div>
                <div class="modal-footer">
                    <button type="button"
                            class="btn btn-secondary"
                            data-bs-dismiss="modal">
                        Cancel
                    </button>
                    <button type="button"
                            class="btn btn-primary"
                            id="commissionRequestConfirmButton">
                        Confirm
                    </button>
                </div>
            </div>
        </div>
    </div>
</div>

<?php
require($_SERVER["DOCUMENT_ROOT"] . "/bitrix/footer.php");
