<?php
declare(strict_types=1);

use Bitrix\Main\Loader;
use Bitrix\Crm\Service;

if (!defined("B_PROLOG_INCLUDED") || B_PROLOG_INCLUDED !== true) {
    die();
}

const UNITS_ENTITY_TYPE_ID = 1032;
const PROJECT_ENTITY_TYPE_ID = 1036;

const UNIT_FIELD_PROJECT = 'UF_CRM_4_PROJECT';
const UNIT_FIELD_TOTAL_AREA = 'UF_CRM_4_1710319509126';
const UNIT_FIELD_PROPERTY_TYPE = 'UF_CRM_4_PROPERTY_TYPE';
const UNIT_FIELD_PRICE = 'UF_CRM_4_1710319624271';
const UNIT_FIELD_FLOOR_LAYOUT = 'UF_CRM_4_1712292264468';
const UNIT_FIELD_UNIT_LAYOUT = 'UF_CRM_4_1710319898856';
const UNIT_FIELD_COMBINED_LAYOUT = 'UF_CRM_4_1717680195718';

function broker_units_field_value(array $data, string $code)
{
    return $data[$code] ?? null;
}

function broker_units_format_price($priceRaw): string
{
    if (is_array($priceRaw)) {
        $amount = isset($priceRaw['amount']) ? (float)$priceRaw['amount'] : 0.0;
        $currency = isset($priceRaw['currency']) ? (string)$priceRaw['currency'] : 'AED';
        if ($amount > 0) {
            return number_format($amount, 0, '.', ' ') . ' ' . $currency;
        }
        return '';
    }

    if (is_numeric($priceRaw)) {
        $amount = (float)$priceRaw;
        if ($amount > 0) {
            return number_format($amount, 0, '.', ' ') . ' AED';
        }
        return '';
    }

    if (is_string($priceRaw) && $priceRaw !== '') {
        $s = trim($priceRaw);

        if (strpos($s, '|') !== false) {
            [$amountPart, $currencyPart] = explode('|', $s, 2);

            $amountDigits = preg_replace('/[^\d\.]/', '', $amountPart);
            $currency = preg_replace('/[^A-Z]/', '', strtoupper($currencyPart));

            $amount = is_numeric($amountDigits) ? (float)$amountDigits : 0.0;

            if ($amount > 0) {
                return number_format($amount, 0, '.', ' ') . ' ' . ($currency !== '' ? $currency : 'AED');
            }

            return trim($amountPart . ' ' . $currencyPart);
        }

        return $s;
    }

    return '';
}

function broker_units_resolve_file_url($value): ?string
{
    if (empty($value)) {
        return null;
    }

    $fileId = null;

    if (is_array($value)) {
        foreach ($value as $v) {
            if (is_numeric($v)) {
                $fileId = (int)$v;
                break;
            }
            if (is_array($v) && isset($v['ID']) && is_numeric($v['ID'])) {
                $fileId = (int)$v['ID'];
                break;
            }
        }
    } elseif (is_numeric($value)) {
        $fileId = (int)$value;
    }

    if (!$fileId || $fileId <= 0) {
        return null;
    }

    if (!class_exists(\CFile::class)) {
        return null;
    }

    $file = \CFile::GetFileArray($fileId);
    if (!is_array($file) || empty($file['SRC'])) {
        return null;
    }

    return $file['SRC'];
}

function broker_units_resolve_enum_value(string $fieldName, $id): string
{
    static $cache = [];

    if (!is_numeric($id)) {
        return (string)$id;
    }

    $id = (int)$id;

    if (isset($cache[$fieldName][$id])) {
        return $cache[$fieldName][$id];
    }

    if (!class_exists(\CUserFieldEnum::class)) {
        return (string)$id;
    }

    $rsEnum = \CUserFieldEnum::GetList(
        [],
        ['USER_FIELD_NAME' => $fieldName, 'ID' => $id]
    );

    if ($arEnum = $rsEnum->GetNext()) {
        $cache[$fieldName][$id] = (string)$arEnum['VALUE'];
        return $cache[$fieldName][$id];
    }

    $cache[$fieldName][$id] = (string)$id;
    return (string)$id;
}

