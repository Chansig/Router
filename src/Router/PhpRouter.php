<?php

namespace Chansig\Router;

/**
 * Class PhpRouter
 *
 * @package Chansig\Router
 */
class PhpRouter
{
    const VENDOR_NAME = 'chansig/router';
    const VERSION = '0.7.1';
    /**
     * @var array
     */
    protected static $defaultConfig = [
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
        "vhosts" => [],
        "auto-index-file" => null,
        "render-ssi" => null,
        "ext-ssi" => ['shtml', 'html', 'htm'],
    ];

    /**
     * @var array
     */
    protected $config = [];
    /**
     * @var string
     */
    protected $extension = '';

    /**
     * @var string
     */
    protected $scriptFilename = '';

    /**
     * @var string
     */
    protected $originalScriptFilename = '';

    /**
     * @var string
     */
    protected $docroot;

    /**
     * @var string
     */
    protected $host = '';

    /**
     * @var int
     */
    protected $port = '';

    /**
     * @var string
     */
    protected $originaDocRoot;

    /**
     * @var string
     */
    protected static $originalConfigFile;

    /**
     * @var string
     */
    public $output = 'php://stdout';

    /**
     * @param string $configFile
     */
    public function __construct($configFile = '')
    {
        $this->config = static::$defaultConfig;
        $config = [];
        if (is_array($configFile)) {
            $config = $configFile;
        } elseif (is_string($configFile) && !empty($configFile) && file_exists($configFile)) {
            $config = json_decode(file_get_contents($configFile), true);
            static::$originalConfigFile = $configFile;
        } elseif (is_string($configFile) && !empty($configFile) && !file_exists($configFile)) {
            $this->error(500, 'config file does not exist');
        }
        if (!empty($config)) {
            $this->configure($config);
        }
        $this->initFromGlobals();
    }

    /**
     * @return array
     */
    public static function getDefaultConfig()
    {
        return static::$defaultConfig;
    }

    /**
     * @return string
     */
    public static function getOriginalConfigFile()
    {
        return static::$originalConfigFile;
    }

    /**
     * @param $config
     * @return bool
     */
    protected function configure($config)
    {
        $this->config = array_merge($this->config, $config);
        if (!$this->configureVhosts()) {
            $this->error(404);
        }
        if (!is_null($this->config['docroot']) && !is_dir($this->config['docroot'])) {
            throw new \InvalidArgumentException(sprintf('%s is not a valid docroot directory. Check your configuration syntax.', $this->config['docroot']));
        }
        if (!is_null($this->config['logs-dir']) && !is_dir($this->config['logs-dir'])) {
            throw new \InvalidArgumentException(sprintf('%s is not a valid logs directory. Check your configuration syntax.', $this->config['logs-dir']));
        }

    }

    /**
     * @throws \Exception
     */
    protected function initFromGlobals()
    {
        $path = parse_url($_SERVER["REQUEST_URI"], PHP_URL_PATH);
        $exp = explode(':', $_SERVER['HTTP_HOST']);

        $this->extension = pathinfo($path, PATHINFO_EXTENSION);
        $this->originaDocRoot = $_SERVER['DOCUMENT_ROOT'];
        $this->host = $exp[0];
        $this->port = $this->getCurrentPort();
        if (!is_null($this->config['docroot'])) {
            $this->docroot = $this->config['docroot'];
        } else {
            $this->docroot = $this->originaDocRoot;
        }

        $this->scriptFilename = $this->docroot . str_replace('/', DIRECTORY_SEPARATOR, $_SERVER['SCRIPT_NAME']);
        $this->originalScriptFilename = $this->originaDocRoot . str_replace('/', DIRECTORY_SEPARATOR, $_SERVER['SCRIPT_NAME']);

        $this->setGlobals();
    }

    /**
     * @return int
     */
    protected function getCurrentPort()
    {
        $exp = explode(':', $_SERVER['HTTP_HOST']);
        return isset($exp[1]) ? (int)$exp[1] : 80;
    }

    /**
     * @return bool|string
     */
    public function prepend()
    {
        if (ini_get('auto_prepend_file') && !in_array(realpath(ini_get('auto_prepend_file')), get_included_files(), true)) {
            return ini_get('auto_prepend_file');
        }
        return false;
    }

    /**
     * @return bool|string
     */
    public function append()
    {
        if (ini_get('auto_append_file') && !in_array(realpath(ini_get('auto_append_file')), get_included_files(), true)) {
            return ini_get('auto_append_file');
        }
        return false;
    }

