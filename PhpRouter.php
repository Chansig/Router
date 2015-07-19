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
    const VERSION = '0.1';
    /**
     * @var array
     */
    protected $config = [
        "directory-index" => "index.php",
        "allowed-hosts" => [],
        "allow-origin" => false,
        "handle-404" => false,
        "cache" => 0,
        "log" => true
    ];
    /**
     * @var array
     */
    protected $supportedMime = [];
    /**
     * @var string
     */
    protected $extension = '';
    /**
     * @var string
     */
    protected $filename = '';

    /**
     * @param $config
     */
    public function __construct($config = [])
    {

        if (ini_get('auto_prepend_file') && !in_array(realpath(ini_get('auto_prepend_file')), get_included_files(), true)) {
            include ini_get('auto_prepend_file');
        }
        $this->config = array_merge($this->config, $config);
        $path = parse_url($_SERVER["REQUEST_URI"], PHP_URL_PATH);
        $this->filename = substr($_SERVER['SCRIPT_FILENAME'], strlen($_SERVER['DOCUMENT_ROOT']) + 1);
        $this->extension = pathinfo($path, PATHINFO_EXTENSION);
        $this->supportedMime = $this->getSupportedMime();
    }

    /**
     * 1/ No extension  => SCRIPT_FILENAME = index.php
     * 2/ exist.php =>  SCRIPT_FILENAME = exist.php
     * 3/ notexist.php =>  SCRIPT_FILENAME = router.php
     * 4/ asset.ext =>  SCRIPT_FILENAME = router.php
     *
     * @return bool
     */
    public function run()
    {
        header(sprintf('X-Router: %s %s', static::VENDOR_NAME, static::VERSION));
        if (!empty($this->config['allowed-hosts'])) {
            if (!in_array($_SERVER['REMOTE_ADDR'], $this->config['allowed-hosts'])) {
                return $this->error(403);
            }
        }

        if (php_sapi_name() !== 'cli-server') {
            return false;
        }

        if (is_file($_SERVER['DOCUMENT_ROOT'] . DIRECTORY_SEPARATOR . $_SERVER['SCRIPT_NAME'])) {
            header(sprintf('X-Router-file: %s', $_SERVER['SCRIPT_NAME']));
            // execute php files
            if ('php' === $this->extension) {
                return false;
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
        if ($this->config['handle-404'] && $_SERVER['SCRIPT_NAME'] !== '/') {
            return $this->error(404);
        } else {
            $this->logAccess('200');
            $_SERVER['SCRIPT_FILENAME'] = $_SERVER['DOCUMENT_ROOT'] . DIRECTORY_SEPARATOR . $this->config['directory-index'];
            include $_SERVER['SCRIPT_FILENAME'];
            exit;
        }
    }

    /**
     * @return bool
     */
    protected function executeKnownFile()
    {
        $this->logAccess('200');
        if (in_array($this->extension, $this->supportedMime) && !$this->config['allow-origin'] && 0 === $this->config['cache']) {
            return false;
        } elseif (array_key_exists($this->extension, $this->getMimeTypes())) {
            $types = $this->getMimeTypes()[$this->extension];
            if (!is_array($types)) {
                $types = [$types];
            }
            foreach ($types as $type) {
                header(sprintf('Content-type: %s', $type));
            }
            if ($this->config['allow-origin']) {
                header('Access-Control-Allow-Origin: *');
            }
            if (!is_null($this->config['cache'])) {
                header(sprintf('Cache-Control: public, max-age=%1$d', $this->config['cache']));
            }
            readfile($_SERVER['SCRIPT_FILENAME']);
            exit;
        }
        return false;
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
    protected function error($error = 404)
    {
        $this->logAccess($error);
        if (403 === $error) {
            header("HTTP/1.0 403 Forbidden");
            $status = 'Forbidden';
        } else {
            header("HTTP/1.0 404 Not Found");
            $status = 'Not Found';
        }
        echo sprintf('<!doctype html><html><head><title>%1$s %2$s</title><style> * body { background-color: #ffffff; color: #000000; } * h1 { font-family: sans-serif; font-size: 150%%; background-color: #9999cc; font-weight: bold; color: #000000; margin-top: 0;} * </style></head><body><h1>%2$s</h1><p>The requested resource <strong>%4$s</strong> was %3$s on this server.</p><hr /><p>%5$s %6$s</p></body></html>', $error, $status, strtolower($status), $_SERVER['REQUEST_URI'], static::VENDOR_NAME, static::VERSION);
        return true;
    }

    /**
     * @param int $status
     */
    protected function logAccess($status = 200)
    {
        if ($this->config['log']) {
            $now = new \DateTime('now', new \DateTimeZone('UTC'));
            file_put_contents("php://stdout", sprintf("[%s] %s:%s [%s]: %s\n", $now->format("D M j H:i:s Y"), $_SERVER["REMOTE_ADDR"], $_SERVER["REMOTE_PORT"], $status, $_SERVER["REQUEST_URI"]));
        }
    }
}
