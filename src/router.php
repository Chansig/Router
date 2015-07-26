<?php

require __DIR__ . '/../../../../vendor/autoload.php';

$router = new Chansig\Router\PhpRouter();

if ($prepend = $router->prepend()) {
    include $prepend;
}

if (is_bool($result = $router->run())) {
    return $result;
} else {
    include($result);
}

if ($append = $router->append()) {
    include $append;
}

