<?php

require $_SERVER['DOCUMENT_ROOT'] . '/bitrix/modules/main/include/prolog_before.php';
require_once $_SERVER['DOCUMENT_ROOT'] . '/include/broker_handler.php';

use Bitrix\Main\Loader;
use Bitrix\Crm\Service;

global $USER;
if (!$USER || !$USER->IsAdmin()) {
    http_response_code(403);
    die('Access denied');
}

if (!Loader::includeModule('crm')) {
    http_response_code(500);
    die('CRM module not available');
}

$reportType = $_GET['type'] ?? 'excel';
$periodPreset = $_GET['period'] ?? 'current_month';
$dateFrom = $_GET['dateFrom'] ?? '';
$dateTo = $_GET['dateTo'] ?? '';

list($dateFromObj, $dateToObj) = calculatePeriod($periodPreset, $dateFrom, $dateTo);

$reportData = generateCommissionReport($dateFromObj, $dateToObj);

if ($reportType === 'excel') {
    generateExcelReport($reportData, $dateFromObj, $dateToObj);
} else {
    generateCsvReport($reportData, $dateFromObj, $dateToObj);
}

function calculatePeriod($preset, $dateFrom, $dateTo) {
    $now = new DateTime();
    
    switch ($preset) {
        case 'current_month':
            $from = new DateTime('first day of this month 00:00:00');
            $to = new DateTime('last day of this month 23:59:59');
            break;
            
        case 'last_month':
            $from = new DateTime('first day of last month 00:00:00');
            $to = new DateTime('last day of last month 23:59:59');
            break;
            
        case 'quarter':
            $currentMonth = (int)$now->format('n');
            $quarterStartMonth = floor(($currentMonth - 1) / 3) * 3 + 1;
            $from = new DateTime("$quarterStartMonth/1/" . $now->format('Y') . " 00:00:00");
            $to = clone $from;
            $to->modify('+3 months -1 day');
            $to->setTime(23, 59, 59);
            break;
            
        case 'year':
            $from = new DateTime('first day of January ' . $now->format('Y') . ' 00:00:00');
            $to = new DateTime('last day of December ' . $now->format('Y') . ' 23:59:59');
            break;
            
        case 'custom':
            if ($dateFrom && $dateTo) {
                $from = new DateTime($dateFrom . ' 00:00:00');
                $to = new DateTime($dateTo . ' 23:59:59');
            } else {
                $from = new DateTime('first day of this month 00:00:00');
                $to = new DateTime('last day of this month 23:59:59');
            }
            break;
            
        default:
            $from = new DateTime('first day of this month 00:00:00');
            $to = new DateTime('last day of this month 23:59:59');
    }
    
    return [$from, $to];
}

function generateCommissionReport($dateFrom, $dateTo) {
    $filter = [
        'CATEGORY_ID' => 0,
        'CHECK_PERMISSIONS' => 'N',
        '>=DATE_CREATE' => $dateFrom->format('d.m.Y H:i:s'),
        '<=DATE_CREATE' => $dateTo->format('d.m.Y H:i:s'),
        '!STAGE_ID' => ['LOSE'],
    ];
    
    $select = [
        'ID',
        'TITLE',
        'STAGE_ID',
        'OPPORTUNITY',
        'CURRENCY_ID',
        'DATE_CREATE',
        'CONTACT_ID',
        'UF_CRM_1763708684',
        'UF_CRM_1756215544',
        'UF_CRM_1763304021510',
        'UF_CRM_1756215507',
        'UF_CRM_1756215456',
    ];
    
    $reportData = [];
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
        'APOLOGY'            => 'Resale',
    ];
    
    $res = CCrmDeal::GetListEx(
        ['DATE_CREATE' => 'DESC'],
        $filter,
        false,
        false,
        $select
    );
    
    while ($row = $res->Fetch()) {
        $dealId = (int)$row['ID'];
        $amount = (float)$row['OPPORTUNITY'];
        $commission = (float)($row['UF_CRM_1763304021510'] ?? 0);
        $brokerName = (string)$row['UF_CRM_1763708684'];
        $agencyName = (string)$row['UF_CRM_1756215544'];
        $stageId = (string)$row['STAGE_ID'];
        $stageName = $stageMap[$stageId] ?? $stageId;
        
        if ($amount <= 0 || $commission <= 0) {
            continue;
        }
        
        $commissionAmount = ($amount * $commission) / 100;
        
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
        
        $clientName = '';
        $contactId = (int)$row['CONTACT_ID'];
        if ($contactId > 0) {
            $contact = CCrmContact::GetByID($contactId);
            if ($contact) {
                $clientName = trim($contact['NAME'] . ' ' . $contact['LAST_NAME']);
            }
        }
        
        $reportData[] = [
            'deal_id' => $dealId,
            'deal_number' => 'D-' . $dealId,
            'date' => $row['DATE_CREATE'],
            'broker_name' => $brokerName,
            'agency_name' => $agencyName,
            'client_name' => $clientName,
            'project' => $projectName,
            'unit' => $unitName,
            'stage' => $stageName,
            'deal_amount' => $amount,
            'currency' => $row['CURRENCY_ID'] ?: 'AED',
            'commission_rate' => $commission,
            'commission_amount' => $commissionAmount,
            'status' => getPaymentStatus($stageId)
        ];
    }
    
    return $reportData;
}

