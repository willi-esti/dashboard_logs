<?php

require_once 'utils.php';
require_once 'middleware.php';

$dotenv = Dotenv\Dotenv::createImmutable(__DIR__ . '/../..');
$dotenv->load();

authenticate();

