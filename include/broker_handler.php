<?php

// /include/broker_handler.php

use Bitrix\Main\Loader;
use Bitrix\Main\Type\DateTime;
use Bitrix\Crm\Service;
use Bitrix\Crm\Timeline\CommentEntry;
use Bitrix\Main\Diag\Debug;

if (session_status() !== PHP_SESSION_ACTIVE) {
    session_start();
}

const BROKER_ENTITY_TYPE_ID = 1076;
const BROKER_FIELD_EMAIL    = 'UF_CRM_15_EMAIL';
const BROKER_FIELD_PASS     = 'UF_CRM_15_PASS';
const BROKER_FIELD_NAME     = 'UF_CRM_15_NAME';
const BROKER_FIELD_LASTNAME = 'UF_CRM_15_LAST_NAME';
const BROKER_FIELD_LAST_LOGIN = 'UF_CRM_15_LAST_LOGIN_LOG';

function broker_password_matches(string $input, string $stored): bool
{
    if ($stored === '') {
        return false;
    }

    if (preg_match('/^\$(2y|2a|argon2id|argon2i)\$/', $stored)) {
        return password_verify($input, $stored);
    }

    return hash_equals($stored, $input);
}

function broker_find_item_by_email(string $email): ?\Bitrix\Crm\Item
{
    $email = trim(mb_strtolower($email));
    if ($email === '') {
        return null;
    }

    if (!class_exists(Loader::class)) {
        require_once $_SERVER['DOCUMENT_ROOT'] . '/bitrix/modules/main/include/prolog_before.php';
    }

    if (!Loader::includeModule('crm')) {
        throw new \RuntimeException('CRM module is not installed');
    }

    $factory = Service\Container::getInstance()->getFactory(BROKER_ENTITY_TYPE_ID);
    if ($factory === null) {
        throw new \RuntimeException('Brokers factory not found (entityTypeId ' . BROKER_ENTITY_TYPE_ID . ')');
    }

    $items = $factory->getItems([
        'filter' => [
            '=' . BROKER_FIELD_EMAIL => $email,
        ],
        'limit'  => 1,
    ]);

    $item = null;
    foreach ($items as $candidate) {
        $item = $candidate;
        break;
    }

    return $item;
}

function broker_login(string $email, string $password): array
{
    $email = trim($email);

    if ($email === '' || $password === '') {
        throw new \RuntimeException('EMPTY_CREDENTIALS');
    }

    $item = broker_find_item_by_email($email);
    if ($item === null) {
        throw new \RuntimeException('INVALID_CREDENTIALS');
    }

    $data = $item->getData();

    $storedPass = (string)($data[BROKER_FIELD_PASS] ?? '');
    if (!broker_password_matches($password, $storedPass)) {
        throw new \RuntimeException('INVALID_CREDENTIALS');
    }

    $broker = [
        'ID'        => (int)$item->getId(),
        'EMAIL'     => (string)($data[BROKER_FIELD_EMAIL] ?? $email),
        'NAME'      => (string)($data[BROKER_FIELD_NAME] ?? ''),
        'LAST_NAME' => (string)($data[BROKER_FIELD_LASTNAME] ?? ''),
    ];

    $_SESSION['BROKER'] = $broker;

    broker_log_login($broker);

    return $broker;
}

function broker_current(): ?array
{
    return isset($_SESSION['BROKER']) && is_array($_SESSION['BROKER'])
        ? $_SESSION['BROKER']
        : null;
}

function broker_require_auth(): array
{
    $broker = broker_current();
    if ($broker !== null) {
        return $broker;
    }

    $requestUri = $_SERVER['REQUEST_URI'] ?? '/index.php';
    $backUrl    = urlencode($requestUri);

    header('Location: /auth/?backurl=' . $backUrl);
    exit;
}

function broker_logout(): void
{
    unset($_SESSION['BROKER']);
}

function broker_log_login(array $broker): void
{
    try {
        if (!\Bitrix\Main\Loader::includeModule('crm')) {
            return;
        }

        if (!defined('BROKER_ENTITY_TYPE_ID') || BROKER_ENTITY_TYPE_ID <= 0) {
            return;
        }

        $brokerId = (int)($broker['ID'] ?? 0);
        if ($brokerId <= 0) {
            return;
        }

        $name  = trim((string)($broker['NAME'] ?? '') . ' ' . (string)($broker['LAST_NAME'] ?? ''));
        $email = (string)($broker['EMAIL'] ?? '');
        $ip    = (string)($_SERVER['REMOTE_ADDR'] ?? '');
        $now   = new \Bitrix\Main\Type\DateTime();
        $timeStr = $now->format('Y-m-d H:i:s');

        $text = 'Broker portal login';
        if ($name !== '') {
            $text .= ': ' . $name;
        }
        if ($email !== '') {
            $text .= ' <' . $email . '>';
        }
        $text .= ' at ' . $timeStr;
        if ($ip !== '') {
            $text .= ', IP ' . $ip;
        }

        $container = \Bitrix\Crm\Service\Container::getInstance();
        $factory   = $container->getFactory(BROKER_ENTITY_TYPE_ID);
        if (!$factory) {
            return;
        }

        $item = $factory->getItem($brokerId);
        if (!$item) {
            return;
        }

        $item->set(BROKER_FIELD_LAST_LOGIN, $text);

        $operation = $factory->getUpdateOperation($item);
        if (method_exists($operation, 'disableAutomation')) {
            $operation->disableAutomation();
        }
        if (method_exists($operation, 'disableBizProc')) {
            $operation->disableBizProc();
        }
        if (method_exists($operation, 'disableCheckAccess')) {
            $operation->disableCheckAccess();
        }

        $result = $operation->launch();
        if (!$result->isSuccess()) {
            return;
        }

        try {
            \Bitrix\Crm\Timeline\CommentEntry::create([
                'TEXT' => $text,
                'BINDINGS' => [
                    [
                        'ENTITY_TYPE_ID' => BROKER_ENTITY_TYPE_ID,
                        'ENTITY_ID'      => $brokerId,
                    ],
                ],
            ]);
        } catch (\Throwable $e) {}
    } catch (\Throwable $e) {}
}