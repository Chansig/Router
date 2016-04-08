<?php

namespace Chansig\Router\Composer;

use Composer\Script\Event;

/**
 * Class ScriptHandler
 * @package Chansig\Router\Composer
 */
class ScriptHandler
{
    const  ROUTER_FILE_NAME = 'router.php';
    const  CONFIG_FILE_NAME = 'router.json';
    const  SERVER_FILE_NAME = 'server.sh';
    const  SERVER_HOST = '127.0.0.1';
    const  SERVER_PORT = 8000;

    private static $routerFile = self::ROUTER_FILE_NAME;
    private static $configFile = self::CONFIG_FILE_NAME;
    private static $serverFile = self::SERVER_FILE_NAME;
    private static $serverPort = self::SERVER_PORT;
    private static $serverHost = self::SERVER_HOST;

    /**
     * @param Event $event
     */
    public static function install(Event $event)
    {
        static::createFiles($event);
    }

    /**
     * @param Event $event
     */
    public static function update(Event $event)
    {
        static::install($event);
    }

    /**
     * @param Event $event
     */
    private static function createFiles(Event $event)
    {
        $routerFile = static::getRouterFile($event);
        $routerDistFile = static::getRouterDistFile($event);
        static::createFile($event, $routerDistFile, $routerFile);

        $configFile = static::getConfigFile($event);
        $configDistFile = static::getConfigDistFile($event);
        if (static::createFile($event, $configDistFile, $configFile)) {
            $content = file_get_contents($routerFile);
            $content = str_replace(self::CONFIG_FILE_NAME, static::$configFile, $content);
            file_put_contents($routerFile, $content);
        }

        $serverFile = static::getServerFile($event);
        $serverDistFile = static::getServerDistFile($event);
        if (static::createFile($event, $serverDistFile, $serverFile)) {
            $content = file_get_contents($serverFile);
            $content = str_replace(self::ROUTER_FILE_NAME, static::$routerFile, $content);
            $content = str_replace(self::SERVER_PORT, static::$serverPort, $content);
            $content = str_replace(self::SERVER_HOST, static::$serverHost, $content);
            file_put_contents($serverFile, $content);
        }
    }

    /**
     * @param Event $event
     * @param $source
     * @param $destination
     */
    private static function createFile(Event $event, $source, $destination)
    {
        if (!file_exists($destination)) {
            $question = sprintf('The file [%s] will be created. OK? (y)', $destination);
            $response = $event->getIO()->askConfirmation($question, true);
            if ($response) {
                copy($source, $destination);
                $event->getIO()->write(sprintf("<info>[%s] has been created.</info>", $destination));
                return true;
            } else {
                $event->getIO()->write(sprintf("<warning>[%s] has not been created.</warning>", $destination));
            }
        } else {
            $question = sprintf('/!\ The file [%s] already exists. Override? (n)', $destination);
            $response = $event->getIO()->askConfirmation($question, false);
            if ($response) {
                copy($source, $destination);
                $event->getIO()->write(sprintf("<warning>[%s] has been overridden.</warning>", $destination));
                return true;
            } else {
                $event->getIO()->write(sprintf("<warning>[%s] has not been overridden.</warning>", $destination));
            }
        }
    }

    /**
     * @param Event $event
     * @return string
     */
    public static function getRouterFile(Event $event)
    {
        $extras = $event->getComposer()->getPackage()->getExtra();
        if (isset($extras['chansig-router-parameters'])) {
            $configs = $extras['chansig-router-parameters'];
            if (!is_array($configs)) {
                throw new \InvalidArgumentException('The extra.chansig-router-parameters setting must be an array.');
            }
            if (isset($configs['router-file'])) {
                if (!is_string($configs['router-file'])) {
                    throw new \InvalidArgumentException('The extra.chansig-router-parameters.router-file setting must be a string.');
                }
                static::$routerFile = $configs['router-file'];
            }
        }

        $vendorDir = $event->getComposer()->getConfig()->get('vendor-dir');
        return realpath(sprintf('%s/../', $vendorDir)) . DIRECTORY_SEPARATOR . static::$routerFile;
    }

    /**
     * @param Event $event
     * @return string
     */
    private static function getRouterDistFile(Event $event)
    {
        return realpath(__DIR__ . '/../../../dist/router.php.dist');
    }

    /**
     * @param Event $event
     * @return string
     */
    public static function getConfigFile(Event $event)
    {
        $extras = $event->getComposer()->getPackage()->getExtra();
        if (isset($extras['chansig-router-parameters'])) {
            $configs = $extras['chansig-router-parameters'];
            if (!is_array($configs)) {
                throw new \InvalidArgumentException('The extra.chansig-router-parameters setting must be an array.');
            }
            if (isset($configs['config-file'])) {
                if (!is_string($configs['config-file'])) {
                    throw new \InvalidArgumentException('The extra.chansig-router-parameters.config-file setting must be a string.');
                }
                static::$configFile = $configs['config-file'];
            }
        }
        $vendorDir = $event->getComposer()->getConfig()->get('vendor-dir');
        return realpath(sprintf('%s/../', $vendorDir)) . DIRECTORY_SEPARATOR . static::$configFile;
    }

    /**
     * @param Event $event
     * @return string
     */
    private static function getServerFile(Event $event)
    {
        $extras = $event->getComposer()->getPackage()->getExtra();
        if (isset($extras['chansig-router-parameters'])) {
            $configs = $extras['chansig-router-parameters'];
            if (!is_array($configs)) {
                throw new \InvalidArgumentException('The extra.chansig-router-parameters setting must be an array.');
            }
            if (isset($configs['server-file'])) {
                if (!is_string($configs['server-file'])) {
                    throw new \InvalidArgumentException('The extra.chansig-router-parameters.server-file setting must be a string.');
                }
                static::$serverFile = $configs['server-file'];
            }
        }
        $vendorDir = $event->getComposer()->getConfig()->get('vendor-dir');
        return realpath(sprintf('%s/../', $vendorDir)) . DIRECTORY_SEPARATOR . static::$serverFile;
    }

    /**
     * @param Event $event
     * @return string
     */
    private static function getConfigDistFile(Event $event)
    {
        return realpath(__DIR__ . '/../../../dist/router.json.dist');
    }

    /**
     * @param Event $event
     * @return string
     */
    private static function getServerDistFile(Event $event)
    {
        return realpath(__DIR__ . '/../../../dist/server.sh.dist');
    }

    /**
     * @param Event $event
     */
    public static function installed(Event $event)
    {
        $event->getIO()->write(sprintf('
______                   _         _
| ___ \                 | |       | |
| |_/ /  ___   __ _   __| | _   _ | |
|    /  / _ \ / _` | / _` || | | || |
| |\ \ |  __/| (_| || (_| || |_| ||_|
\_| \_| \___| \__,_| \__,_| \__, |(_)
                             __/ |
                            |___/

Run now "php -S 127.0.0.1:80 router.php. To add vhosts, edit %s', static::$configFile));
    }

}
