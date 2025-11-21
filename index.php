<?php
require($_SERVER["DOCUMENT_ROOT"] . "/include/broker_handler.php");

$broker = broker_require_auth();

require($_SERVER["DOCUMENT_ROOT"] . "/bitrix/header.php");
$APPLICATION->SetTitle("Dashboard");

// Подключение стилей для дашборда
$APPLICATION->SetAdditionalCSS("/assets/css/broker-dashboard.css");
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
                    "GRID_ID"   => "BROKER_DASHBOARD_PERIOD_GRID",
                    "FILTER"    => [
                        [
                            "id"      => "PERIOD_PRESET",
                            "name"    => "Period",
                            "type"    => "list",
                            "items"   => [
                                "current_month" => "Current month",
                                "last_month"    => "Last month",
                                "quarter"       => "Quarter",
                                "year"          => "Year",
                                "custom"        => "Custom period",
                            ],
                            "default" => true,
                        ],
                        [
                            "id"   => "PERIOD",
                            "name" => "Date range",
                            "type" => "date",
                        ],
                    ],
                    "ENABLE_LABEL"       => true,
                    "ENABLE_LIVE_SEARCH" => false,
                ]
            );
            ?>
        </div>
    </div>

    <!-- Основные метрики -->
    <div class="broker-dashboard mb-4">
        <div class="broker-dashboard__row d-flex flex-column flex-xl-row gap-3">
            <div class="broker-dashboard__summary flex-grow-1">
                <div class="row row-cols-1 row-cols-md-4 g-3">
                    <div class="col">
                        <div class="broker-dashboard__metric metric-in-progress">
                            <div class="broker-dashboard__metric-label">Deals in progress</div>
                            <div class="broker-dashboard__metric-value" id="broker-sum-in-progress">0 AED</div>
                            <div class="broker-dashboard__metric-subtext" id="broker-count-in-progress">0 deals</div>
                        </div>
                    </div>
                    <div class="col">
                        <div class="broker-dashboard__metric metric-booking">
                            <div class="broker-dashboard__metric-label">Deals in booking</div>
                            <div class="broker-dashboard__metric-value" id="broker-sum-booking">0 AED</div>
                            <div class="broker-dashboard__metric-subtext" id="broker-count-booking">0 deals</div>
                        </div>
                    </div>
                    <div class="col">
                        <div class="broker-dashboard__metric metric-spa">
                            <div class="broker-dashboard__metric-label">Deals in SPA</div>
                            <div class="broker-dashboard__metric-value" id="broker-sum-spa">0 AED</div>
                            <div class="broker-dashboard__metric-subtext" id="broker-count-spa">0 deals</div>
                        </div>
                    </div>
                    <div class="col">
                        <div class="broker-dashboard__metric metric-commissions">
                            <div class="broker-dashboard__metric-label">Total commissions</div>
                            <div class="broker-dashboard__metric-value" id="broker-sum-commissions">0 AED</div>
                            <div class="broker-dashboard__metric-subtext" id="broker-commission-rate">0% rate</div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Дашборды аналитики -->
    <div class="broker-analytics mb-4">
        <div class="row g-4">
            <!-- Топ агентов -->
            <div class="col-12 col-xl-6">
                <div class="card h-100">
                    <div class="card-header">
                        <h6 class="mb-0">Top Agents by Deals Amount</h6>
                    </div>
                    <div class="card-body">
                        <div id="top-agents-chart" style="min-height: 300px;">
                            <div style="text-align: center; padding: 50px;">
                                <div class="spinner-border text-primary" role="status">
                                    <span class="visually-hidden">Loading...</span>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Топ агентств -->
            <div class="col-12 col-xl-6">
                <div class="card h-100">
                    <div class="card-header">
                        <h6 class="mb-0">Top Agencies by Total Deals</h6>
                    </div>
                    <div class="card-body">
                        <div id="top-agencies-chart" style="min-height: 300px;">
                            <div style="text-align: center; padding: 50px;">
                                <div class="spinner-border text-primary" role="status">
                                    <span class="visually-hidden">Loading...</span>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Динамика по периодам -->
    <div class="broker-analytics mb-4">
        <div class="card">
            <div class="card-header">
                <h6 class="mb-0">Deals & Commissions Dynamics</h6>
            </div>
            <div class="card-body">
                <div id="dynamics-chart" style="min-height: 350px;">
                    <div style="text-align: center; padding: 50px;">
                        <div class="spinner-border text-primary" role="status">
                            <span class="visually-hidden">Loading...</span>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Детальные таблицы -->
    <div class="broker-analytics mb-4">
        <div class="row g-4">
            <!-- Таблица агентов -->
            <div class="col-12">
                <div class="card">
                    <div class="card-header">
                        <h6 class="mb-0">Agents Rating</h6>
                    </div>
                    <div class="card-body">
                        <?php include $_SERVER["DOCUMENT_ROOT"] . "/include/broker_dashboard_agents_grid.php"; ?>
                    </div>
                </div>
            </div>

            <!-- Таблица агентств -->
            <div class="col-12">
                <div class="card">
                    <div class="card-header">
                        <h6 class="mb-0">Agencies Rating</h6>
                    </div>
                    <div class="card-body">
                        <?php include $_SERVER["DOCUMENT_ROOT"] . "/include/broker_dashboard_agencies_grid.php"; ?>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Вкладки с данными -->
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
                        id="deals-tab"
                        data-bs-toggle="tab"
                        data-bs-target="#deals"
                        type="button"
                        role="tab">
                    Deals
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
        </ul>

        <div class="tab-content">
            <div class="tab-pane fade show active" id="units" role="tabpanel">
                <?php include $_SERVER["DOCUMENT_ROOT"] . "/include/broker_units_tab.php"; ?>
            </div>

            <div class="tab-pane fade" id="deals" role="tabpanel">
                <?php include $_SERVER["DOCUMENT_ROOT"] . "/include/broker_deals_list_tab.php"; ?>
            </div>

            <div class="tab-pane fade" id="commissions" role="tabpanel">
                <?php include $_SERVER["DOCUMENT_ROOT"] . "/include/broker_commissions_tab.php"; ?>
            </div>
        </div>
    </div>