function broker_units_resolve_project_title($projectId): string
{
    static $cache = [];

    if (!is_numeric($projectId)) {
        return (string)$projectId;
    }

    $projectId = (int)$projectId;

    if ($projectId <= 0) {
        return '';
    }

    if (isset($cache[$projectId])) {
        return $cache[$projectId];
    }

    if (!Loader::includeModule('crm')) {
        return '';
    }

    $factory = Service\Container::getInstance()->getFactory(PROJECT_ENTITY_TYPE_ID);
    if ($factory === null) {
        return '';
    }

    $item = $factory->getItem($projectId);
    if ($item === null) {
        $cache[$projectId] = '';
        return '';
    }

    $title = (string)$item->getTitle();
    $cache[$projectId] = $title;

    return $title;
}

function broker_units_get_list(?array $broker, int $page, int $pageSize, ?int &$totalCount = null): array
{
    if (!Loader::includeModule('crm')) {
        throw new \RuntimeException('CRM module is not installed');
    }

    $factory = Service\Container::getInstance()->getFactory(UNITS_ENTITY_TYPE_ID);
    if ($factory === null) {
        throw new \RuntimeException('Units factory not found (entityTypeId ' . UNITS_ENTITY_TYPE_ID . ')');
    }

    $filter = [];

    $totalCount = (int)$factory->getItemsCount($filter);

    $stageMap = [];
    foreach ($factory->getStages() as $stage) {
        $stageMap[$stage->getStatusId()] = $stage->getName();
    }

    $offset = max(0, ($page - 1) * $pageSize);

    $items = $factory->getItems([
        'filter' => $filter,
        'select' => [
            'ID',
            'TITLE',
            'STAGE_ID',
            UNIT_FIELD_PROJECT,
            UNIT_FIELD_TOTAL_AREA,
            UNIT_FIELD_PRICE,
            UNIT_FIELD_PROPERTY_TYPE,
            UNIT_FIELD_FLOOR_LAYOUT,
            UNIT_FIELD_UNIT_LAYOUT,
            UNIT_FIELD_COMBINED_LAYOUT,
        ],
        'order' => ['ID' => 'DESC'],
        'limit' => $pageSize,
        'offset' => $offset,
    ]);

    $units = [];

    foreach ($items as $item) {
        $data = $item->getData();

        $projectRaw = broker_units_field_value($data, UNIT_FIELD_PROJECT);
        $project = is_numeric($projectRaw)
            ? broker_units_resolve_project_title($projectRaw)
            : (string)$projectRaw;

        $unitTitle = (string)($data['TITLE'] ?? '');

        $totalAreaRaw = broker_units_field_value($data, UNIT_FIELD_TOTAL_AREA);
        $area = '';
        if (is_numeric($totalAreaRaw)) {
            $area = rtrim(
                rtrim(number_format((float)$totalAreaRaw, 2, '.', ''), '0'),
                '.'
            ) . ' ft²';
        }

        $propertyTypeRaw = broker_units_field_value($data, UNIT_FIELD_PROPERTY_TYPE);
        $typeName = $propertyTypeRaw !== null
            ? broker_units_resolve_enum_value(UNIT_FIELD_PROPERTY_TYPE, $propertyTypeRaw)
            : '';

        $priceRaw = broker_units_field_value($data, UNIT_FIELD_PRICE);
        $priceFormatted = broker_units_format_price($priceRaw);

        $stageId = (string)($data['STAGE_ID'] ?? '');
        $status = $stageMap[$stageId] ?? $stageId;

        $layoutUrl = broker_units_resolve_file_url(
            broker_units_field_value($data, UNIT_FIELD_COMBINED_LAYOUT)
        );
        if ($layoutUrl === null) {
            $layoutUrl = broker_units_resolve_file_url(
                broker_units_field_value($data, UNIT_FIELD_UNIT_LAYOUT)
            );
        }
        if ($layoutUrl === null) {
            $layoutUrl = broker_units_resolve_file_url(
                broker_units_field_value($data, UNIT_FIELD_FLOOR_LAYOUT)
            );
        }

        $units[] = [
            'ID'              => (int)$item->getId(),
            'PROJECT_ID'      => is_numeric($projectRaw) ? (int)$projectRaw : 0,
            'PROJECT_NAME'    => $project,
            'UNIT_TITLE'      => $unitTitle,
            'TYPE_NAME'       => $typeName,
            'AREA_SQFT'       => $area,
            'PRICE_FORMATTED' => $priceFormatted,
            'STATUS_NAME'     => $status,
            'LAYOUT_URL'      => $layoutUrl,
        ];
    }

    return $units;
}