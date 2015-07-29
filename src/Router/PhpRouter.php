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
    const VERSION = '0.3.1';
    /**
     * @var array
     */
    protected $config = [
        "hosts-name" => [],
        "docroot" => null,
        "directory-index" => ["index.php", "index.html"],
        "allow-origin" => false,
        "handle-404" => false,
        "cache" => 0,
        "log" => true,
        "log-dir" => null,
        "vhosts" => [],
        "auto-index-file" => null,
    ];

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
    protected $host = '';

    /**
     * @var string
     */
    protected $originaDocRoot;

    /**
     * @param $config
     */
    public function __construct($config = [])
    {
        if (!is_array($config)) {
            throw new \Exception('Invalid configuration. Check your router.json syntax.');
        }
        $this->config = array_merge($this->config, $config);
        $this->host = explode(':', $_SERVER['HTTP_HOST'])[0];
        $path = parse_url($_SERVER["REQUEST_URI"], PHP_URL_PATH);
        $this->extension = pathinfo($path, PATHINFO_EXTENSION);
        $this->originaDocRoot = $_SERVER['DOCUMENT_ROOT'];

        if (!empty($this->config['vhosts'])) {
            foreach ($this->config['vhosts'] as $vhost) {
                if (in_array($this->host, $vhost['hosts-name'])) {
                    $this->config = array_merge($this->config, $vhost);
                    break;
                } else {
                    foreach ($vhost['hosts-name'] as $host) {
                        $pattern = "@" . $host . "@";
                        if (preg_match($pattern, $this->host, $matches)) {
                            for ($i = 1; $i < count($matches); $i++) {
                                $vhost['hosts-name'] = [$this->host];
                                $vhost['docroot'] = str_replace('$' . $i, $matches[$i], $vhost['docroot']);
                                $vhost['log-dir'] = str_replace('$' . $i, $matches[$i], $vhost['log-dir']);
                            }
                            $this->config = array_merge($this->config, $vhost);
                            break;
                        }
                    }
                }
            }
        }

        if (!is_null($this->config['docroot']) && !is_dir($this->config['docroot'])) {
            $this->error(500, 'Invalid docroot. Check your router.json syntax.');
        }
        if (!is_null($this->config['log-dir']) && !is_dir($this->config['log-dir'])) {
            $this->error(500, sprintf('%s is not a valid log-dir. Check your router.json syntax.', $this->config['log-dir']));
        }
        if (!is_null($this->config['docroot'])) {
            $_SERVER['DOCUMENT_ROOT'] = $this->config['docroot'];
        }
        $this->scriptFilename = $_SERVER['DOCUMENT_ROOT'] . str_replace('/', DIRECTORY_SEPARATOR, $_SERVER['SCRIPT_NAME']);
        $this->originalScriptFilename = $this->originaDocRoot . str_replace('/', DIRECTORY_SEPARATOR, $_SERVER['SCRIPT_NAME']);
        $_SERVER['SCRIPT_FILENAME'] = $this->scriptFilename;
        $GLOBALS['_SERVER'] = $_SERVER;
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
     * @return bool
     */
    public function run()
    {
        header(sprintf('X-Router: %s %s', static::VENDOR_NAME, static::VERSION));
        if (!empty($this->config['hosts-name'])) {
            if (!in_array($this->host, $this->config['hosts-name']) && !in_array($_SERVER['REMOTE_ADDR'], $this->config['hosts-name'])) {
                return $this->error(403);
            }
        }

        if (php_sapi_name() !== 'cli-server') {
            return false;
        }

        if (is_file($this->scriptFilename)) {
            header(sprintf('X-Router-file: %s', $_SERVER['SCRIPT_NAME']));
            if ('php' === $this->extension) {
                chdir(dirname($this->scriptFilename));
                return $this->scriptFilename;
            }
            return $this->executeKnownFile();
        } else {
            header(sprintf('X-Router-file: %s', $_SERVER['SCRIPT_NAME']));
            return $this->executeUnknownFile();
        }
    }


    /**
     * @return bool
     */
    protected function executeIndex()
    {
        return false;
    }

    /**
     * @return bool
     */
    protected function executeUnknownFile()
    {
        foreach ($this->config['directory-index'] as $index) {
            // directory-index (index.php or index.html) exists in directory
            if (file_exists($_SERVER['SCRIPT_FILENAME'] . DIRECTORY_SEPARATOR . $index)) {
                $_SERVER['SCRIPT_FILENAME'] = $_SERVER['SCRIPT_FILENAME'] . DIRECTORY_SEPARATOR . $index;
            } // directory-index router exists (app.php) exist in directory
            elseif (file_exists($_SERVER['DOCUMENT_ROOT'] . DIRECTORY_SEPARATOR . $index)) {
                $_SERVER['SCRIPT_FILENAME'] = $_SERVER['DOCUMENT_ROOT'] . DIRECTORY_SEPARATOR . $index;
            } else {
                continue;
            }

            chdir(dirname($_SERVER['SCRIPT_FILENAME']));
            $this->logAccess('200');
            $this->sendHeaders();
            return $_SERVER['SCRIPT_FILENAME'];
        }
        // auto-index-file has been set in router.json. @ see chansig/directory
        if ($this->config['auto-index-file'] && file_exists($this->config['auto-index-file'])) {
            if (is_dir($_SERVER['SCRIPT_FILENAME'])) {
                chdir($_SERVER['SCRIPT_FILENAME']);
            } else {
                chdir(dirname($_SERVER['SCRIPT_FILENAME']));
            }
            $this->logAccess('200');
            $this->sendHeaders();
            return $this->config['auto-index-file'];
        } elseif ($this->config['handle-404'] && '/' !== $_SERVER['SCRIPT_NAME']) {
            return $this->error(404);
        } else {
            return $this->error(500, 'Invalid directory-index');
        }
    }


    /**
     * @return bool
     */
    protected function executeKnownFile()
    {
        $this->logAccess('200');

        if (in_array($this->extension, $this->getSupportedMime()) && !$this->config['allow-origin'] && 0 === $this->config['cache'] && file_exists($this->originalScriptFilename)) {
            return false;
        } elseif (array_key_exists($this->extension, $this->getMimeTypes()) && file_exists($this->scriptFilename)) {
            $this->sendHeaders();
            readfile($this->scriptFilename);
            exit;
        }
        return false;
    }

    /**
     *
     */
    protected function sendHeaders()
    {
        if ('' !== $this->extension) {
            $types = $this->getMimeTypes();
            if (isset($types[$this->extension])) {
                $types = $types [$this->extension];
                if (!is_array($types)) {
                    $types = [$types];
                }
                foreach ($types as $type) {
                    header(sprintf('Content-type: %s', $type));
                }
            }
        }
        if ($this->config['allow-origin']) {
            header('Access-Control-Allow-Origin: *');
        }
        if (!is_null($this->config['cache'])) {
            header(sprintf('Cache-Control: public, max-age=%1$d', $this->config['cache']));
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
     * @return bool
     */
    protected function error($error = 500, $message = '')
    {
        $this->logAccess($error);
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
        return true;
    }

    /**
     * @param int $status
     */
    protected function logAccess($status = 200)
    {
        if ($this->config['log'] || $this->config['log-dir']) {
            $now = new \DateTime('now', new \DateTimeZone('UTC'));
            $log = sprintf("[%s] %s:%s [%s]: %s\n", $now->format("D M j H:i:s Y"), $_SERVER["REMOTE_ADDR"], $_SERVER["REMOTE_PORT"], $status, $_SERVER["REQUEST_URI"]);
            if ($this->config['log']) {
                file_put_contents("php://stdout", $log);
            }
            if (!is_null($this->config['log-dir'])) {
                $filename = sprintf('%s%srouter-access-%s.log', $this->config['log-dir'], DIRECTORY_SEPARATOR, $this->host);
                error_log($log, 3, $filename);
            }
        }
    }
}
