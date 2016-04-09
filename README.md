Chansig/Router
--------------

PHP Router for PHP5.4+ Built-in Server

See http://php.net/manual/en/features.commandline.webserver.php


Works on:

- Wordpress
- Symfony 2,
- Laravel,
- phpMyAdmin,
- bolt,
- etc.

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

## Warning

The built-in PHP web server is NOT meant to be a replacement for any production web server. It should used for development purposes only!

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

e.g.
    php -S localhost:80 vendor/chansig/router/src/router.php

e.g. on Symfony:

    php -S 127.0.0.1:8080 -t web vendor/chansig/src/router/router_symfony_dev.php
  
e.g. on Wordpress:

    php -S localhost:81 -t wordpress vendor/chansig/src/router/router.php

## Using Symfony console

    app/console server:run 127.0.0.1:80 --router=vendor/chansig/src/router/router_symfony_dev.php --docroot=web
    
## php.ini

override ini values

     php -S localhost:81 -t wordpress -c my.ini router.php

## Configuration

- copy vendor/chansig/src/router/router.php in your main directory
- require Composer's autoloader in router.php:

        #router.php
        require 'vendor/autoload.php';
        

### Override configuration

- copy vendor/chansig/src/router/router.json.example in your main directory
- copy router.json.example to router.json

Set configuration values in router.json:

       
-   "hosts-name"  
    @var string[] ***[]***  
    List of allowed hosts if not empty.    
        

-   "port"  
    @var null|int ***null***  
    Which tcp/ip port does the server run on.
    Default null for all ports
  
  
-   "docroot"  
    @var null|string ***null***  
    Override Server DOCUMENT_ROOT if not null


-   "directory-index"  
    @var null|array ***["index.php", "index.html"]***  
    Directory index filenames.  


-   "rewrite-index"  
    @var null|array ***null***  
    Rewrite filenames in docroot.  

    e.g.
        
            "rewrite-index": ["app_dev.php"]  
            
-  "allow-origin"  
    @var null|array ***null***  
    Send Header Access-Control-Allow-Origin for defined hosts.  
    Useful for fonts files required on local CDN or Ajax requests.  
    
    e.g.
        
            "allow-origin": ["*"]


-   "handle-404"  
    @var bool ***false***  
    Router handles 404 error page.  
    

-   "cache-control"
    @var null|int ***null***  
    Send http cache headers if > 0. Ex: Cache-Control: public, max-age=300  

    e.g.
            
            "cache-control": 86400

-   "render-ssi"
    @var bool   false
    Render SSI include virtual & file.

    e.g.
            
            "render-ssi": true


-  "ext-ssi"
    @var null|array ***["shtml", "html", "htm"]*** 
    File extensions that contains an SSI directive.

    e.g.
            
            "ext-ssi": ["shtml"]


-   "log"  
    @var bool ***true***  
    Send access logs to output if true
    

-   "logs-dir"  
    @var null|string  ***null***  
    Write access logs to logs-dir if not null  
    
    e.g.
            
            "logs-dir": "/Users/Toto/Sites/mysite.fr/app/logs"


-   "auto-index-file"  
    @var null|string  ***null***  
    PHP index Directory for directory listing. @see Chansig/DirectoryIndex.  
    
    To add auto-index-file, composer install chansig/directoryindex.  
    Set "auto-index-file" to absolute path of file vendor/chansig/directoryindex/src/directory.php.  
    
    e.g.
            
            "auto-index-file": "/var/www/myphotos/vendor/chansig/directoryindex/src/directory.php"  


-   "vhosts"  
    @var object  ***null***  
    List of virtual hosts.  
    
    You must define server(s) name and document root for each vhost.  
    Configuration is the same as the global configuration.  
    Vhost configuration is merged into global configuration.  
    
    e.g.  
        
        #router.json
        {
            "hosts-name":  [],
            "port": null,
            "docroot": null,
            "directory-index": ["index.php"],
            "rewrite-index": null,
            "allow-origin":  null,
            "allow-origin":  null,
            "handle-404":  false,
            "cache-control":  0,
            "log":  true,
            "logs-dir":  null,
            "auto-index-file":  null,
            "vhosts":{
                "serverkey1": {
                    "hosts-name": ["dev.mysite.ltd", "dev.www.mysite.ltd"],
                    "docroot": "/var/www/www.mysite.tld",
                    "rewrite-index": ["app_dev.php"],
                    "logs-dir": "/var/log/php/mysite.ltd",
                },
                "serverkey1": {
                    "hosts-name": ["cdn.dev.mysite.ltd""],
                    "port": 8080,
                    "docroot": "/var/www/www.mysite.tld",
                    "directory-index": ["index.html", "mydirectoryindex.php"],
                    "allow-origin": null,
                    "cache-control": 43200,
                    "handle-404": true,
                    "log": false
                }
            }
        }

    
    In vhosts configuration, **hosts-name** can be a regex. Captured patterns are available in $1 to $n string.  
    
    e.g.
    
        "hosts-name": ["dev.www.([a-z]+).([a-z]+)"],
               
    They will be replaced in **docroot** and **logs-dir** values.
    
    e.g.  
     
        "docroot": "/var/www/www.$1.$2/web",
        "logs-dir": "/var/log/php/www.$1.$2", 


-   "include"  
    @var null|array  ***[]***  
    Include external config files.  
    Don't override main config.  
    
    e.g.
            
            "include": [
                "/Users/Toto/Sites/mysite.fr/router.json"
            ]


You can skip default configuration:  
For exemple, sf2 site on windows:

    #router.json
            {
                "vhosts":{
                    "mysite": {
                        "hosts-name": ["dev.mysite.ltd", "dev.www.mysite.ltd"],
                        "docroot": "C:\\var\\www\\www.mysite.tld\\web",
                        "rewrite-index": ["app_dev.php"],
                        "logs-dir": "C:\\var\\www\\www.mysite.tld\\app\\logs",",
                    },
                    "mysite2": {
                        "hosts-name": ["dev.www.mysite2.ltd"],
                        "docroot": "C:\\var\\www\\www.mysite2.tld\\web",
                        "rewrite-rewrite": ["app_dev.php"],
                        "logs-dir": "C:\\var\\www\\www.mysite2.tld\\app\\logs",",
                    },
                    "directory": {
                        "hosts-name": ["dev.www.mysite3.ltd"],
                        "docroot": "C:\\var\\www\\www.mysite23.tld",
                        "auto-index-file": "C:\\var\\www\\vendor\\chansig\\directoryindex\\directory.php"
                    },
                }
            }

- load config into router

        $config = json_decode(file_get_contents(__DIR__ . '/router.json'), true);
        $router = new PhpRouter($config);
        
        or
        
        $router = new PhpRouter(__DIR__ . '/router.json');

- run server

        php -S localhost:80 router.php

        To find process:
        ps -ef | grep router.php

## Warning

On OSX, run  sudo php -S 127.0.0.1:{port} router.php for port < 1024


PHP Build In Server is single threaded. So you can not curl (or file_ge_content) to itself on same port. Run an other Server on a new Port to do it.

e.g. php -S 127.0.0.1:80 for front and php -S 127.0.0.1:8000 for api

## License

MIT License

## Author

[Chansig](https://github.com/Chansig).