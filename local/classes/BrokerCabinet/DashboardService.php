<?php

namespace BrokerCabinet;

use Bitrix\Main\Loader;

class DashboardService
{
    public function getStats(array $broker, array $filter = []): array
    {
        if (!Loader::includeModule('crm')) {
            return $this->getEmptyStats();
        }

        $brokerName = trim($broker['NAME'] . ' ' . $broker['LAST_NAME']);
        if ($brokerName === '') {
            return $this->getEmptyStats();
        }

        $dateFilter = $this->prepareDateFilter($filter);

        $baseFilter = [
            'CATEGORY_ID' => Config::DEALS_CATEGORY_ID,
            Config::DEAL_FIELD_BROKER => $brokerName,
            'CHECK_PERMISSIONS' => 'N',
        ];

        if (!empty($dateFilter)) {
            $baseFilter = array_merge($baseFilter, $dateFilter);
        }

        $stats = [
            'in_progress' => 0,
            'booking' => 0,
            'spa' => 0,
            'commissions' => 0,
        ];

        $inProgressFilter = array_merge($baseFilter, ['STAGE_ID' => Config::DEAL_STAGE_IN_PROGRESS]);
        $stats['in_progress'] = $this->sumDeals($inProgressFilter);

        $bookingFilter = array_merge($baseFilter, ['STAGE_ID' => Config::DEAL_STAGE_BOOKING]);
        $stats['booking'] = $this->sumDeals($bookingFilter);

        $spaFilter = array_merge($baseFilter, ['STAGE_ID' => Config::DEAL_STAGE_SPA]);
        $stats['spa'] = $this->sumDeals($spaFilter);

        $wonFilter = array_merge($baseFilter, ['STAGE_ID' => Config::DEAL_STAGE_WON]);
        $wonAmount = $this->sumDeals($wonFilter);
        $stats['commissions'] = $wonAmount * ($broker['COMMISSION'] / 100);

        return $stats;
    }

    private function sumDeals(array $filter): float
    {
        $sum = 0;

        $res = \CCrmDeal::GetListEx(
            [],
            $filter,
            false,
            false,
            ['ID', 'OPPORTUNITY']
        );

        while ($row = $res->Fetch()) {
            $sum += (float)($row['OPPORTUNITY'] ?? 0);
        }

        return $sum;
    }

    private function prepareDateFilter(array $filter): array
    {
        $dateFilter = [];

        if (!empty($filter['date_from'])) {
            $dateFilter['>=DATE_CREATE'] = $filter['date_from'];
        }

        if (!empty($filter['date_to'])) {
            $dateFilter['<=DATE_CREATE'] = $filter['date_to'];
        }

        if (!empty($filter['period'])) {
            $dates = $this->getPeriodDates($filter['period']);
            if ($dates) {
                $dateFilter['>=DATE_CREATE'] = $dates['from'];
                $dateFilter['<=DATE_CREATE'] = $dates['to'];
            }
        }

        return $dateFilter;
    }

    private function getPeriodDates(string $period): ?array
    {
        $now = new \DateTime();
        
        switch ($period) {
            case 'current_month':
                return [
                    'from' => $now->format('Y-m-01 00:00:00'),
                    'to' => $now->format('Y-m-t 23:59:59'),
                ];
            
            case 'last_month':
                $lastMonth = (clone $now)->modify('-1 month');
                return [
                    'from' => $lastMonth->format('Y-m-01 00:00:00'),
                    'to' => $lastMonth->format('Y-m-t 23:59:59'),
                ];
            
            case 'quarter':
                $quarter = ceil($now->format('m') / 3);
                $startMonth = ($quarter - 1) * 3 + 1;
                $endMonth = $startMonth + 2;
                return [
                    'from' => $now->format('Y-' . str_pad($startMonth, 2, '0', STR_PAD_LEFT) . '-01 00:00:00'),
                    'to' => (new \DateTime($now->format('Y-' . str_pad($endMonth, 2, '0', STR_PAD_LEFT) . '-01')))->format('Y-m-t 23:59:59'),
                ];
            
            case 'year':
                return [
                    'from' => $now->format('Y-01-01 00:00:00'),
                    'to' => $now->format('Y-12-31 23:59:59'),
                ];
        }

        return null;
    }

    private function getEmptyStats(): array
    {
        return [
            'in_progress' => 0,
            'booking' => 0,
            'spa' => 0,
            'commissions' => 0,
        ];
    }
}