    /**
     * @return array|bool|string
     */
    public function run()
    {
        if (!$this->IsAuthorized()) {
            $this->error(403);
        }

        header(sprintf('X-Router: %s %s', static::VENDOR_NAME, static::VERSION));

        if (is_file($this->scriptFilename)) {
            if ('php' === $this->extension) {
                chdir(dirname($this->scriptFilename));
                return array($this->scriptFilename);
            }
            return $this->readFile();
        } elseif (is_null($this->config['rewrite-index']) && is_dir($this->scriptFilename)) {
            return $this->executeDirectory();
        } elseif (!is_null($this->config['rewrite-index'])) {
            return $this->executeRewrite();
        } else {
            $this->error(404);
        }
    }


    /**
     *
     */
    protected function configureVhosts()
    {
        $this->host = explode(':', $_SERVER['HTTP_HOST'])[0];

        if (!empty($this->config['vhosts'])) {
            foreach ($this->config['vhosts'] as $vhost) {
                if (in_array($this->host, $vhost['hosts-name'])) {
                    if (isset($vhost['port']) && !is_null($vhost['port']) && $vhost['port'] != $this->getCurrentPort()) {
                        return false;
                    }
                    $this->config = array_merge($this->config, $vhost);
                    unset($this->config['vhosts']);
                    return true;
                } else {
                    foreach ($vhost['hosts-name'] as $host) {
                        $pattern = "@" . $host . "@";
                        if (preg_match($pattern, $this->host, $matches)) {
                            for ($i = 1; $i < count($matches); $i++) {
                                $vhost['hosts-name'] = [$this->host];
                                if (isset($vhost['docroot']) && !is_null($vhost['docroot'])) {
                                    $vhost['docroot'] = str_replace('$' . $i, $matches[$i], $vhost['docroot']);
                                }
                                if (isset($vhost['logs-dir']) && !is_null($vhost['logs-dir'])) {
                                    $vhost['logs-dir'] = str_replace('$' . $i, $matches[$i], $vhost['logs-dir']);
                                }
                            }
                            if (isset($vhost['port']) && !is_null($vhost['port']) && $vhost['port'] != $this->getCurrentPort()) {
                                return false;
                            }
                            $this->config = array_merge($this->config, $vhost);
                            return true;
                        }
                    }
                }
            }
        }
        return true;
    }

    /**
     *
     */
    protected function  setGlobals()
    {
        $_SERVER['DOCUMENT_ROOT'] = $this->docroot;
        $_SERVER['SCRIPT_FILENAME'] = $this->scriptFilename;
        $GLOBALS['_SERVER'] = $_SERVER;
    }

    /**
     * @return bool
     */
    protected function IsAuthorized()
    {
        if (!empty($this->config['hosts-name'])) {
            if (!in_array($this->host, $this->config['hosts-name']) && !in_array($_SERVER['REMOTE_ADDR'], $this->config['hosts-name'])) {
                return false;
            }
        }
        return true;
    }


    /**
     * @return bool|string
     */
    protected function readFile()
    {
        if (in_array($this->extension, $this->getSupportedMime()) && is_null($this->config['allow-origin']) && null === $this->config['cache-control'] && file_exists($this->originalScriptFilename)) {
            return $this->send();
        } elseif (file_exists($this->scriptFilename)) {
            if (array_key_exists($this->extension, $this->getMimeTypes())) {
                $types = $this->getMimeTypes()[$this->extension];
                if (!is_array($types)) {
                    $types = [$types];
                }
                foreach ($types as $type) {
                    header(sprintf('Content-type: %s', $type));
                }
            }
            if (!is_null($this->config['allow-origin'])) {
                foreach ($this->config['allow-origin'] as $origin) {
                    header(sprintf('Access-Control-Allow-Origin: %s', $origin));
                }
            }
            if (!is_null($this->config['cache-control'])) {
                header(sprintf('Cache-Control: public, max-age=%1$d', $this->config['cache-control']));
            }
            return $this->send($this->scriptFilename);
        }
        return $this->send();
    }

