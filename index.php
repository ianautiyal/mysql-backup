<?php
include __DIR__ . '/vendor/autoload.php';
include __DIR__ . '/app.php';

$dotenv = new Dotenv\Dotenv(__DIR__);
$dotenv->load();

$class = new MoveAndUpdate;
$detail = $class->moveDataToS3();
$status = $class->updateSpreadsheet($detail);
return $status;
