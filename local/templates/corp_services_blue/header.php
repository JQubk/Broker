<?php
if (!defined("B_PROLOG_INCLUDED") || B_PROLOG_INCLUDED !== true) {
    die();
}

use Bitrix\Main\Page\Asset;
use Bitrix\Main\Context;

require_once $_SERVER['DOCUMENT_ROOT'] . '/include/broker_handler.php';

$context = Context::getCurrent();
$request = $context->getRequest();
$isSidePanel = ($request->get('IFRAME') === 'Y');

$asset = Asset::getInstance();
$asset->addCss("/assets/css/bootstrap.min.css");
$asset->addCss("/assets/css/broker.css");

$asset->addJs("/assets/js/bootstrap.bundle.min.js");
$asset->addJs("/assets/js/broker.js");

CJSCore::Init(['sidepanel']);

global $APPLICATION;

$currentPage = $APPLICATION->GetCurPage(false);

$publicPages = [
    '/auth/',
    '/auth/index.php',
    '/auth/logout.php',
    '/favicon.ico',
    '/404.php',
];

$broker = broker_current();

if (!in_array($currentPage, $publicPages, true) && !$isSidePanel) {
    if ($broker === null) {
        $backUrl = $APPLICATION->GetCurPageParam('', [], false);
        header('Location: /auth/?backurl=' . urlencode($backUrl));
        exit;
    }
}

$hideSidebarPages = [
    '/auth/',
    '/auth/index.php',
];

$showSidebar = !$isSidePanel && !in_array($currentPage, $hideSidebarPages, true);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta http-equiv="X-UA-Compatible" content="IE=edge" />
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?php $APPLICATION->ShowTitle(); ?></title>
    <link rel="shortcut icon" href="<?= SITE_TEMPLATE_PATH ?>/favicon.ico" />
    <?php $APPLICATION->ShowHead(); ?>
</head>
<body>
<?php $APPLICATION->ShowPanel(); ?>

<div id="page-wrapper" class="app-layout d-flex flex-column" style="min-height:100vh;">

    <?php if (!$isSidePanel): ?>
        <header class="app-header bg-white border-bottom">
            <div class="container-fluid">
                <div class="d-flex align-items-center justify-content-between py-2">
                    <div class="d-flex align-items-center gap-3">
                        <a href="/index.php" class="navbar-brand">
                            <img src="<?= SITE_TEMPLATE_PATH ?>/logo.svg" alt="Logo" style="height:36px">
                        </a>
                        <h5 class="mb-0">
                            <?= htmlspecialcharsbx($APPLICATION->GetTitle()) ?>
                        </h5>
                    </div>

                    <div class="d-flex align-items-center gap-3">
                        <div class="app-header__user text-muted small">
                            <?php if ($broker !== null): ?>
                                Current broker:
                                <strong>
                                    <?= htmlspecialcharsbx(trim($broker['NAME'] . ' ' . $broker['LAST_NAME'])) ?>
                                </strong>
                            <?php else: ?>
                                Not signed in
                            <?php endif; ?>
                        </div>

                        <?php if ($broker !== null): ?>
                            <a href="/auth/logout.php"
                               class="btn btn-outline-secondary btn-sm"
                               data-broker-logout="Y">
                                Log out
                            </a>
                        <?php else: ?>
                            <a class="btn btn-primary btn-sm" href="/auth/">
                                Sign in
                            </a>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </header>
    <?php endif; ?>


    <main class="app-main container-fluid flex-grow-1">
        <div class="row g-0">
            <?php if ($showSidebar): ?>
                <aside id="left-menu" class="app-sidebar col-auto bg-light border-end" style="width:260px;">
                    <div class="p-3">
                        <nav class="nav flex-column">
                            <span class="text-muted small mb-2 d-block">Sections</span>

                            <a class="nav-link py-2"
                               href="/index.php">
                                Broker cabinet
                            </a>

                            <a class="nav-link py-2"
                               href="/broker/units.php"
                               data-broker-sidepanel="Y">
                                Units availability
                            </a>

                            <a class="nav-link py-2"
                               href="/broker/deals.php"
                               data-broker-sidepanel="Y">
                                Deals
                            </a>

                            <a class="nav-link py-2"
                               href="/broker/commissions.php"
                               data-broker-sidepanel="Y">
                                Commission request
                            </a>
                        </nav>
                    </div>
                </aside>

                <div id="content-wrapper" class="app-content col">
            <?php else: ?>
                <div id="content-wrapper" class="app-content col-12">
            <?php endif; ?>
                    <div id="content" class="p-4">
                        <?php
                        if (
                            $currentPage != SITE_DIR
                            && !in_array($currentPage, ['/auth/', '/auth/index.php'], true)
                        ): ?>
                            <div id="breadcrumb" class="mb-3">
                                <?php
                                $APPLICATION->IncludeComponent(
                                    "bitrix:breadcrumb",
                                    ".default",
                                    [
                                        "START_FROM" => "1",
                                        "PATH"      => "",
                                        "SITE_ID"   => SITE_ID,
                                    ],
                                    false
                                );
                                ?>
                            </div>
                        <?php endif; ?>

                        <div id="workarea-wrapper">
                            <div id="workarea" class="bg-white rounded shadow-sm p-3">
                                <div id="workarea-inner">
                                    <?php if (!in_array($currentPage, ['/index.php', '/auth/', '/auth/index.php'], true)): ?>
                                        <h5 class="mb-3"><?php $APPLICATION->ShowTitle(false); ?></h5>
                                    <?php endif; ?>