    /**
     * @return bool|string
     */
    protected function executeDirectory()
    {
        chdir($_SERVER['SCRIPT_FILENAME']);

        foreach ($this->config['directory-index'] as $index) {
            // directory-index (index.php or index.html) exists in directory
            if (file_exists($_SERVER['SCRIPT_FILENAME'] . DIRECTORY_SEPARATOR . $index)) {
                chdir(dirname($_SERVER['SCRIPT_FILENAME'] . DIRECTORY_SEPARATOR . $index));
                $_SERVER['SCRIPT_FILENAME'] = $_SERVER['SCRIPT_FILENAME'] . DIRECTORY_SEPARATOR . $index;
                return $this->send($_SERVER['SCRIPT_FILENAME']);
            }
        }

        // auto-index-file has been set in router.json. @ see chansig/directory
        if ($this->config['auto-index-file'] && file_exists($this->config['auto-index-file'])) {
            return $this->send($this->config['auto-index-file']);
        } elseif ($this->config['handle-404'] && $_SERVER['SCRIPT_NAME'] !== '/') {
            $this->error(404);
        }

        $this->error(403);
    }

    /**
     * @return bool
     */
    protected function executeRewrite()
    {
        foreach ($this->config['rewrite-index'] as $index) {
            // rewrite-index router exists (app.php) exist in docroot
            if (file_exists($this->docroot . DIRECTORY_SEPARATOR . $index)) {
                chdir(dirname($this->docroot . DIRECTORY_SEPARATOR . $index));
                $_SERVER['SCRIPT_FILENAME'] = $this->docroot . DIRECTORY_SEPARATOR . $index;
                $_SERVER['SCRIPT_NAME'] = "/" . $index;
                return $this->send($_SERVER['SCRIPT_FILENAME']);
            }
        }

        $this->error(403);
    }

    /**
     * @param string $file
     * @param bool $read
     * @return bool|string
     */
    protected function send($file = '', $read = false)
    {
        $this->logAccess('200');
        header('HTTP/1.0 200 OK');

        if ('' === $file) {
            if ($this->hasSSI($this->scriptFilename)) {
                return $this->renderSSI(@file_get_contents($this->scriptFilename));
            }
            return false;
        } else {
            if ($this->hasSSI($file)) {
                return $this->renderSSI(@file_get_contents($file));
            }
            return [$file];
        }

    }

    /**
     * @param $file
     * @return bool
     */
    public function hasSSI($file)
    {
        if (file_exists($file)) {
            $extension = pathinfo($file, PATHINFO_EXTENSION);
            return $this->config['render-ssi'] && is_array($this->config['ext-ssi']) && in_array($extension, $this->config['ext-ssi']);
        }
        return false;
    }

    /**
     * @param $content
     * @return mixed
     */
    protected function renderSSI($content)
    {
        try {
            $pattern = '/<!--([\s\t]?)#include([\s\t]?)virtual="([^"]*)"([\s\t]?)-->/sU';
            $content = preg_replace_callback($pattern, array($this, 'replaceVirtual'), $content);
            $content = $this->includeExpr($content);

            $pattern = '/<!--([\s\t]?)#include([\s\t]?)file="([^"]*)"([\s\t]?)-->/sU';
            $content = preg_replace_callback($pattern, array($this, 'replaceFile'), $content);
            $content = $this->includeExpr($content);

            return $content;
        } catch (\Exception $e) {
            $this->error(500, $e->getMessage());
        }
    }

    protected function replaceFile($url)
    {
        return $this->renderSSI(@file_get_contents(getcwd() . DIRECTORY_SEPARATOR . $url[3]));
    }

    protected function replaceVirtual($url)
    {
        return $this->renderSSI(@file_get_contents($this->docroot . DIRECTORY_SEPARATOR . $url[3]));
    }

    /**
     * remove #if expr
     *
     * @param $content
     * @return mixed
     */
    protected function includeExpr($content)
    {
        try {
            $pattern = '/<!--#if([\s\t]?)expr="([^"]*)"([\s\t]?)-->([^"]*)<!--#endif-->/sU';
            $content = preg_replace($pattern, '', $content);
            return $content;
        } catch (\Exception $e) {
            $this->error(500);
        }
    }


