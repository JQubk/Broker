<?php

require $_SERVER['DOCUMENT_ROOT'] . '/include/broker_handler.php';

broker_logout();

header('Location: /auth/');
exit;
