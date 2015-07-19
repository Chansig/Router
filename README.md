Chansig/Router
--------------

PHP Router for PHP5.4+ Built-in Server

Works fine in Wordpress, Symfony 2, etc.


## Features

- Specific directory index (app_dev.php for example)
- Allowed Hosts only
- Header Cache control
- Header Access-Control-Allow-Origin
- Handles 404 error page
- Access logs

## Requirement

PHP 5.4+

##  Installation

The recommended way to install Chansig/Router is through
[Composer](http://getcomposer.org).


    # Install Composer
    curl -sS https://getcomposer.org/installer | php


Next, run the Composer command to install the latest stable version of Chansig/Router:

    composer.phar require chansig/router

You can then later update Chansig/Router using composer:

    composer.phar update

## Run PHP Built-in Server

    php -S <addr>:<port> -t <docroot> vendor/chansig/router/router.php
ex:

    php -S localhost:81 vendor/chansig/router/router.php

ex on Symfony:

    php -S 127.0.0.1:8080 -t web vendor/chansig/router/router_symfony_dev.php
  
ex on Wordpress:

    php -S localhost:81 -t wordpress vendor/chansig/router/router.php

## Using Symfony console

    app/console server:run 127.0.0.1:80 --router=vendor/chansig/router/router_symfony_dev.php --docroot=web
    
## php.ini

override ini values

     php -S localhost:81 -t wordpress -c vendor/chansig/router/router.ini vendor/chansig/router/router.php
     php -S localhost:81 -t wordpress -c my.ini router.php
    
## Specific configuration

- copy vendor/chansig/router/router.php.dist in your main directory
- rename router.php.dist to router.php
- require Composer's autoloader in router.php:

        #router.php
        require 'vendor/autoload.php';
        
- change config values in router.php if needed:

        #router.php
        $config = [
            "directory-index" => "index.php", # Directory index filename.
            "allowed-hosts" => [], # List of allowed hosts if not empty.
            "allow-origin" => true, # Send Header Access-Control-Allow-Origin: *. Useful for fonts files required on local CDN.
            "handle-404" => false, # Server handles 404 error page.
            "cache" => 0, # Send http cache headers if > 0. Ex: Cache-Control: public, max-age=300
            "log" => true # Write access logs to output
        ];
        return (new Chansig\Router\PhpRouter($config))->run();
        
- run server

        php -S localhost:80 router.php

## License

MIT License

## Author

[Chansig](https://github.com/Chansig).