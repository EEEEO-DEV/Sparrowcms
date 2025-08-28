<?php
declare(strict_types=1);
session_start();
$app = require __DIR__ . '/../bootstrap/app.php';
$app->run();
