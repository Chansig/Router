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

    private static $override = false;
    private static $routerFile = self::ROUTER_FILE_NAME;
    private static $configFile = self::CONFIG_FILE_NAME;

    /**
     * @param Event $event
     */
    public static function rootPackageInstall(Event $event)
    {
        static::$override = true;
    }

    /**
     * @param Event $event
     */
    public static function install(Event $event)
    {
        static::createFiles($event, static::$override);
        static::installed($event);
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
     * @param $override
     */
    private static function createFiles(Event $event, $override)
    {
        $routerFile = static::getRouterFile($event);
        $routerDistFile = static::getRouterDistFile($event);
        static::createFile($event, $routerDistFile, $routerFile, $override);

        $configFile = static::getConfigFile($event);
        $configDistFile = static::getConfigDistFile($event);
        if (static::createFile($event, $configDistFile, $configFile, $override)) {
            $content = file_get_contents($routerFile);
            $content = str_replace(self::CONFIG_FILE_NAME, static::$configFile, $content);
            file_put_contents($routerFile, $content);
        }
    }

    /**
     * @param Event $event
     * @param $source
     * @param $destination
     * @param $override
     */
    private static function createFile(Event $event, $source, $destination, $override)
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
        } elseif ($override) {
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
    private static function getRouterFile(Event $event)
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
    private static function getConfigFile(Event $event)
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
    private static function getConfigDistFile(Event $event)
    {
        return realpath(__DIR__ . '/../../../dist/router.json.dist');
    }

    /**
     * @param Event $event
     */
    private static function installed(Event $event)
    {
        $event->getIO()->write(sprintf('

| ___ \                 | |       | |
| |_/ /  ___   __ _   __| | _   _ | |
|    /  / _ \ / _` | / _` || | | || |
| |\ \ |  __/| (_| || (_| || |_| ||_|
\_| \_| \___| \__,_| \__,_| \__, |(_)
                             __/ |
                            |___/
Run now "php -S 127.0.0.1:80 router.php"
To admin the server, edit %s', static::$configFile));
    }

}