    /**
     * @return array
     */
    protected function getSupportedMime()
    {
        $mime = ['html'];

        $mime5_5_12 = ['xml', 'xsl', 'xsd'];
        $mime5_5_7 = ['3gp', 'apk', 'avi', 'bmp', 'css', 'csv', 'doc', 'docx', 'flac', 'gif', 'gz', 'gzip', 'htm', 'html', 'ics', 'jpe', 'jpeg', 'jpg', 'js', 'kml', 'kmz', 'm4a', 'mov', 'mp3', 'mp4', 'mpeg', 'mpg', 'odp', 'ods', 'odt', 'oga', 'png', 'pps', 'pptx', 'qt', 'svg', 'swf', 'tar', 'text', 'tif', 'txt', 'wav', 'wmv', 'xls', 'xlsx', 'zip'];
        $mime5_5_5 = ['pdf'];
        $mime5_4_11 = ['ogg', 'ogv', 'webm'];
        $mime5_4_4 = ['htm', 'svg'];

        if (version_compare(phpversion(), '5.4.4', '>=')) {
            $mime = array_merge($mime, $mime5_4_4);
        }
        if (version_compare(phpversion(), '5.4.11', '>=')) {
            $mime = array_merge($mime, $mime5_4_11);
        }
        if (version_compare(phpversion(), '5.5.5', '>=')) {
            $mime = array_merge($mime, $mime5_5_5);
        }
        if (version_compare(phpversion(), '5.5.7', '>=')) {
            $mime = array_merge($mime, $mime5_5_7);
        }
        if (version_compare(phpversion(), '5.5.12', '>=')) {
            $mime = array_merge($mime, $mime5_5_12);
        }
        return $mime;
    }

    /**
     * @see https://raw.githubusercontent.com/kbjr/Resources/master/lib/mimes.php
     *
     * @return array
     */
    protected function getMimeTypes()
    {
        return [
            'ico' => 'image/x-icon',
            'hqx' => 'application/mac-binhex40',
            'cpt' => 'application/mac-compactpro',
            'csv' => ['text/x-comma-separated-values', 'text/comma-separated-values', 'application/octet-stream', 'application/vnd.ms-excel', 'text/csv', 'application/csv', 'application/excel', 'application/vnd.msexcel'],
            'bin' => 'application/macbinary',
            'dms' => 'application/octet-stream',
            'lha' => 'application/octet-stream',
            'lzh' => 'application/octet-stream',
            'exe' => 'application/octet-stream',
            'class' => 'application/octet-stream',
            'psd' => 'application/x-photoshop',
            'so' => 'application/octet-stream',
            'sea' => 'application/octet-stream',
            'dll' => 'application/octet-stream',
            'oda' => 'application/oda',
            'pdf' => ['application/pdf', 'application/x-download'],
            'ai' => 'application/postscript',
            'eps' => 'application/postscript',
            'ps' => 'application/postscript',
            'smi' => 'application/smil',
            'smil' => 'application/smil',
            'mif' => 'application/vnd.mif',
            'xls' => ['application/excel', 'application/vnd.ms-excel', 'application/msexcel'],
            'ppt' => ['application/powerpoint', 'application/vnd.ms-powerpoint'],
            'wbxml' => 'application/wbxml',
            'wmlc' => 'application/wmlc',
            'dcr' => 'application/x-director',
            'dir' => 'application/x-director',
            'dxr' => 'application/x-director',
            'dvi' => 'application/x-dvi',
            'gtar' => 'application/x-gtar',
            'gz' => 'application/x-gzip',
            'php' => 'application/x-httpd-php',
            'php4' => 'application/x-httpd-php',
            'php3' => 'application/x-httpd-php',
            'phtml' => 'application/x-httpd-php',
            'phps' => 'application/x-httpd-php-source',
            'js' => 'application/x-javascript',
            'swf' => 'application/x-shockwave-flash',
            'sit' => 'application/x-stuffit',
            'tar' => 'application/x-tar',
            'tgz' => 'application/x-tar',
            'xhtml' => 'application/xhtml+xml',
            'xht' => 'application/xhtml+xml',
            'zip' => ['application/x-zip', 'application/zip', 'application/x-zip-compressed'],
            'mid' => 'audio/midi',
            'midi' => 'audio/midi',
            'mpga' => 'audio/mpeg',
            'mp2' => 'audio/mpeg',
            'mp3' => ['audio/mpeg', 'audio/mpg'],
            'aif' => 'audio/x-aiff',
            'aiff' => 'audio/x-aiff',
            'aifc' => 'audio/x-aiff',
            'ram' => 'audio/x-pn-realaudio',
            'rm' => 'audio/x-pn-realaudio',
            'rpm' => 'audio/x-pn-realaudio-plugin',
            'ra' => 'audio/x-realaudio',
            'rv' => 'video/vnd.rn-realvideo',
            'wav' => 'audio/x-wav',
            'bmp' => 'image/bmp',
            'gif' => 'image/gif',
            'jpeg' => ['image/jpeg', 'image/pjpeg'],
            'jpg' => ['image/jpeg', 'image/pjpeg'],
            'jpe' => ['image/jpeg', 'image/pjpeg'],
            'png' => ['image/png', 'image/x-png'],
            'tiff' => 'image/tiff',
            'tif' => 'image/tiff',
            'css' => 'text/css',
            'html' => 'text/html',
            'htm' => 'text/html',
            'shtml' => 'text/html',
            'txt' => 'text/plain',
            'text' => 'text/plain',
            'log' => ['text/plain', 'text/x-log'],
            'rtx' => 'text/richtext',
            'rtf' => 'text/rtf',
            'xml' => 'text/xml',
            'xsl' => 'text/xml',
            'mpeg' => 'video/mpeg',
            'mpg' => 'video/mpeg',
            'mpe' => 'video/mpeg',
            'qt' => 'video/quicktime',
            'mov' => 'video/quicktime',
            'avi' => 'video/x-msvideo',
            'movie' => 'video/x-sgi-movie',
            'doc' => 'application/msword',
            'docx' => 'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
            'xlsx' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            'word' => ['application/msword', 'application/octet-stream'],
            'xl' => 'application/excel',
            'eml' => 'message/rfc822',
            'eot' => 'application/vnd.ms-fontobject',
            'ttf' => 'font/ttf',
            'otf' => 'font/otf',
            'woff' => 'application/x-woff',
            'woff2' => 'application/x-woff'
        ];
    }

