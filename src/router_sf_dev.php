<?php

require __DIR__ . '/../../../../vendor/autoload.php';

use Chansig\Router\PhpRouter;

$config = ['directory-index' => ['app_dev.php']];
$router = new PhpRouter($config);

if ($prepend = $router->prepend()) {
    include $prepend;
}

if (is_bool($result = $router->run())) {
    return $result;
} elseif (is_resource($result) && 'stream' === get_resource_type($result)) {
    echo stream_get_contents($result);
    fclose($result);
} else {
    include($result);
}
