<?php

require $_SERVER['DOCUMENT_ROOT'] . '/bitrix/modules/main/include/prolog_before.php';
require_once $_SERVER['DOCUMENT_ROOT'] . '/include/broker_handler.php';

use Bitrix\Main\Loader;
use Bitrix\Crm\Service;

header('Content-Type: application/json');

$broker = broker_current();
if ($broker === null) {
    http_response_code(401);
    echo json_encode(['error' => 'Unauthorized']);
    exit;
}

if (!Loader::includeModule('crm')) {
    http_response_code(500);
    echo json_encode(['error' => 'CRM module not available']);
    exit;
}

try {
    $periodPreset = $_GET['periodPreset'] ?? 'current_month';
    $dateFrom = $_GET['dateFrom'] ?? '';
    $dateTo = $_GET['dateTo'] ?? '';
    
    list($dateFromObj, $dateToObj) = calculatePeriod($periodPreset, $dateFrom, $dateTo);
    
    $brokerName = trim((string)($broker['NAME'] ?? '') . ' ' . (string)($broker['LAST_NAME'] ?? ''));
    $brokerEmail = (string)($broker['EMAIL'] ?? '');
    
    $brokerCommissionRate = 0;
    $brokerAgencyName = '';
    
    if ($brokerEmail !== '') {
        $brokerItem = broker_find_item_by_email($brokerEmail);
        if ($brokerItem !== null) {
            $bData = $brokerItem->getData();
            $brokerCommissionRate = (float)($bData['UF_CRM_15_COMMISSION'] ?? 0);
            $brokerAgencyName = (string)($bData['UF_CRM_15_AGENCY_NAME'] ?? '');
        }
    }
    
    $deals = getDealsForPeriod($brokerName, $dateFromObj, $dateToObj);
    $metrics = calculateMainMetrics($deals, $brokerCommissionRate);
    $topAgents = getTopAgents($dateFromObj, $dateToObj);
    $topAgencies = getTopAgencies($dateFromObj, $dateToObj);
    $dynamics = getDynamics($brokerName, $dateFromObj, $dateToObj);
    
    $response = [
        'metrics' => $metrics,
        'topAgents' => $topAgents,
        'topAgencies' => $topAgencies,
        'dynamics' => $dynamics
    ];
    
    echo json_encode($response, JSON_UNESCAPED_UNICODE);
    
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['error' => $e->getMessage()]);
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

function getDealsForPeriod($brokerName, $dateFrom, $dateTo) {
    $filter = [
        'CATEGORY_ID' => 0,
        'CHECK_PERMISSIONS' => 'N',
        '>=DATE_CREATE' => $dateFrom->format('d.m.Y H:i:s'),
        '<=DATE_CREATE' => $dateTo->format('d.m.Y H:i:s'),
    ];
    
    if ($brokerName !== '') {
        $filter['UF_CRM_1763708684'] = $brokerName;
    }
    
    $select = [
        'ID',
        'STAGE_ID',
        'OPPORTUNITY',
        'CURRENCY_ID',
        'DATE_CREATE',
        'UF_CRM_1763708684',
        'UF_CRM_1756215544',
        'UF_CRM_1763304021510',
    ];
    
    $deals = [];
    $res = CCrmDeal::GetListEx(
        ['DATE_CREATE' => 'ASC'],
        $filter,
        false,
        false,
        $select
    );
    
    while ($row = $res->Fetch()) {
        $deals[] = $row;
    }
    
    return $deals;
}

function calculateMainMetrics($deals, $brokerCommissionRate) {
    $inProgress = ['amount' => 0, 'count' => 0];
    $booking = ['amount' => 0, 'count' => 0];
    $spa = ['amount' => 0, 'count' => 0];
    $totalCommissions = 0;
    $inProgressStages = ['NEW', 'PREPARATION', 'UC_U0R404'];
    $bookingStages = ['PREPAYMENT_INVOICE'];
    $spaStages = ['EXECUTING'];
    
    foreach ($deals as $deal) {
        $stageId = (string)$deal['STAGE_ID'];
        $amount = (float)$deal['OPPORTUNITY'];
        $commission = (float)($deal['UF_CRM_1763304021510'] ?? $brokerCommissionRate);
        
        if ($amount <= 0) {
            continue;
        }
        
        $commissionAmount = ($amount * $commission) / 100;
        $totalCommissions += $commissionAmount;
        
        if (in_array($stageId, $inProgressStages)) {
            $inProgress['amount'] += $amount;
            $inProgress['count']++;
        } elseif (in_array($stageId, $bookingStages)) {
            $booking['amount'] += $amount;
            $booking['count']++;
        } elseif (in_array($stageId, $spaStages)) {
            $spa['amount'] += $amount;
            $spa['count']++;
        }
    }
    
    return [
        'inProgress' => $inProgress,
        'booking' => $booking,
        'spa' => $spa,
        'commissions' => [
            'amount' => $totalCommissions,
            'rate' => $brokerCommissionRate
        ]
    ];
}

