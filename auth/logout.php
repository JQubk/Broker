<?php
require $_SERVER['DOCUMENT_ROOT'] . '/local/classes/BrokerCabinet/autoload.php';

use BrokerCabinet\AuthService;

$authService = new AuthService();
$authService->logout();

header('Location: /auth/');
exit;