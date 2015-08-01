<?php

require __DIR__ . '/../../../../vendor/autoload.php';

use Chansig\Router\PhpRouter;

$config = json_decode(file_get_contents(__DIR__ . '/router.json'), true);
PhpRouter::configure($config);

if ($prepend = PhpRouter::prepend()) {
    include $prepend;
}

if (is_bool($result = PhpRouter::run())) {
    return $result;
} else {
    include($result);
}