<?php
require $_SERVER['DOCUMENT_ROOT'] . '/bitrix/modules/main/include/prolog_before.php';
require_once $_SERVER['DOCUMENT_ROOT'] . '/include/broker_handler.php';

use Bitrix\Main\Loader;
use Bitrix\Main\Mail\Event;
use Bitrix\Crm\Service;

if (!Loader::includeModule('crm')) {
    die('CRM module not available');
}

$logFile = $_SERVER['DOCUMENT_ROOT'] . '/logs/dashboard_update_' . date('Y-m') . '.log';

logMessage($logFile, "=== Starting daily dashboard update ===");

try {
    $stats = updateDashboardStatistics();
    logMessage($logFile, "Statistics updated: " . json_encode($stats));
    
    $reportData = generateDailyCommissionReport();
    logMessage($logFile, "Commission report generated: " . count($reportData) . " records");
    
    sendDailyReport($stats, $reportData);
    logMessage($logFile, "Daily report sent to administrators");
    
    cleanupOldData();
    logMessage($logFile, "Old data cleaned up");
    
    logMessage($logFile, "=== Daily dashboard update completed successfully ===\n");
    
} catch (Exception $e) {
    logMessage($logFile, "ERROR: " . $e->getMessage());
    logMessage($logFile, "Stack trace: " . $e->getTraceAsString() . "\n");
}

function updateDashboardStatistics() {
    $now = new DateTime();
    $firstDayOfMonth = new DateTime('first day of this month 00:00:00');
    
    $filter = [
        'CATEGORY_ID' => 0,
        'CHECK_PERMISSIONS' => 'N',
        '>=DATE_CREATE' => $firstDayOfMonth->format('d.m.Y H:i:s'),
    ];
    
    $select = [
        'ID',
        'STAGE_ID',
        'OPPORTUNITY',
        'UF_CRM_1763304021510',
    ];
    
    $stats = [
        'total_deals' => 0,
        'active_deals' => 0,
        'won_deals' => 0,
        'total_amount' => 0,
        'total_commission' => 0,
        'in_progress' => 0,
        'booking' => 0,
        'spa' => 0,
    ];
    
    $res = CCrmDeal::GetListEx(
        [],
        $filter,
        false,
        false,
        $select
    );
    
    while ($row = $res->Fetch()) {
        $stageId = (string)$row['STAGE_ID'];
        $amount = (float)$row['OPPORTUNITY'];
        $commission = (float)($row['UF_CRM_1763304021510'] ?? 0);
        
        $stats['total_deals']++;
        
        if ($amount > 0) {
            $stats['total_amount'] += $amount;
            $stats['total_commission'] += ($amount * $commission) / 100;
        }
        
        if ($stageId === 'WON') {
            $stats['won_deals']++;
        } elseif (!in_array($stageId, ['WON', 'LOSE'])) {
            $stats['active_deals']++;
        }
        
        if (in_array($stageId, ['NEW', 'PREPARATION', 'UC_U0R404'])) {
            $stats['in_progress']++;
        } elseif ($stageId === 'PREPAYMENT_INVOICE') {
            $stats['booking']++;
        } elseif ($stageId === 'EXECUTING') {
            $stats['spa']++;
        }
    }
    
    $cacheFile = $_SERVER['DOCUMENT_ROOT'] . '/upload/dashboard_cache.json';
    file_put_contents($cacheFile, json_encode($stats, JSON_UNESCAPED_UNICODE));
    
    return $stats;
}

function generateDailyCommissionReport() {
    $yesterday = new DateTime('yesterday 00:00:00');
    $today = new DateTime('today 00:00:00');
    
    $filter = [
        'CATEGORY_ID' => 0,
        'CHECK_PERMISSIONS' => 'N',
        '>=DATE_CREATE' => $yesterday->format('d.m.Y H:i:s'),
        '<DATE_CREATE' => $today->format('d.m.Y H:i:s'),
    ];
    
    $select = [
        'ID',
        'TITLE',
        'STAGE_ID',
        'OPPORTUNITY',
        'DATE_CREATE',
        'UF_CRM_1763708684',
        'UF_CRM_1756215544',
        'UF_CRM_1763304021510',
    ];
    
    $reportData = [];
    
    $res = CCrmDeal::GetListEx(
        ['DATE_CREATE' => 'DESC'],
        $filter,
        false,
        false,
        $select
    );
    
    while ($row = $res->Fetch()) {
        $amount = (float)$row['OPPORTUNITY'];
        $commission = (float)($row['UF_CRM_1763304021510'] ?? 0);
        
        if ($amount <= 0 || $commission <= 0) {
            continue;
        }
        
        $reportData[] = [
            'deal_id' => (int)$row['ID'],
            'broker_name' => (string)$row['UF_CRM_1763708684'],
            'agency_name' => (string)$row['UF_CRM_1756215544'],
            'amount' => $amount,
            'commission_rate' => $commission,
            'commission_amount' => ($amount * $commission) / 100,
            'date' => $row['DATE_CREATE']
        ];
    }
    
    return $reportData;
}

