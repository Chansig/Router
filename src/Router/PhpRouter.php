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
    const VERSION = '0.4.0';
    /**
     * @var array
     */
    protected static $config = [
        "hosts-name" => [],
        "docroot" => null,
        "directory-index" => ["index.php", "index.html"],
        "allow-origin" => null,
        "handle-404" => false,
        "cache-control" => 0,
        "log" => true,
        "log-dir" => null,
        "vhosts" => [],
        "auto-index-file" => null,
    ];

    /**
     * @var string
     */
    protected static $extension = '';

    /**
     * @var string
     */
    protected static $scriptFilename = '';

    /**
     * @var string
     */
    protected static $originalScriptFilename = '';

    /**
     * @var string
     */
    protected static $host = '';

    /**
     * @var string
     */
    protected static $originaDocRoot;

    /**
     * @param array $config
     * @throws \Exception
     */
    public static function configure(array $config)
    {
        if (!is_array($config)) {
            throw new \Exception('Invalid configuration. Check your configuration syntax.');
        }
        static::$config = array_merge(static::$config, $config);
        static::configureVhosts();
    }

    /**
     * @throws \Exception
     */
    public static function init()
    {
        static::$host = explode(':', $_SERVER['HTTP_HOST'])[0];
        $path = parse_url($_SERVER["REQUEST_URI"], PHP_URL_PATH);
        static::$extension = pathinfo($path, PATHINFO_EXTENSION);
        static::$originaDocRoot = $_SERVER['DOCUMENT_ROOT'];

        if (!is_null(static::$config['docroot']) && !is_dir(static::$config['docroot'])) {
            static::error(500, 'Invalid docroot. Check your configuration syntax.');
        }
        if (!is_null(static::$config['log-dir']) && !is_dir(static::$config['log-dir'])) {
            static::error(500, sprintf('%s is not a valid log-dir. Check your configuration syntax.', static::$config['log-dir']));
        }
        if (!is_null(static::$config['docroot'])) {
            $_SERVER['DOCUMENT_ROOT'] = static::$config['docroot'];
        }
        static::$scriptFilename = $_SERVER['DOCUMENT_ROOT'] . str_replace('/', DIRECTORY_SEPARATOR, $_SERVER['SCRIPT_NAME']);
        static::$originalScriptFilename = static::$originaDocRoot . str_replace('/', DIRECTORY_SEPARATOR, $_SERVER['SCRIPT_NAME']);
        $_SERVER['SCRIPT_FILENAME'] = static::$scriptFilename;
        $GLOBALS['_SERVER'] = $_SERVER;
    }

    /**
     * @return bool|string
     */
    public static function prepend()
    {
        if (ini_get('auto_prepend_file') && !in_array(realpath(ini_get('auto_prepend_file')), get_included_files(), true)) {
            return ini_get('auto_prepend_file');
        }
        return false;
    }

    /**
     * @return bool|string
     */
    public static function append()
    {
        if (ini_get('auto_append_file') && !in_array(realpath(ini_get('auto_append_file')), get_included_files(), true)) {
            return ini_get('auto_append_file');
        }
        return false;
    }

    /**
     * @return bool
     */
    public static function run()
    {
        static::init();

        header(sprintf('X-Router: %s %s', static::VENDOR_NAME, static::VERSION));

        if (!static::IsAuthorized()) {
            return static::error(403);
        }

        if (php_sapi_name() !== 'cli-server') {
            return static::send();
        }

        header(sprintf('X-Router-file: %s', $_SERVER['SCRIPT_NAME']));

        if (is_file(static::$scriptFilename)) {
            if ('php' === static::$extension) {
                chdir(dirname(static::$scriptFilename));
                return static::$scriptFilename;
            }
            return static::executeKnownFile();
        } else {
            return static::executeUnknownFile();
        }
    }

    /**
     *
     */
    protected static function configureVhosts()
    {
        static::$host = explode(':', $_SERVER['HTTP_HOST'])[0];

        if (!empty(static::$config['vhosts'])) {
            foreach (static::$config['vhosts'] as $vhost) {
                if (in_array(static::$host, $vhost['hosts-name'])) {
                    static::$config = array_merge(static::$config, $vhost);
                    unset(static::$config['vhosts']);
                    break;
                } else {
                    foreach ($vhost['hosts-name'] as $host) {
                        $pattern = "@" . $host . "@";
                        if (preg_match($pattern, static::$host, $matches)) {
                            for ($i = 1; $i < count($matches); $i++) {
                                $vhost['hosts-name'] = [static::$host];
                                if (isset($vhost['docroot']) && !is_null($vhost['docroot'])) {
                                    $vhost['docroot'] = str_replace('$' . $i, $matches[$i], $vhost['docroot']);
                                }
                                if (isset($vhost['log-dir']) & !is_null($vhost['log-dir'])) {
                                    $vhost['log-dir'] = str_replace('$' . $i, $matches[$i], $vhost['log-dir']);
                                }
                            }
                            static::$config = array_merge(static::$config, $vhost);
                            break;
                        }
                    }
                }
            }
        }
    }

    /**
     * @return bool
     */
    protected static function IsAuthorized()
    {
        if (!empty(static::$config['hosts-name'])) {
            if (!in_array(static::$host, static::$config['hosts-name']) && !in_array($_SERVER['REMOTE_ADDR'], static::$config['hosts-name'])) {
                return false;
            }
        }
        return true;
    }


    /**
     * @return bool|string
     */
    protected static function executeKnownFile()
    {
        if (in_array(static::$extension, static::getSupportedMime()) && is_null(static::$config['allow-origin']) && null === static::$config['cache-control'] && file_exists(static::$originalScriptFilename)) {
            return static::send();
        } elseif (array_key_exists(static::$extension, static::getMimeTypes()) && file_exists(static::$scriptFilename)) {
            $types = static::getMimeTypes()[static::$extension];
            if (!is_array($types)) {
                $types = [$types];
            }
            foreach ($types as $type) {
                header(sprintf('Content-type: %s', $type));
            }
            if (!is_null(static::$config['allow-origin'])) {
                foreach (static::$config['allow-origin'] as $origin) {
                    header(sprintf('Access-Control-Allow-Origin: %s', $origin));
                }
            }
            if (!is_null(static::$config['cache-control'])) {
                header(sprintf('Cache-Control: public, max-age=%1$d', static::$config['cache-control']));
            }
            return static::send(static::$scriptFilename, true);
        }
        return static::send();
    }

    /**
     * @return bool
     */
    protected static function executeUnknownFile()
    {
        foreach (static::$config['directory-index'] as $index) {
            // directory-index (index.php or index.html) exists in directory
            if (file_exists($_SERVER['SCRIPT_FILENAME'] . DIRECTORY_SEPARATOR . $index)) {
                $_SERVER['SCRIPT_FILENAME'] = $_SERVER['SCRIPT_FILENAME'] . DIRECTORY_SEPARATOR . $index;
                chdir(dirname($_SERVER['SCRIPT_FILENAME']));
                return static::send($_SERVER['SCRIPT_FILENAME']);
            }

            // directory-index router exists (app.php) exist in docroot
            if (file_exists($_SERVER['DOCUMENT_ROOT'] . DIRECTORY_SEPARATOR . $index)) {
                $_SERVER['SCRIPT_FILENAME'] = $_SERVER['DOCUMENT_ROOT'] . DIRECTORY_SEPARATOR . $index;
                return static::send($_SERVER['SCRIPT_FILENAME']);
            }
        }

        // auto-index-file has been set in router.json. @ see chansig/directory
        if (static::$config['auto-index-file'] && file_exists(static::$config['auto-index-file'])) {
            if (is_dir($_SERVER['SCRIPT_FILENAME'])) {
                chdir($_SERVER['SCRIPT_FILENAME']);
            } else {
                chdir(dirname($_SERVER['SCRIPT_FILENAME']));
            }
            return static::send(static::$config['auto-index-file']);
        } elseif (static::$config['handle-404'] && $_SERVER['SCRIPT_NAME'] !== '/') {
            return static::error(404);
        }
        return static::send();
    }

    /**
     * @param string $file
     * @param bool $read
     * @return bool|string
     */
    protected static function send($file = '', $read = false)
    {
        static::logAccess('200');
        if ('' === $file) {
            return false;
        } elseif ($read) {
            readfile(static::$scriptFilename);
            exit;
        }
        return $file;
    }

    /**
     * @return array
     */
    protected static function getSupportedMime()
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
    protected static function getMimeTypes()
    {
        return [
            'hqx' => 'application/mac-binhex40',
            'cpt' => 'application/mac-compactpro',
            'csv' => array('text/x-comma-separated-values', 'text/comma-separated-values', 'application/octet-stream', 'application/vnd.ms-excel', 'text/csv', 'application/csv', 'application/excel', 'application/vnd.msexcel'),
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
            'pdf' => array('application/pdf', 'application/x-download'),
            'ai' => 'application/postscript',
            'eps' => 'application/postscript',
            'ps' => 'application/postscript',
            'smi' => 'application/smil',
            'smil' => 'application/smil',
            'mif' => 'application/vnd.mif',
            'xls' => array('application/excel', 'application/vnd.ms-excel', 'application/msexcel'),
            'ppt' => array('application/powerpoint', 'application/vnd.ms-powerpoint'),
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
            'zip' => array('application/x-zip', 'application/zip', 'application/x-zip-compressed'),
            'mid' => 'audio/midi',
            'midi' => 'audio/midi',
            'mpga' => 'audio/mpeg',
            'mp2' => 'audio/mpeg',
            'mp3' => array('audio/mpeg', 'audio/mpg'),
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
            'jpeg' => array('image/jpeg', 'image/pjpeg'),
            'jpg' => array('image/jpeg', 'image/pjpeg'),
            'jpe' => array('image/jpeg', 'image/pjpeg'),
            'png' => array('image/png', 'image/x-png'),
            'tiff' => 'image/tiff',
            'tif' => 'image/tiff',
            'css' => 'text/css',
            'html' => 'text/html',
            'htm' => 'text/html',
            'shtml' => 'text/html',
            'txt' => 'text/plain',
            'text' => 'text/plain',
            'log' => array('text/plain', 'text/x-log'),
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
            'word' => array('application/msword', 'application/octet-stream'),
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
     * @return bool
     */
    protected static function error($error = 500, $message = '')
    {
        static::logAccess($error);

        if (403 === $error) {
            $status = 'Forbidden';
            if ('' === $message) {
                $message = sprintf('Access Forbidden for the requested resource <strong>%s</strong> on this server.', $_SERVER['REQUEST_URI']);
            }
        } elseif (404 === $error) {
            $status = 'Not Found';
            if ('' === $message) {
                $message = sprintf('The requested resource <strong>%4$s</strong> was Not Found on this server.', $_SERVER['REQUEST_URI']);
            }
        } else {
            $status = 'Internal server error';
        }

        header(sprintf("HTTP/1.0 %d %s", $error, $status));

        echo sprintf('<!doctype html><html><head><title>%s %s</title><style> * body { background-color: #ffffff; color: #000000; } * h1 { font-family: sans-serif; font-size: 150%%; background-color: #9999cc; font-weight: bold; color: #000000; margin-top: 0;} * </style></head><body><h1>%s</h1><p>%s</p><hr /><p>%s %s</p></body></html>', $error, $status, strtolower($status), $message, static::VENDOR_NAME, static::VERSION);
        exit;
    }

    /**
     * @param int $status
     */
    protected static function logAccess($status = 200)
    {

        if ($status >= 500) {
            $color = 31;// red
        } elseif ($status >= 400) {
            $color = 33;// yellow
        } elseif ($status >= 300) {
            $color = 35;// pink
        } else {
            $color = 32;// green
        }

        if (static::$config['log'] || static::$config['log-dir']) {
            $now = new \DateTime('now', new \DateTimeZone('UTC'));
            $coloredLog = sprintf("[%s] \033[%dm%s:%s [%s]: %s\033[0m\n", $now->format("D M j H:i:s Y"), $color, $_SERVER["REMOTE_ADDR"], $_SERVER["REMOTE_PORT"], $status, $_SERVER["REQUEST_URI"]);
            $log = sprintf("[%s] %s:%s [%s]: %s\n", $now->format("D M j H:i:s Y"), $_SERVER["REMOTE_ADDR"], $_SERVER["REMOTE_PORT"], $status, $_SERVER["REQUEST_URI"]);
            if (static::$config['log']) {
                file_put_contents("php://stdout", static::hasColorSupport() ? $coloredLog : $log);
            }
            if (!is_null(static::$config['log-dir'])) {
                $filename = sprintf('%s%srouter-access-%s.log', static::$config['log-dir'], DIRECTORY_SEPARATOR, static::$host);
                error_log($log, 3, $filename);
            }
        }
    }

    /**
     *
     *
     * @return bool
     */
    protected static function hasColorSupport()
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