    /**
     * @param int $error
     * @param string $message
     * @param bool|true $log
     */
    protected function error($error = 500, $message = '')
    {
        $this->logAccess($error);

        if (403 === $error) {
            if ('' === $message) {
                $message = sprintf('Access Forbidden for the requested resource <strong>%s</strong> on this server.', $_SERVER['REQUEST_URI']);
            }
        } elseif (404 === $error) {
            if ('' === $message) {
                $message = sprintf('The requested resource <strong>%s</strong> was Not Found on this server.', $_SERVER['REQUEST_URI']);
            }
        } else {
            $error = 500;
        }

        $status = [
            '403' => 'Forbidden',
            '404' => 'Not Found',
            '500' => 'Internal server error',
        ];

        header(sprintf("HTTP/1.0 %d %s", $error, $status[$error]));

        die(sprintf('<!doctype html><html><head><title>%s %s</title><style> * body { background-color: #ffffff; color: #000000; } * h1 { font-family: sans-serif; font-size: 150%%; background-color: #9999cc; font-weight: bold; color: #000000; margin-top: 0;} * </style></head><body><h1>%s</h1><p>%s</p><hr /><p>%s %s</p></body></html>', $error, $status[$error], strtolower($status[$error]), $message, static::VENDOR_NAME, static::VERSION));
    }

    /**
     * @param int $status
     */
    protected function logAccess($status = 200)
    {
        if ($this->config['log'] || $this->config['logs-dir']) {

            if ($status >= 500) {
                $color = 31;// red
            } elseif ($status >= 400) {
                $color = 33;// yellow
            } elseif ($status >= 300) {
                $color = 35;// pink
            } else {
                $color = 32;// green
            }

            $now = new \DateTime('now', new \DateTimeZone('UTC'));
            $coloredLog = sprintf("[%s] \033[%dm%s:%s [%s] [%s]: %s\033[0m\n", $now->format("D M j H:i:s Y"), $color, $_SERVER["REMOTE_ADDR"], $_SERVER["REMOTE_PORT"], $this->host, $status, $_SERVER["REQUEST_URI"]);
            $log = sprintf("[%s] %s:%s [%s] [%s]: %s\n", $now->format("D M j H:i:s Y"), $_SERVER["REMOTE_ADDR"], $_SERVER["REMOTE_PORT"], $this->host, $status, $_SERVER["REQUEST_URI"]);
            if ($this->config['log']) {
                file_put_contents($this->output, $this->hasColorSupport() ? $coloredLog : $log);
            }
            if (!is_null($this->config['logs-dir'])) {
                $filename = sprintf('%s%srouter-access-%s.log', $this->config['logs-dir'], DIRECTORY_SEPARATOR, $this->host);
                error_log($log, 3, $filename);
            }
        }
    }

    /**
     * @return bool
     */
    protected function hasColorSupport()
    {
        if (!ini_get('cli_server.color')) {
            return false;
        }
        // sf2 console
        if (DIRECTORY_SEPARATOR === '\\') {
            return false !== getenv('ANSICON') || 'ON' === getenv('ConEmuANSI');
        }

        return function_exists('posix_isatty') && @posix_isatty(STDOUT);
    }

}
