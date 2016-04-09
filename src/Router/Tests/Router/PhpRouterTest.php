<?php

namespace Chansig\Router\Tests\Router;

use Chansig\Router\PhpRouter;

class PhpRouterTest extends \PHPUnit_Framework_TestCase
{

    protected $reflection;

    protected $config = [
        "hosts-name" => [],
        "port" => null,
        "docroot" => null,
        "directory-index" => ["index.php", "index.html"],
        "rewrite-index" => null,
        "allow-origin" => null,
        "handle-404" => false,
        "cache-control" => null,
        "log" => true,
        "logs-dir" => null,
        "include" => [],
        "vhosts" => [],
        "auto-index-file" => null,
        "render-ssi" => null,
        "ext-ssi" => ['shtml', 'html', 'htm'],
    ];

    public function setUp()
    {
        $this->reflection = new \ReflectionClass('Chansig\Router\PhpRouter');
        $_SERVER['HTTP_HOST'] = 'foo';
        $_SERVER['REQUEST_URI'] = '/foo';
        $_SERVER['REMOTE_ADDR'] = '127.0.0.1';
        $_SERVER['REMOTE_PORT'] = 80;
    }


    public function testConfig()
    {
        $router = new PhpRouter();

        $property = $this->reflection->getProperty('config');
        $property->setAccessible(true);
        $this->assertTrue($property->getValue($router) == $this->config, 'Check config');
    }

    public function testConstructor()
    {
        $config = [
            "hosts-name" => ['www.foo.bar', 'www.bar.foo'],
            "port" => null,
            "docroot" => null,
            "directory-index" => ['foo.bar', 'bar.foo'],
            "rewrite-index" => ['bar.foo.bar'],
            "allow-origin" => ['*', 'cdn.foo.bar'],
            "handle-404" => true,
            "cache-control" => 699,
            "log" => false,
            "logs-dir" => null,
            "include" => [],
            "auto-index-file" => 'foo.bar.foo',
            "render-ssi" => null,
            "ext-ssi" => ['shtml', 'html', 'htm'],
            "vhosts" => []
        ];

        $router = new PhpRouter($config);

        $property = $this->reflection->getProperty('config');
        $property->setAccessible(true);
        $this->assertTrue($property->getValue($router) == $config, 'Check configuration');
    }

    /**
     * @expectedException        \InvalidArgumentException
     * @expectedExceptionMessage /foobar is not a valid docroot directory. Check your configuration syntax.
     */
    public function testConfigureInvalidDocRoot()
    {
        $config = [
            "docroot" => '/foobar'
        ];
        new PhpRouter($config);
    }

    /**
     * @expectedException        \InvalidArgumentException
     * @expectedExceptionMessage /foobar is not a valid docroot directory. Check your configuration syntax.
     */
    public function testConfigureInvalidDocRootInVhost()
    {
        $config = [
            "vhosts" => [
                "foo" => [
                    "hosts-name" => ['foo'],
                    "docroot" => '/foobar'
                ]
            ]
        ];
        new PhpRouter($config);
    }

    /**
     * @expectedException        \InvalidArgumentException
     * @expectedExceptionMessage /foobar is not a valid logs directory. Check your configuration syntax.
     */
    public function testConfigureInvalidLogDir()
    {
        $config = [
            "logs-dir" => '/foobar'
        ];
        new PhpRouter($config);
    }

    /**
     * @expectedException        \InvalidArgumentException
     * @expectedExceptionMessage /foobar is not a valid logs directory. Check your configuration syntax.
     */
    public function testConfigureInvalidLogDirInVhost()
    {
        $config = [
            "vhosts" => [
                "foo" => [
                    "hosts-name" => ['foo'],
                    "logs-dir" => '/foobar'
                ]
            ]
        ];
        new PhpRouter($config);
    }

    /**
     * @expectedException        \InvalidArgumentException
     * @expectedExceptionMessage /foo/rooter.json does not exist.
     */
    public function testConfigureNotFoundIncludeFile()
    {
        $config = [
            "include" => [
                "/foo/rooter.json"
            ]
        ];
        new PhpRouter($config);
    }

    /**
     * @expectedException        \InvalidArgumentException
     */
    public function testConfigureInvalidIncludeFile()
    {
        $config = [
            "include" => [
                __DIR__ . '/Resources/badrouter.json'
            ]
        ];
        new PhpRouter($config);
    }

    public function testConfigureValidIncludeFile()
    {
        $config = [
            "include" => [
                __DIR__ . '/Resources/router.json'
            ]
        ];
        $router = new PhpRouter($config);
        $includeConf = json_decode('{
    "vhosts": {
        "www": {
            "hosts-name": [
                "dev.www.([a-z]+).([a-z]+)"
            ],
            "docroot": "/var/www/www.$1.$2/web",
            "directory-index": [
                "app_dev.php"
            ],
            "allow-origin": null,
            "handle-404": false,
            "cache": null,
            "log": true,
            "logs-dir": "/var/www/www.$1.$2/app/logs",
            "auto-index-file": null
        },
        "cdn": {
            "hosts-name": [
                "dev.cdn.www.([a-z]+).([a-z]+)"
            ],
            "port": 8080,
            "docroot": "/var/www/www.$1.$2/web",
            "directory-index": [
                "app_dev.php"
            ],
            "allow-origin": ["*"],
            "handle-404": false,
            "cache": 86400,
            "log": true,
            "logs-dir": "/var/www/www.$1.$2/app/logs",
            "auto-index-file": null
        }
    }
}', true);

        $property = $this->reflection->getProperty('config');
        $property->setAccessible(true);
        $this->assertTrue($property->getValue($router)['vhosts'] == $includeConf['vhosts'], 'Check configuration');
    }
}
