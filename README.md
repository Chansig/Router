Chansig/Router
--------------

PHP Router for PHP5.4+ Built-in Server

Works fine in Wordpress, Symfony 2, etc.


## Features

- Serve multiple domains with vhosts configuration
- Allow different directory index (app_dev.php for example)
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

    composer.phar require chansig/router dev-master

You can then later update Chansig/Router using composer:

    composer.phar update

## Run PHP Built-in Server

    php -S <addr>:<port> -t <docroot> vendor/chansig/router/src/router.php

ex:
    php -S localhost:80 vendor/chansig/router/src/router.php

ex on Symfony:

    php -S 127.0.0.1:8080 -t web vendor/chansig/src/router/router_symfony_dev.php
  
ex on Wordpress:

    php -S localhost:81 -t wordpress vendor/chansig/src/router/router.php

## Using Symfony console

    app/console server:run 127.0.0.1:80 --router=vendor/chansig/src/router/router_symfony_dev.php --docroot=web
    
## php.ini

override ini values

     php -S localhost:81 -t wordpress -c vendor/chansig/src/router/router.ini vendor/chansig/src/router/router.php
     php -S localhost:81 -t wordpress -c my.ini router.php

## Configuration

- copy vendor/chansig/src/router/router.php in your main directory
- require Composer's autoloader in router.php:

        #router.php
        require 'vendor/autoload.php';
        

### Override configuration

- copy vendor/chansig/src/router/router.json in your main directory

Set configuration values in router.json:

       
-   "hosts-name"  
    @var string[] []  
    List of allowed hosts if not empty.


-   "docroot"  
    @var null|string null  
    Override Server DOCUMENT_ROOT if not null  


-   "directory-index"  
    @var string "index.php"  
    Directory index filename.


-  "allow-origin"  
    @var bool true  
    Send Header Access-Control-Allow-Origin: *.  
    Useful for fonts files required on local CDN.  
        

-   "handle-404"  
    @var bool false  
    Router handles 404 error page.  
    

-   "cache"
    @var int 0  
    Send http cache headers if > 0. Ex: Cache-Control: public, max-age=300  


-   "log"  
    @var bool true
    Send access logs to output if true
    

-   "log-dir"  
    @var null|string  null
    Write access logs to log-dir if not null  
        

-   "auto-index-file"  
    @var null|string  null  
    PHP index Directory for directory listing. @see Chansig/DirectoryIndex.  
    
    To add auto-index-file, composer install chansig/directoryindex.  
    Set "auto-index-file" to absolute path of file vendor/chansig/directoryindex/directory.php.  
    
        ex : /var/www/myphotos/vendor/chansig/directoryindex/directory.php  


-   "vhosts"  
    @var object  
    list of virtual hosts.  
    You must define server(s) name and document root for each vhost.  
    Configuration is the same as the global configuration.  
    Vhost configuration is merged into global configuration.  
    
    ex:  
        
        #router.json
        {
            "hosts-name":  [],
            "docroot": null,
            "directory-index": ["index.php"],
            "allow-origin":  false,
            "handle-404":  false,
            "cache":  0,
            "log":  true,
            "log-dir":  null,
            "auto-index-file":  null,
            "vhosts":{
                "serverkey1": {
                    "hosts-name": ["dev.mysite.ltd", "dev.www.mysite.ltd"],
                    "docroot": "/var/www/www.mysite.tld",
                    "directory-index": ["mydirectoryindex.php"],
                    "log-dir": "/var/log/php/mysite.ltd",
                },
                "serverkey1": {
                    "hosts-name": ["cdn.dev.mysite.ltd""],
                    "docroot": "/var/www/www.mysite.tld",
                    "directory-index": ["index.html", "mydirectoryindex.php"],
                    "allow-origin": true,
                    "cache": 43200,
                    "handle-404": true,
                    "log": false
                }
            }
        }
        
You can skip default configuration:  
For exemple, sf2 site on windows:

    #router.json
            {
                "vhosts":{
                    "mysite": {
                        "hosts-name": ["dev.mysite.ltd", "dev.www.mysite.ltd"],
                        "docroot": "C:\\var\\www\\www.mysite.tld\\web",
                        "directory-index": ["app_dev.php"],
                        "log-dir": "C:\\var\\www\\www.mysite.tld\\app\\logs",",
                    },
                    "mysite2": {
                        "hosts-name": ["dev.www.mysite2.ltd"],
                        "docroot": "C:\\var\\www\\www.mysite2.tld\\web",
                        "directory-index": ["app_dev.php"],
                        "log-dir": "C:\\var\\www\\www.mysite2.tld\\app\\logs",",
                    },
                    "directory": {
                        "hosts-name": ["dev.www.mysite3.ltd"],
                        "docroot": "C:\\var\\www\\www.mysite23.tld",
                        "auto-index-file": "C:\\var\\www\\vendor\\chansig\\directoryindex\\directory.php"
                    },
                }
            }
        
- run server

        php -S localhost:80 router.php

## License

MIT License

## Author

[Chansig](https://github.com/Chansig).