function sendDailyReport($stats, $reportData) {
    $adminEmails = [];
    $rsUsers = CUser::GetList(
        'ID',
        'ASC',
        ['GROUPS_ID' => [1]],
        ['FIELDS' => ['ID', 'EMAIL']]
    );
    
    while ($user = $rsUsers->Fetch()) {
        if (!empty($user['EMAIL'])) {
            $adminEmails[] = $user['EMAIL'];
        }
    }
    
    if (empty($adminEmails)) {
        return;
    }
    
    $reportHtml = generateReportHtml($stats, $reportData);
    
    foreach ($adminEmails as $email) {
        Event::send([
            'EVENT_NAME' => 'BROKER_DAILY_REPORT',
            'LID' => SITE_ID,
            'C_FIELDS' => [
                'EMAIL_TO' => $email,
                'REPORT_DATE' => date('d.m.Y'),
                'REPORT_HTML' => $reportHtml,
            ],
        ]);
    }
}

function generateReportHtml($stats, $reportData) {
    $html = '<html><body style="font-family: Arial, sans-serif;">';
    $html .= '<h2>Daily Broker Dashboard Report</h2>';
    $html .= '<p>Report date: ' . date('d.m.Y') . '</p>';
    
    $html .= '<h3>Statistics (Current Month)</h3>';
    $html .= '<table border="1" cellpadding="10" cellspacing="0" style="border-collapse: collapse;">';
    $html .= '<tr><td><strong>Total Deals</strong></td><td>' . $stats['total_deals'] . '</td></tr>';
    $html .= '<tr><td><strong>Active Deals</strong></td><td>' . $stats['active_deals'] . '</td></tr>';
    $html .= '<tr><td><strong>Won Deals</strong></td><td>' . $stats['won_deals'] . '</td></tr>';
    $html .= '<tr><td><strong>In Progress</strong></td><td>' . $stats['in_progress'] . '</td></tr>';
    $html .= '<tr><td><strong>Booking</strong></td><td>' . $stats['booking'] . '</td></tr>';
    $html .= '<tr><td><strong>SPA</strong></td><td>' . $stats['spa'] . '</td></tr>';
    $html .= '<tr><td><strong>Total Amount</strong></td><td>' . number_format($stats['total_amount'], 0, '.', ' ') . ' AED</td></tr>';
    $html .= '<tr><td><strong>Total Commission</strong></td><td>' . number_format($stats['total_commission'], 0, '.', ' ') . ' AED</td></tr>';
    $html .= '</table>';
    
    if (!empty($reportData)) {
        $html .= '<h3>New Deals (Yesterday)</h3>';
        $html .= '<table border="1" cellpadding="10" cellspacing="0" style="border-collapse: collapse;">';
        $html .= '<tr>';
        $html .= '<th>Deal ID</th>';
        $html .= '<th>Broker</th>';
        $html .= '<th>Agency</th>';
        $html .= '<th>Amount</th>';
        $html .= '<th>Commission</th>';
        $html .= '</tr>';
        
        foreach ($reportData as $item) {
            $html .= '<tr>';
            $html .= '<td>D-' . $item['deal_id'] . '</td>';
            $html .= '<td>' . htmlspecialchars($item['broker_name']) . '</td>';
            $html .= '<td>' . htmlspecialchars($item['agency_name']) . '</td>';
            $html .= '<td>' . number_format($item['amount'], 0, '.', ' ') . ' AED</td>';
            $html .= '<td>' . number_format($item['commission_amount'], 0, '.', ' ') . ' AED (' . $item['commission_rate'] . '%)</td>';
            $html .= '</tr>';
        }
        
        $html .= '</table>';
    }
    
    $html .= '</body></html>';
    
    return $html;
}

function cleanupOldData() {
    $cacheDir = $_SERVER['DOCUMENT_ROOT'] . '/upload/cache/';
    if (!is_dir($cacheDir)) {
        return;
    }
    
    $files = glob($cacheDir . 'dashboard_*.json');
    $now = time();
    
    foreach ($files as $file) {
        if (is_file($file)) {
            if ($now - filemtime($file) >= 30 * 24 * 3600) {
                unlink($file);
            }
        }
    }
}

function logMessage($logFile, $message) {
    $logDir = dirname($logFile);
    if (!is_dir($logDir)) {
        mkdir($logDir, 0755, true);
    }
    
    file_put_contents(
        $logFile,
        date('Y-m-d H:i:s') . ' ' . $message . "\n",
        FILE_APPEND
    );
}