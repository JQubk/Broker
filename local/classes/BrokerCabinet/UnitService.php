<?php

namespace BrokerCabinet;

use Bitrix\Main\Loader;
use Bitrix\Crm\Service;

class UnitService
{
    private $factory;
    private $stageMap = [];

    public function __construct()
    {
        if (!Loader::includeModule('crm')) {
            throw new \RuntimeException('CRM module not installed');
        }

        $this->factory = Service\Container::getInstance()->getFactory(Config::UNITS_ENTITY_TYPE_ID);
        
        if ($this->factory === null) {
            throw new \RuntimeException('Units factory not found');
        }

        $this->loadStageMap();
    }

    private function loadStageMap(): void
    {
        $stages = $this->factory->getStages();
        foreach ($stages as $stage) {
            $this->stageMap[$stage->getStatusId()] = $stage->getName();
        }
    }

    public function getList(array $filter, int $page, int $pageSize, ?int &$totalCount = null): array
    {
        $pageSize = max(10, min(100, $pageSize));
        $page = max(1, $page);
        $offset = ($page - 1) * $pageSize;

        $bitrixFilter = $this->prepareBitrixFilter($filter);

        $totalCount = (int)$this->factory->getItemsCount($bitrixFilter);

        $items = $this->factory->getItems([
            'filter' => $bitrixFilter,
            'select' => $this->getSelectFields(),
            'order' => ['ID' => 'DESC'],
            'limit' => $pageSize,
            'offset' => $offset,
        ]);

        $units = [];
        foreach ($items as $item) {
            $units[] = $this->prepareUnit($item);
        }

        return $units;
    }

    public function getById(int $id): ?array
    {
        $item = $this->factory->getItem($id);
        
        if ($item === null) {
            return null;
        }

        return $this->prepareUnit($item);
    }

    private function prepareUnit($item): array
    {
        $data = $item->getData();

        $projectRaw = $this->getFieldValue($data, Config::UNIT_FIELD_PROJECT);
        $projectName = is_numeric($projectRaw) ? $this->getProjectName((int)$projectRaw) : (string)$projectRaw;

        $propertyTypeRaw = $this->getFieldValue($data, Config::UNIT_FIELD_PROPERTY_TYPE);
        $propertyType = $propertyTypeRaw !== null ? $this->resolveEnumValue(Config::UNIT_FIELD_PROPERTY_TYPE, $propertyTypeRaw) : '';

        $areaRaw = $this->getFieldValue($data, Config::UNIT_FIELD_TOTAL_AREA);
        $area = is_numeric($areaRaw) ? (float)$areaRaw : 0;

        $priceRaw = $this->getFieldValue($data, Config::UNIT_FIELD_PRICE);
        $price = is_numeric($priceRaw) ? (float)$priceRaw : 0;

        $stageId = (string)($data['STAGE_ID'] ?? '');
        $statusName = $this->stageMap[$stageId] ?? $stageId;

        $layoutUrl = $this->resolveLayoutUrl($data);

        return [
            'ID' => (int)$item->getId(),
            'TITLE' => (string)($data['TITLE'] ?? ''),
            'PROJECT_ID' => is_numeric($projectRaw) ? (int)$projectRaw : 0,
            'PROJECT_NAME' => $projectName,
            'PROPERTY_TYPE' => $propertyType,
            'AREA' => $area,
            'AREA_FORMATTED' => Format::area($area),
            'PRICE' => $price,
            'PRICE_FORMATTED' => Format::price($price),
            'STATUS' => $statusName,
            'STAGE_ID' => $stageId,
            'LAYOUT_URL' => $layoutUrl,
        ];
    }

    private function getFieldValue(array $data, string $fieldName)
    {
        if (!isset($data[$fieldName])) {
            return null;
        }

        $value = $data[$fieldName];

        if (is_object($value) && method_exists($value, '__toString')) {
            return (string)$value;
        }

        return $value;
    }

    private function resolveLayoutUrl(array $data): ?string
    {
        $fields = [
            Config::UNIT_FIELD_COMBINED_LAYOUT,
            Config::UNIT_FIELD_UNIT_LAYOUT,
            Config::UNIT_FIELD_FLOOR_LAYOUT,
        ];

        foreach ($fields as $fieldName) {
            $url = $this->resolveFileUrl($this->getFieldValue($data, $fieldName));
            if ($url !== null) {
                return $url;
            }
        }

        return null;
    }

    private function resolveFileUrl($value): ?string
    {
        if (empty($value)) {
            return null;
        }

        $fileId = null;

        if (is_array($value)) {
            foreach ($value as $item) {
                if (is_numeric($item)) {
                    $fileId = (int)$item;
                    break;
                }
                if (is_array($item) && isset($item['ID']) && is_numeric($item['ID'])) {
                    $fileId = (int)$item['ID'];
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

    private function getProjectName(int $projectId): string
    {
        static $cache = [];

        if (isset($cache[$projectId])) {
            return $cache[$projectId];
        }

        $factory = Service\Container::getInstance()->getFactory(Config::PROJECT_ENTITY_TYPE_ID);
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

    private function resolveEnumValue(string $fieldName, $id): string
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

    private function prepareBitrixFilter(array $filter): array
    {
        $bitrixFilter = [];

        if (!empty($filter['search'])) {
            $bitrixFilter['%TITLE'] = $filter['search'];
        }

        if (!empty($filter['project_id'])) {
            $bitrixFilter['=' . Config::UNIT_FIELD_PROJECT] = (int)$filter['project_id'];
        }

        if (!empty($filter['property_type'])) {
            $bitrixFilter['=' . Config::UNIT_FIELD_PROPERTY_TYPE] = $filter['property_type'];
        }

        if (!empty($filter['status'])) {
            if (is_array($filter['status'])) {
                $bitrixFilter['@STAGE_ID'] = $filter['status'];
            } else {
                $bitrixFilter['=STAGE_ID'] = $filter['status'];
            }
        }

        return $bitrixFilter;
    }

    private function getSelectFields(): array
    {
        return [
            'ID',
            'TITLE',
            'STAGE_ID',
            Config::UNIT_FIELD_PROJECT,
            Config::UNIT_FIELD_TOTAL_AREA,
            Config::UNIT_FIELD_PRICE,
            Config::UNIT_FIELD_PROPERTY_TYPE,
            Config::UNIT_FIELD_FLOOR_LAYOUT,
            Config::UNIT_FIELD_UNIT_LAYOUT,
            Config::UNIT_FIELD_COMBINED_LAYOUT,
        ];
    }

    public function getProjectsList(): array
    {
        $factory = Service\Container::getInstance()->getFactory(Config::PROJECT_ENTITY_TYPE_ID);
        if ($factory === null) {
            return [];
        }

        $items = $factory->getItems([
            'select' => ['ID', 'TITLE'],
            'order' => ['TITLE' => 'ASC'],
        ]);

        $projects = [];
        foreach ($items as $item) {
            $projects[$item->getId()] = $item->getTitle();
        }

        return $projects;
    }

    public function getPropertyTypes(): array
    {
        if (!class_exists(\CUserFieldEnum::class)) {
            return [];
        }

        $rsEnum = \CUserFieldEnum::GetList(
            ['SORT' => 'ASC'],
            ['USER_FIELD_NAME' => Config::UNIT_FIELD_PROPERTY_TYPE]
        );

        $types = [];
        while ($arEnum = $rsEnum->GetNext()) {
            $types[$arEnum['ID']] = $arEnum['VALUE'];
        }

        return $types;
    }

    public function getStatuses(): array
    {
        return $this->stageMap;
    }
}