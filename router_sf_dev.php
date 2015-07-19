<?php

require __DIR__ . '/../../../vendor/autoload.php';

$config = ['directory-index' => 'app_dev.php'];
return (new Chansig\Router\PhpRouter($config))->run();