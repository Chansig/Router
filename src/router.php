<?php

require __DIR__ . '/../../../../vendor/autoload.php';

use Chansig\Router\PhpRouter;

$router = new PhpRouter();

if ($prepend = $router->prepend()) {
    include $prepend;
}

if (is_bool($result = $router->run())) {
    return $result;
} elseif (is_array($result)) {
    foreach ($result as $file) {
        include($file);
    }
} else {
    echo $result;
}