<?php
use Bitrix\Main\Loader;
use Bitrix\DocumentGenerator\Template;
use Bitrix\DocumentGenerator\Document;

require $_SERVER['DOCUMENT_ROOT'] . '/bitrix/header.php';
require_once $_SERVER['DOCUMENT_ROOT'] . '/include/broker_handler.php';

global $APPLICATION;

$broker = broker_current();
if ($broker === null) {
    LocalRedirect('/auth/');
}

$APPLICATION->SetTitle('Commission request');

$errors = [];
$success = false;
$showConfirmation = false;
$dealData = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST' && check_bitrix_sessid()) {
    $dealId = (int)($_POST['DEAL_ID'] ?? 0);
    $confirmed = (int)($_POST['CONFIRMED'] ?? 0);
    
    if ($dealId <= 0) {
        $errors[] = 'Invalid deal ID.';
    }
    
    if (empty($errors)) {
        try {
            if (!Loader::includeModule('crm')) {
                throw new \RuntimeException('CRM module is not available.');
            }
            
            $rsDeals = \CCrmDeal::GetListEx(
                [],
                ['ID' => $dealId, 'CHECK_PERMISSIONS' => 'N'],
                false,
                ['nTopCount' => 1],
                ['ID', 'TITLE', 'OPPORTUNITY', 'CURRENCY_ID', 'STAGE_ID', 'UF_CRM_1763708684', 'COMMENTS']
            );
            
            $deal = $rsDeals->Fetch();
            
            if (!$deal) {
                throw new \RuntimeException('Deal not found.');
            }
            
            if (strpos($deal['COMMENTS'], 'Commission request from broker:') !== false) {
                throw new \RuntimeException('Commission has already been requested for this deal.');
            }
            
            $amount = (float)$deal['OPPORTUNITY'];
            $currency = $deal['CURRENCY_ID'] ?: 'AED';
            $dealTitle = $deal['TITLE'];
            $dealStage = $deal['STAGE_ID'];
            
            $brokerEmail = (string)($broker['EMAIL'] ?? '');
            $brokerCommission = 0;
            $brokerItemId = 0;
            
            if ($brokerEmail !== '') {
                $brokerItem = broker_find_item_by_email($brokerEmail);
                if ($brokerItem !== null) {
                    $bData = $brokerItem->getData();
                    $brokerCommission = (float)($bData['UF_CRM_15_COMMISSION'] ?? 0);
                    $brokerItemId = (int)$brokerItem->getId();
                }
            }
            
            $commission = ($amount * $brokerCommission) / 100;
            
            $brokerName = trim((string)($broker['NAME'] ?? '') . ' ' . (string)($broker['LAST_NAME'] ?? ''));
            
            if (!$confirmed) {
                $showConfirmation = true;
                $dealData = [
                    'ID' => $dealId,
                    'TITLE' => $dealTitle,
                    'AMOUNT' => $amount,
                    'CURRENCY' => $currency,
                    'COMMISSION_RATE' => $brokerCommission,
                    'COMMISSION' => $commission,
                    'STAGE' => $dealStage,
                ];
            } else {
                if (!Loader::includeModule('documentgenerator')) {
                    throw new \RuntimeException('Document Generator module is not available.');
                }
                
                $fileId = null;
                
                try {
                    $template = Template::loadById(2);
                    $template->setSourceType(\Bitrix\Crm\Integration\DocumentGenerator\DataProvider\Deal::class);
                    
                    $document = Document::createByTemplate($template, $dealId);
                    $result = $document->getFile();
                    
                    if ($result->isSuccess()) {
                        $fileData = $result->getData();
                        $fileId = $fileData['ID'] ?? 0;
                    }
                } catch (\Exception $e) {
                    throw new \RuntimeException('Failed to generate invoice: ' . $e->getMessage());
                }
                
                if (!$fileId) {
                    throw new \RuntimeException('Invoice file was not created.');
                }
                
                $comment = "Commission request from broker: {$brokerName}\n";
                $comment .= "Deal: D-{$dealId} - {$dealTitle}\n";
                $comment .= "Deal amount: " . number_format($amount, 2, '.', ',') . " {$currency}\n";
                $comment .= "Commission rate: {$brokerCommission}%\n";
                $comment .= "Commission amount: " . number_format($commission, 2, '.', ',') . " {$currency}\n";
                $comment .= "Invoice file ID: {$fileId}\n";
                $comment .= "Date: " . date('Y-m-d H:i:s');
                
                $dealEntity = new \CCrmDeal(false);
                $currentComments = (string)($deal['COMMENTS'] ?? '');
                $newComments = $currentComments . "\n\n" . $comment;
                
                $updateFields = ['COMMENTS' => $newComments];
                $updateResult = $dealEntity->Update($dealId, $updateFields);
                
                if (!$updateResult) {
                    throw new \RuntimeException('Failed to update deal comments.');
                }
                
                if (Loader::includeModule('crm')) {
                    \CCrmTimeline::AddComment([
                        'ENTITY_TYPE_ID' => \CCrmOwnerType::Deal,
                        'ENTITY_ID' => $dealId,
                        'COMMENT' => "Commission request: " . number_format($commission, 2, '.', ',') . " {$currency}",
                        'AUTHOR_ID' => 1,
                        'FILES' => [$fileId],
                    ]);
                }
                
                if ($brokerItemId > 0 && $fileId > 0) {
                    $smartFactory = \Bitrix\Crm\Service\Container::getInstance()->getFactory(15);
                    if ($smartFactory) {
                        $brokerItemFull = $smartFactory->getItem($brokerItemId);
                        if ($brokerItemFull) {
                            $currentFiles = $brokerItemFull->get('UF_CRM_15_INVOICE_FILES') ?: [];
                            if (!is_array($currentFiles)) {
                                $currentFiles = [];
                            }
                            $currentFiles[] = $fileId;
                            
                            $brokerItemFull->set('UF_CRM_15_INVOICE_FILES', $currentFiles);
                            $smartFactory->getUpdateOperation($brokerItemFull)->launch();
                        }
                    }
                }
                
                if (Loader::includeModule('tasks')) {
                    $taskDescription = "Commission payment request\n\n";
                    $taskDescription .= "Broker: {$brokerName}\n";
                    $taskDescription .= "Deal: D-{$dealId} - {$dealTitle}\n";
                    $taskDescription .= "Commission amount: " . number_format($commission, 2, '.', ',') . " {$currency}\n";
                    $taskDescription .= "Invoice file ID: {$fileId}\n\n";
                    $taskDescription .= "Link to deal: /crm/deal/details/{$dealId}/";
                    
                    $taskItem = \Bitrix\Tasks\Internals\TaskTable::add([
                        'TITLE' => "Commission payment for deal D-{$dealId}",
                        'DESCRIPTION' => $taskDescription,
                        'RESPONSIBLE_ID' => 1,
                        'CREATED_BY' => 1,
                        'STATUS' => 2,
                        'UF_CRM_TASK' => ['D_' . $dealId],
                    ]);
                }
                
                $success = true;
            }
            
        } catch (\Throwable $e) {
            $errors[] = $e->getMessage();
        }
    }
}
?>