function getTopAgents($dateFrom, $dateTo) {
    $filter = [
        'CATEGORY_ID' => 0,
        'CHECK_PERMISSIONS' => 'N',
        '>=DATE_CREATE' => $dateFrom->format('d.m.Y H:i:s'),
        '<=DATE_CREATE' => $dateTo->format('d.m.Y H:i:s'),
        '!UF_CRM_1763708684' => false,
    ];
    
    $select = [
        'ID',
        'OPPORTUNITY',
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
        
        if ($brokerName === '' || $amount <= 0) {
            continue;
        }
        
        if (!isset($agentsData[$brokerName])) {
            $agentsData[$brokerName] = [
                'name' => $brokerName,
                'dealsSum' => 0,
                'commissionsSum' => 0,
                'dealsCount' => 0
            ];
        }
        
        $agentsData[$brokerName]['dealsSum'] += $amount;
        $agentsData[$brokerName]['commissionsSum'] += ($amount * $commission) / 100;
        $agentsData[$brokerName]['dealsCount']++;
    }
    
    usort($agentsData, function($a, $b) {
        return $b['dealsSum'] <=> $a['dealsSum'];
    });
    
    return array_slice($agentsData, 0, 15);
}

function getTopAgencies($dateFrom, $dateTo) {
    $filter = [
        'CATEGORY_ID' => 0,
        'CHECK_PERMISSIONS' => 'N',
        '>=DATE_CREATE' => $dateFrom->format('d.m.Y H:i:s'),
        '<=DATE_CREATE' => $dateTo->format('d.m.Y H:i:s'),
        '!UF_CRM_1756215544' => false,
    ];
    
    $select = [
        'ID',
        'OPPORTUNITY',
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
        
        if ($agencyName === '' || $amount <= 0) {
            continue;
        }
        
        if (!isset($agenciesData[$agencyName])) {
            $agenciesData[$agencyName] = [
                'name' => $agencyName,
                'dealsSum' => 0,
                'commissionsSum' => 0,
                'dealsCount' => 0
            ];
        }
        
        $agenciesData[$agencyName]['dealsSum'] += $amount;
        $agenciesData[$agencyName]['commissionsSum'] += ($amount * $commission) / 100;
        $agenciesData[$agencyName]['dealsCount']++;
    }
    
    usort($agenciesData, function($a, $b) {
        return $b['dealsSum'] <=> $a['dealsSum'];
    });
    
    return array_slice($agenciesData, 0, 15);
}

function getDynamics($brokerName, $dateFrom, $dateTo) {
    $diff = $dateFrom->diff($dateTo);
    $totalDays = (int)$diff->format('%a');
    
    if ($totalDays <= 31) {
        $groupBy = 'day';
        $format = 'd M';
    } elseif ($totalDays <= 90) {
        $groupBy = 'week';
        $format = 'W, Y';
    } elseif ($totalDays <= 365) {
        $groupBy = 'month';
        $format = 'M Y';
    } else {
        $groupBy = 'quarter';
        $format = 'Q, Y';
    }
    
    $filter = [
        'CATEGORY_ID' => 0,
        'CHECK_PERMISSIONS' => 'N',
        '>=DATE_CREATE' => $dateFrom->format('d.m.Y H:i:s'),
        '<=DATE_CREATE' => $dateTo->format('d.m.Y H:i:s'),
    ];
    
    if ($brokerName !== '') {
        $filter['UF_CRM_1763708684'] = $brokerName;
    }
    
    $select = [
        'ID',
        'OPPORTUNITY',
        'DATE_CREATE',
        'UF_CRM_1763304021510',
    ];
    
    $dynamicsData = [];
    
    $res = CCrmDeal::GetListEx(
        ['DATE_CREATE' => 'ASC'],
        $filter,
        false,
        false,
        $select
    );
    
    while ($row = $res->Fetch()) {
        $amount = (float)$row['OPPORTUNITY'];
        $commission = (float)($row['UF_CRM_1763304021510'] ?? 0);
        $dateCreate = new DateTime($row['DATE_CREATE']);
        
        if ($amount <= 0) {
            continue;
        }
        
        $period = getPeriodKey($dateCreate, $groupBy);
        
        if (!isset($dynamicsData[$period])) {
            $dynamicsData[$period] = [
                'period' => $period,
                'dealsSum' => 0,
                'commissionsSum' => 0,
                'dealsCount' => 0,
                'date' => $dateCreate
            ];
        }
        
        $dynamicsData[$period]['dealsSum'] += $amount;
        $dynamicsData[$period]['commissionsSum'] += ($amount * $commission) / 100;
        $dynamicsData[$period]['dealsCount']++;
    }
    
    $result = [];
    foreach ($dynamicsData as $key => $data) {
        $result[] = [
            'period' => formatPeriod($data['date'], $format),
            'dealsSum' => $data['dealsSum'],
            'commissionsSum' => $data['commissionsSum'],
            'dealsCount' => $data['dealsCount']
        ];
    }
    
    return $result;
}

function getPeriodKey($date, $groupBy) {
    switch ($groupBy) {
        case 'day':
            return $date->format('Y-m-d');
        case 'week':
            return $date->format('Y-W');
        case 'month':
            return $date->format('Y-m');
        case 'quarter':
            $quarter = ceil((int)$date->format('n') / 3);
            return $date->format('Y') . '-Q' . $quarter;
        default:
            return $date->format('Y-m-d');
    }
}

function formatPeriod($date, $format) {
    if (strpos($format, 'Q') !== false) {
        $quarter = ceil((int)$date->format('n') / 3);
        return 'Q' . $quarter . ' ' . $date->format('Y');
    }
    return $date->format($format);
}