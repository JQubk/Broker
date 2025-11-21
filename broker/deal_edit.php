<?php
use Bitrix\Main\Loader;
use Bitrix\Crm\DealTable;

require $_SERVER['DOCUMENT_ROOT'] . '/bitrix/header.php';
require_once $_SERVER['DOCUMENT_ROOT'] . '/include/broker_handler.php';
require_once $_SERVER['DOCUMENT_ROOT'] . '/include/broker_units.php';

global $APPLICATION, $USER;

$broker = broker_current();
if ($broker === null) {
    LocalRedirect('/auth/');
}

$APPLICATION->SetTitle('Register deal');

$errors = [];
$success = false;

$dealData = [
    'BUYER_FIRST_NAME'  => '',
    'BUYER_LAST_NAME'   => '',
    'BUYER_EMAIL'       => '',
    'BUYER_PHONE'       => '',
    'BUYER_CITIZENSHIP' => '',
    'UNIT_ID'           => '',
];

$unitsList  = [];
$unitsIndex = [];

$brokerAgencyName = '';
$brokerCommission = '';
$brokerCurrency   = '';

$logFile = $_SERVER["DOCUMENT_ROOT"] . '/logs/deal_debug.log';

try {
    if (!Loader::includeModule('crm')) {
        throw new \RuntimeException('CRM module is not available.');
    }

    $totalUnits = 0;
    $unitsList = broker_units_get_list($broker, 1, 500, $totalUnits);

    foreach ($unitsList as $item) {
        $id = (int)$item['ID'];

        $labelParts = [];
        if (!empty($item['PROJECT_NAME'])) {
            $labelParts[] = $item['PROJECT_NAME'];
        }
        if (!empty($item['UNIT_TITLE'])) {
            $labelParts[] = $item['UNIT_TITLE'];
        }
        if (!empty($item['TYPE_NAME'])) {
            $labelParts[] = $item['TYPE_NAME'];
        }

        $label = $labelParts ? implode(' / ', $labelParts) : (string)$id;

        $projectIdRaw =
            $item['PROJECT_ID']
            ?? $item['PROJECT_ITEM_ID']
            ?? $item['PROJECT']
            ?? null;
        $projectId = is_scalar($projectIdRaw) ? (int)$projectIdRaw : 0;

        $priceRaw = (string)($item['PRICE_FORMATTED'] ?? '');
        $priceValue = 0.0;
        if ($priceRaw !== '') {
            $priceDigits = preg_replace('~[^\d\.]~', '', $priceRaw);
            if ($priceDigits !== '') {
                $priceValue = (float)$priceDigits;
            }
        }

        $unitsIndex[$id] = [
            'ID'              => $id,
            'LABEL'           => $label,
            'PROJECT_NAME'    => (string)($item['PROJECT_NAME'] ?? ''),
            'PRICE'           => $priceRaw,
            'PRICE_VALUE'     => $priceValue,
            'PROJECT_ITEM_ID' => $projectId,
            'STATUS_NAME'     => (string)($item['STATUS_NAME'] ?? ''),
        ];
    }

    $brokerEmail = (string)($broker['EMAIL'] ?? '');
    if ($brokerEmail !== '') {
        $brokerItem = broker_find_item_by_email($brokerEmail);
        if ($brokerItem !== null) {
            $bData = $brokerItem->getData();
            $brokerAgencyName = (string)($bData['UF_CRM_15_AGENCY_NAME'] ?? '');
            $brokerCommission = (string)($bData['UF_CRM_15_COMMISSION'] ?? '');
            $brokerCurrency   = (string)($bData['UF_CRM_15_CURRENCY'] ?? '');
        }
    }
} catch (\Throwable $e) {
    $errors[] = 'Cannot load units or broker: ' . $e->getMessage();
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && check_bitrix_sessid()) {
    $dealData['BUYER_FIRST_NAME']  = trim((string)($_POST['BUYER_FIRST_NAME'] ?? ''));
    $dealData['BUYER_LAST_NAME']   = trim((string)($_POST['BUYER_LAST_NAME'] ?? ''));
    $dealData['BUYER_EMAIL']       = trim((string)($_POST['BUYER_EMAIL'] ?? ''));
    $dealData['BUYER_PHONE']       = trim((string)($_POST['BUYER_PHONE'] ?? ''));
    $dealData['BUYER_CITIZENSHIP'] = trim((string)($_POST['BUYER_CITIZENSHIP'] ?? ''));
    $dealData['UNIT_ID']           = (int)($_POST['UNIT_ID'] ?? 0);

    if ($dealData['BUYER_FIRST_NAME'] === '') {
        $errors[] = 'First name is required.';
    }
    if ($dealData['BUYER_LAST_NAME'] === '') {
        $errors[] = 'Last name is required.';
    }
    if ($dealData['BUYER_EMAIL'] === '') {
        $errors[] = 'Email is required.';
    }
    if ($dealData['BUYER_PHONE'] === '') {
        $errors[] = 'Phone is required.';
    }
    if ($dealData['UNIT_ID'] <= 0 || !isset($unitsIndex[$dealData['UNIT_ID']])) {
        $errors[] = 'Unit is required.';
    }

    if ($dealData['UNIT_ID'] > 0 && isset($unitsIndex[$dealData['UNIT_ID']])) {
        $unitInfo = $unitsIndex[$dealData['UNIT_ID']];
        $statusName = (string)($unitInfo['STATUS_NAME'] ?? '');
        if ($statusName !== '' && strcasecmp($statusName, 'Available') !== 0) {
            $errors[] = 'Unit not available.';
        }
    }

    if (empty($errors)) {
        try {
            if (!Loader::includeModule('crm')) {
                throw new \RuntimeException('CRM module is not available.');
            }

            $userId = 1;

            $contactEntity = new \CCrmContact(false);

            $contactFields = [
                'NAME'           => $dealData['BUYER_FIRST_NAME'],
                'LAST_NAME'      => $dealData['BUYER_LAST_NAME'],
                'ASSIGNED_BY_ID' => $userId,
                'CREATED_BY_ID'  => $userId,
                'MODIFY_BY_ID'   => $userId,
                'OPENED'         => 'Y',
            ];

            $fm = [];
            if ($dealData['BUYER_EMAIL'] !== '') {
                $fm['EMAIL'] = [
                    ['VALUE' => $dealData['BUYER_EMAIL'], 'VALUE_TYPE' => 'WORK'],
                ];
            }
            if ($dealData['BUYER_PHONE'] !== '') {
                $fm['PHONE'] = [
                    ['VALUE' => $dealData['BUYER_PHONE'], 'VALUE_TYPE' => 'WORK'],
                ];
            }

            $existingContactId = 0;
            if ($dealData['BUYER_EMAIL'] !== '') {
                $rsContact = \CCrmContact::GetListEx(
                    [],
                    ['=EMAIL' => $dealData['BUYER_EMAIL']],
                    false,
                    ['nTopCount' => 1],
                    ['ID']
                );
                if ($arContact = $rsContact->Fetch()) {
                    $existingContactId = (int)$arContact['ID'];
                }
            }

            if ($existingContactId > 0) {
                $contactId = $existingContactId;

                $ok = $contactEntity->Update($contactId, $contactFields, true, ['CURRENT_USER' => $userId]);
                if (!$ok) {
                    $e = $APPLICATION->GetException();
                    throw new \RuntimeException($e ? $e->GetString() : 'Contact update failed.');
                }
            } else {
                if (!empty($fm)) {
                    $contactFields['FM'] = $fm;
                }

                $contactId = (int)$contactEntity->Add(
                    $contactFields,
                    true,
                    ['CURRENT_USER' => $userId]
                );

                if ($contactId <= 0) {
                    $e = $APPLICATION->GetException();
                    throw new \RuntimeException($e ? $e->GetString() : 'Contact create failed.');
                }
            }

            $passportFileId = 0;
            $eoiFileId = 0;

            if (!empty($_FILES['DOC_PASSPORT']) && (int)$_FILES['DOC_PASSPORT']['error'] === UPLOAD_ERR_OK) {
                $file = $_FILES['DOC_PASSPORT'];
                $file['MODULE_ID'] = 'crm';
                $passportFileId = (int)\CFile::SaveFile($file, 'crm');
            }

            if (!empty($_FILES['DOC_EOI']) && (int)$_FILES['DOC_EOI']['error'] === UPLOAD_ERR_OK) {
                $file = $_FILES['DOC_EOI'];
                $file['MODULE_ID'] = 'crm';
                $eoiFileId = (int)\CFile::SaveFile($file, 'crm');
            }

            $unitInfo = $unitsIndex[$dealData['UNIT_ID']];

            $buyerName = trim($dealData['BUYER_FIRST_NAME'] . ' ' . $dealData['BUYER_LAST_NAME']);

            $titleParts = [];

            if ($unitInfo['PROJECT_NAME'] !== '') {
                $titleParts[] = $unitInfo['PROJECT_NAME'];
            }
            if ($unitInfo['LABEL'] !== '') {
                $titleParts[] = $unitInfo['LABEL'];
            }
            if ($buyerName !== '') {
                $titleParts[] = $buyerName;
            }

            $dealTitle = $titleParts ? implode(' / ', $titleParts) : 'Broker deal';

            $brokerName  = trim((string)($broker['NAME'] ?? '') . ' ' . (string)($broker['LAST_NAME'] ?? ''));
            $brokerEmail = (string)($broker['EMAIL'] ?? '');

            $commentLines = [];

            if ($brokerName !== '') {
                $commentLines[] = 'Broker: ' . $brokerName;
            }
            if ($brokerEmail !== '') {
                $commentLines[] = 'Broker email: ' . $brokerEmail;
            }
            if ($unitInfo['PROJECT_NAME'] !== '') {
                $commentLines[] = 'Project: ' . $unitInfo['PROJECT_NAME'];
            }
            if ($unitInfo['LABEL'] !== '') {
                $commentLines[] = 'Unit: ' . $unitInfo['LABEL'];
            }
            if ($unitInfo['PRICE'] !== '') {
                $commentLines[] = 'Unit price: ' . $unitInfo['PRICE'];
            }

            $dealFields = [
                'TITLE'          => $dealTitle,
                'CATEGORY_ID'    => 0,
                'STAGE_ID'       => 'NEW',
                'CONTACT_ID'     => $contactId,
                'ASSIGNED_BY_ID' => $userId,
                'CREATED_BY_ID'  => $userId,
                'MODIFY_BY_ID'   => $userId,
                'OPENED'         => 'Y',
            ];

            if ($unitInfo['PRICE_VALUE'] > 0) {
                $dealFields['OPPORTUNITY'] = $unitInfo['PRICE_VALUE'];
                $dealFields['CURRENCY_ID'] = 'AED';
            }

            $brokerFullName = $brokerName;
            if ($brokerFullName !== '') {
                $dealFields['UF_CRM_1763708684'] = $brokerFullName;
            }

            if ($brokerAgencyName !== '') {
                $dealFields['UF_CRM_1756215544'] = $brokerAgencyName;
            }

            if ($brokerCommission !== '') {
                $dealFields['UF_CRM_1763304021510'] = (float)$brokerCommission;
            }

            $unitBinding = 'T408_' . (int)$unitInfo['ID'];
            $dealFields['UF_CRM_1756215507'] = $unitBinding;

            $projectId = (int)($unitInfo['PROJECT_ITEM_ID'] ?? 0);
            if ($projectId > 0) {
                $projectBinding = 'T40c_' . $projectId;
                $dealFields['UF_CRM_1756215456'] = $projectBinding;
            }

            if (!empty($commentLines)) {
                $dealFields['COMMENTS'] = implode("\n", $commentLines);
            }

            $result = DealTable::add($dealFields);

            if (!$result->isSuccess()) {
                throw new \RuntimeException(implode('; ', $result->getErrorMessages()));
            }

            $dealId = (int)$result->getId();

            if ($eoiFileId > 0) {
                file_put_contents($logFile, date('Y-m-d H:i:s') . " Trying to attach file $eoiFileId to deal $dealId\n", FILE_APPEND);
                
                $sql = "INSERT INTO b_uts_crm_deal (VALUE_ID, UF_CRM_1756976242324) VALUES ($dealId, $eoiFileId) ON DUPLICATE KEY UPDATE UF_CRM_1756976242324 = $eoiFileId";
                $GLOBALS['DB']->Query($sql);
                
                file_put_contents($logFile, date('Y-m-d H:i:s') . " Direct SQL executed\n", FILE_APPEND);
                
                $checkDeal = \CCrmDeal::GetByID($dealId);
                file_put_contents($logFile, date('Y-m-d H:i:s') . " UF_CRM_1756976242324 value after SQL: " . print_r($checkDeal['UF_CRM_1756976242324'], true) . "\n\n", FILE_APPEND);
            }

            $success = true;
            $dealData = [
                'BUYER_FIRST_NAME'  => '',
                'BUYER_LAST_NAME'   => '',
                'BUYER_EMAIL'       => '',
                'BUYER_PHONE'       => '',
                'BUYER_CITIZENSHIP' => '',
                'UNIT_ID'           => '',
            ];
        } catch (\Throwable $e) {
            $errors[] = $e->getMessage();
            file_put_contents($logFile, date('Y-m-d H:i:s') . " ERROR: " . $e->getMessage() . "\n\n", FILE_APPEND);
        }
    }
}
?>

<div class="container py-4">
    <?php if ($success): ?>
        <div class="alert alert-success">Deal has been registered.</div>
    <?php endif; ?>

    <?php if (!empty($errors)): ?>
        <div class="alert alert-danger">
            <?php foreach ($errors as $msg): ?>
                <div><?= htmlspecialcharsbx($msg) ?></div>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>

    <?php
    $unitsOptions = $unitsIndex;
    include $_SERVER['DOCUMENT_ROOT'] . '/include/broker_deal_form.php';
    ?>
</div>

<?php
require $_SERVER['DOCUMENT_ROOT'] . '/bitrix/footer.php';