<div class="container py-4">
    <?php if ($success): ?>
        <div class="alert alert-success">
            <h5>Commission request submitted successfully!</h5>
            <hr>
            <p>Invoice has been generated and attached to your broker profile.</p>
            <p>A task has been created for the accountant to process the payment.</p>
        </div>
        <a href="/broker/" class="btn btn-primary">Back to dashboard</a>
    <?php endif; ?>

    <?php if ($showConfirmation && !empty($dealData)): ?>
        <div class="card">
            <div class="card-header">
                <h5>Confirm commission request</h5>
            </div>
            <div class="card-body">
                <p><strong>Deal:</strong> D-<?= (int)$dealData['ID'] ?> - <?= htmlspecialcharsbx($dealData['TITLE']) ?></p>
                <p><strong>Deal amount:</strong> <?= number_format($dealData['AMOUNT'], 2, '.', ',') ?> <?= htmlspecialcharsbx($dealData['CURRENCY']) ?></p>
                <p><strong>Commission rate:</strong> <?= number_format($dealData['COMMISSION_RATE'], 2) ?>%</p>
                <hr>
                <h4>Your commission: <?= number_format($dealData['COMMISSION'], 2, '.', ',') ?> <?= htmlspecialcharsbx($dealData['CURRENCY']) ?></h4>
                <hr>
                <p>Do you want to request this commission payment?</p>
                
                <form method="POST">
                    <input type="hidden" name="DEAL_ID" value="<?= (int)$dealData['ID'] ?>">
                    <input type="hidden" name="CONFIRMED" value="1">
                    <input type="hidden" name="sessid" value="<?= bitrix_sessid() ?>">
                    
                    <button type="submit" class="btn btn-success">Confirm request</button>
                    <a href="/broker/" class="btn btn-secondary">Cancel</a>
                </form>
            </div>
        </div>
    <?php endif; ?>

    <?php if (!empty($errors)): ?>
        <div class="alert alert-danger">
            <?php foreach ($errors as $msg): ?>
                <div><?= htmlspecialcharsbx($msg) ?></div>
            <?php endforeach; ?>
        </div>
        <a href="/broker/" class="btn btn-secondary">Back to dashboard</a>
    <?php endif; ?>
</div>

<?php
require $_SERVER['DOCUMENT_ROOT'] . '/bitrix/footer.php';