function getPaymentStatus($stageId) {
    $paidStages = ['WON', 'FINAL_INVOICE'];
    $pendingStages = ['EXECUTING', 'UC_IGBPMT'];
    
    if (in_array($stageId, $paidStages)) {
        return 'Paid';
    } elseif (in_array($stageId, $pendingStages)) {
        return 'Pending payment';
    } else {
        return 'Not due';
    }
}

function generateCsvReport($data, $dateFrom, $dateTo) {
    $fileName = 'commission_report_' . $dateFrom->format('Y-m-d') . '_' . $dateTo->format('Y-m-d') . '.csv';
    
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="' . $fileName . '"');
    
    $output = fopen('php://output', 'w');
    
    fprintf($output, chr(0xEF).chr(0xBB).chr(0xBF));
    
    fputcsv($output, [
        'Deal #',
        'Date',
        'Broker',
        'Agency',
        'Client',
        'Project',
        'Unit',
        'Stage',
        'Deal Amount',
        'Currency',
        'Commission %',
        'Commission Amount',
        'Payment Status'
    ]);
    
    $totalAmount = 0;
    $totalCommission = 0;
    
    foreach ($data as $item) {
        fputcsv($output, [
            $item['deal_number'],
            $item['date'],
            $item['broker_name'],
            $item['agency_name'],
            $item['client_name'],
            $item['project'],
            $item['unit'],
            $item['stage'],
            $item['deal_amount'],
            $item['currency'],
            $item['commission_rate'] . '%',
            $item['commission_amount'],
            $item['status']
        ]);
        
        $totalAmount += $item['deal_amount'];
        $totalCommission += $item['commission_amount'];
    }
    
    fputcsv($output, []);
    fputcsv($output, [
        'TOTAL:',
        '',
        '',
        '',
        '',
        '',
        '',
        '',
        $totalAmount,
        '',
        '',
        $totalCommission,
        ''
    ]);
    
    fclose($output);
    exit;
}

function generateExcelReport($data, $dateFrom, $dateTo) {
    $fileName = 'commission_report_' . $dateFrom->format('Y-m-d') . '_' . $dateTo->format('Y-m-d') . '.xls';
    
    header('Content-Type: application/vnd.ms-excel');
    header('Content-Disposition: attachment; filename="' . $fileName . '"');
    
    echo '<html xmlns:x="urn:schemas-microsoft-com:office:excel">';
    echo '<head><meta charset="UTF-8"></head>';
    echo '<body>';
    echo '<table border="1">';
    
    echo '<tr>';
    echo '<th>Deal #</th>';
    echo '<th>Date</th>';
    echo '<th>Broker</th>';
    echo '<th>Agency</th>';
    echo '<th>Client</th>';
    echo '<th>Project</th>';
    echo '<th>Unit</th>';
    echo '<th>Stage</th>';
    echo '<th>Deal Amount</th>';
    echo '<th>Currency</th>';
    echo '<th>Commission %</th>';
    echo '<th>Commission Amount</th>';
    echo '<th>Payment Status</th>';
    echo '</tr>';
    
    $totalAmount = 0;
    $totalCommission = 0;
    
    foreach ($data as $item) {
        echo '<tr>';
        echo '<td>' . htmlspecialchars($item['deal_number']) . '</td>';
        echo '<td>' . htmlspecialchars($item['date']) . '</td>';
        echo '<td>' . htmlspecialchars($item['broker_name']) . '</td>';
        echo '<td>' . htmlspecialchars($item['agency_name']) . '</td>';
        echo '<td>' . htmlspecialchars($item['client_name']) . '</td>';
        echo '<td>' . htmlspecialchars($item['project']) . '</td>';
        echo '<td>' . htmlspecialchars($item['unit']) . '</td>';
        echo '<td>' . htmlspecialchars($item['stage']) . '</td>';
        echo '<td>' . number_format($item['deal_amount'], 2) . '</td>';
        echo '<td>' . htmlspecialchars($item['currency']) . '</td>';
        echo '<td>' . $item['commission_rate'] . '%</td>';
        echo '<td>' . number_format($item['commission_amount'], 2) . '</td>';
        echo '<td>' . htmlspecialchars($item['status']) . '</td>';
        echo '</tr>';
        
        $totalAmount += $item['deal_amount'];
        $totalCommission += $item['commission_amount'];
    }
    
    echo '<tr>';
    echo '<td><strong>TOTAL:</strong></td>';
    echo '<td colspan="7"></td>';
    echo '<td><strong>' . number_format($totalAmount, 2) . '</strong></td>';
    echo '<td></td>';
    echo '<td></td>';
    echo '<td><strong>' . number_format($totalCommission, 2) . '</strong></td>';
    echo '<td></td>';
    echo '</tr>';
    
    echo '</table>';
    echo '</body>';
    echo '</html>';
    exit;
}