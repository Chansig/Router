<?php

require __DIR__ . '/../../../../vendor/autoload.php';

use Chansig\Router\PhpRouter;

$config = ['directory-index' => ['app_dev.php']];
PhpRouter::configure($config);

if ($prepend = PhpRouter::prepend()) {
    require $prepend;
}

if (is_bool($result = PhpRouter::run())) {
    return $result;
} else {
    require $result;
}