</div>

<!-- Подключение библиотек для графиков - ВАЖНО: загружается перед нашим скриптом -->
<script src="https://cdn.plot.ly/plotly-2.27.0.min.js" charset="utf-8"></script>

<script>
// Ждем полной загрузки Plotly и DOM
(function() {
    'use strict';
    
    // Функция инициализации дашборда
    function initDashboard() {
        console.log('Initializing dashboard...');
        
        // Проверка загрузки Plotly
        if (typeof Plotly === 'undefined') {
            console.error('Plotly.js не загружен! Повторная попытка через 1 секунду...');
            setTimeout(initDashboard, 1000);
            return;
        }
        
        console.log('Plotly.js загружен успешно');
        
        // Загрузка данных дашборда
        loadDashboardData();
        
        // Обновление при изменении фильтра
        if (typeof BX !== 'undefined' && BX.addCustomEvent) {
            BX.addCustomEvent('BX.Main.Filter:apply', function() {
                console.log('Filter applied, reloading data...');
                loadDashboardData();
            });
        }
    }
    
    function loadDashboardData() {
        const filterData = getFilterData();
        
        console.log('Loading dashboard data with filter:', filterData);
        
        // Загрузка основных метрик
        fetch('/broker/ajax/dashboard_metrics.php?' + new URLSearchParams(filterData))
            .then(response => {
                console.log('Response status:', response.status);
                if (!response.ok) {
                    throw new Error('Network response was not ok: ' + response.status);
                }
                return response.json();
            })
            .then(data => {
                console.log('Dashboard data loaded:', data);
                updateMainMetrics(data);
                updateAgentsChart(data.topAgents || []);
                updateAgenciesChart(data.topAgencies || []);
                updateDynamicsChart(data.dynamics || []);
            })
            .catch(error => {
                console.error('Error loading dashboard data:', error);
                showError('Failed to load dashboard data: ' + error.message);
            });
    }
    
    function getFilterData() {
        if (typeof BX === 'undefined' || !BX.Main || !BX.Main.filterManager) {
            return {
                periodPreset: 'current_month',
                dateFrom: '',
                dateTo: ''
            };
        }
        
        const filterManager = BX.Main.filterManager.getById('BROKER_DASHBOARD_PERIOD_FILTER');
        if (!filterManager) {
            return {
                periodPreset: 'current_month',
                dateFrom: '',
                dateTo: ''
            };
        }
        
        const filterFields = filterManager.getFilterFieldsValues();
        return {
            periodPreset: filterFields.PERIOD_PRESET || 'current_month',
            dateFrom: filterFields.PERIOD_from || '',
            dateTo: filterFields.PERIOD_to || ''
        };
    }
    
    function updateMainMetrics(data) {
        if (!data || !data.metrics) {
            console.error('No metrics data');
            return;
        }
        
        const metrics = data.metrics;
        
        document.getElementById('broker-sum-in-progress').textContent = 
            formatMoney(metrics.inProgress.amount) + ' AED';
        document.getElementById('broker-count-in-progress').textContent = 
            metrics.inProgress.count + ' deals';
        
        document.getElementById('broker-sum-booking').textContent = 
            formatMoney(metrics.booking.amount) + ' AED';
        document.getElementById('broker-count-booking').textContent = 
            metrics.booking.count + ' deals';
        
        document.getElementById('broker-sum-spa').textContent = 
            formatMoney(metrics.spa.amount) + ' AED';
        document.getElementById('broker-count-spa').textContent = 
            metrics.spa.count + ' deals';
        
        document.getElementById('broker-sum-commissions').textContent = 
            formatMoney(metrics.commissions.amount) + ' AED';
        document.getElementById('broker-commission-rate').textContent = 
            metrics.commissions.rate + '% rate';
    }
    
    function updateAgentsChart(data) {
        const chartDiv = document.getElementById('top-agents-chart');
        if (!chartDiv) {
            console.error('Chart div not found: top-agents-chart');
            return;
        }
        
        console.log('Updating agents chart with data:', data);
        
        if (!data || data.length === 0) {
            chartDiv.innerHTML = '<div style="text-align: center; padding: 50px; color: #6c757d;"><i class="bi bi-info-circle" style="font-size: 2rem;"></i><br>No data available</div>';
            return;
        }
        
        const agents = data.slice(0, 10);
        
        const trace = {
            x: agents.map(a => a.dealsSum),
            y: agents.map(a => a.name),
            type: 'bar',
            orientation: 'h',
            marker: {
                color: '#0d6efd'
            },
            text: agents.map(a => formatMoney(a.dealsSum) + ' AED'),
            textposition: 'auto',
            hovertemplate: '<b>%{y}</b><br>' +
                          'Deals: %{x:,.0f} AED<br>' +
                          'Commission: %{customdata:,.0f} AED<extra></extra>',
            customdata: agents.map(a => a.commissionsSum)
        };
        
        const layout = {
            margin: { l: 150, r: 50, t: 20, b: 50 },
            xaxis: { title: 'Amount (AED)' },
            yaxis: { autorange: 'reversed' },
            height: 300,
            font: { family: 'Arial, sans-serif' }
        };
        
        const config = {
            responsive: true,
            displayModeBar: false
        };
        
        try {
            Plotly.newPlot(chartDiv, [trace], layout, config);
            console.log('Agents chart created successfully');
        } catch (error) {
            console.error('Error creating agents chart:', error);
            chartDiv.innerHTML = '<div style="text-align: center; padding: 50px; color: #dc3545;">Error loading chart: ' + error.message + '</div>';
        }
    }
    
    function updateAgenciesChart(data) {
        const chartDiv = document.getElementById('top-agencies-chart');
        if (!chartDiv) {
            console.error('Chart div not found: top-agencies-chart');
            return;
        }
        
        console.log('Updating agencies chart with data:', data);
        
        if (!data || data.length === 0) {
            chartDiv.innerHTML = '<div style="text-align: center; padding: 50px; color: #6c757d;"><i class="bi bi-info-circle" style="font-size: 2rem;"></i><br>No data available</div>';
            return;
        }
        
        const agencies = data.slice(0, 10);
        
        const trace = {
            x: agencies.map(a => a.dealsSum),
            y: agencies.map(a => a.name),
            type: 'bar',
            orientation: 'h',
            marker: {
                color: '#198754'
            },
            text: agencies.map(a => formatMoney(a.dealsSum) + ' AED'),
            textposition: 'auto',
            hovertemplate: '<b>%{y}</b><br>' +
                          'Deals: %{x:,.0f} AED<br>' +
                          'Commission: %{customdata:,.0f} AED<extra></extra>',
            customdata: agencies.map(a => a.commissionsSum)
        };
        
        const layout = {
            margin: { l: 150, r: 50, t: 20, b: 50 },
            xaxis: { title: 'Amount (AED)' },
            yaxis: { autorange: 'reversed' },
            height: 300,
            font: { family: 'Arial, sans-serif' }
        };
        
        const config = {
            responsive: true,
            displayModeBar: false
        };
        
        try {
            Plotly.newPlot(chartDiv, [trace], layout, config);
            console.log('Agencies chart created successfully');
        } catch (error) {
            console.error('Error creating agencies chart:', error);
            chartDiv.innerHTML = '<div style="text-align: center; padding: 50px; color: #dc3545;">Error loading chart: ' + error.message + '</div>';
        }
    }
    
    function updateDynamicsChart(data) {
        const chartDiv = document.getElementById('dynamics-chart');
        if (!chartDiv) {
            console.error('Chart div not found: dynamics-chart');
            return;
        }
        
        console.log('Updating dynamics chart with data:', data);
        
        if (!data || data.length === 0) {
            chartDiv.innerHTML = '<div style="text-align: center; padding: 50px; color: #6c757d;"><i class="bi bi-info-circle" style="font-size: 2rem;"></i><br>No data available</div>';
            return;
        }
        
        const trace1 = {
            x: data.map(d => d.period),
            y: data.map(d => d.dealsSum),
            type: 'scatter',
            mode: 'lines+markers',
            name: 'Deals Amount',
            line: { color: '#0d6efd', width: 2 },
            marker: { size: 8 },
            yaxis: 'y1'
        };
        
        const trace2 = {
            x: data.map(d => d.period),
            y: data.map(d => d.commissionsSum),
            type: 'scatter',
            mode: 'lines+markers',
            name: 'Commissions',
            line: { color: '#198754', width: 2 },
            marker: { size: 8 },
            yaxis: 'y2'
        };
        
        const layout = {
            margin: { l: 60, r: 60, t: 30, b: 50 },
            xaxis: { title: 'Period' },
            yaxis: {
                title: 'Deals Amount (AED)',
                side: 'left'
            },
            yaxis2: {
                title: 'Commissions (AED)',
                overlaying: 'y',
                side: 'right'
            },
            legend: {
                x: 0,
                y: 1.1,
                orientation: 'h'
            },
            height: 350,
            font: { family: 'Arial, sans-serif' }
        };
        
        const config = {
            responsive: true,
            displayModeBar: false
        };
        
        try {
            Plotly.newPlot(chartDiv, [trace1, trace2], layout, config);
            console.log('Dynamics chart created successfully');
        } catch (error) {
            console.error('Error creating dynamics chart:', error);
            chartDiv.innerHTML = '<div style="text-align: center; padding: 50px; color: #dc3545;">Error loading chart: ' + error.message + '</div>';
        }
    }
    
    function formatMoney(value) {
        return new Intl.NumberFormat('en-US').format(Math.round(value));
    }
    
    function showError(message) {
        console.error(message);
        const charts = ['top-agents-chart', 'top-agencies-chart', 'dynamics-chart'];
        charts.forEach(id => {
            const div = document.getElementById(id);
            if (div) {
                div.innerHTML = '<div style="text-align: center; padding: 50px; color: #dc3545;"><i class="bi bi-exclamation-triangle" style="font-size: 2rem;"></i><br>' + message + '</div>';
            }
        });
    }
    
    // Запуск инициализации при загрузке DOM
    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', initDashboard);
    } else {
        // DOM уже загружен
        initDashboard();
    }
})();
</script>

<?php
require($_SERVER["DOCUMENT_ROOT"] . "/bitrix/footer.php");