<?php

namespace GeneroWP\WpUploadsProxy;

class RemoteImage
{
    const DOWNLOAD_TIMEOUT = 30;

    protected static $wp_upload_dir;
    protected static $site_url;

    /** @var Relative path from uploads directory */
    protected $relative_path;
    /** @var Request URI */
    protected $request_uri;
    /** @var Absolute path to the local file */
    protected $absolute_local_path;
    /** @var Absolute URL to the remote file */
    protected $absolute_remote_path;

    /**
     * @param string $uri
     */
    public function __construct(string $uri)
    {
        // /app/uploads/2018/01/adobestock-134771208.jpeg
        $this->request_uri = '/' . ltrim($uri, '/');
        // 2018/01/adobestock-134771208.jpeg
        $this->relative_path = str_replace(self::getRelativeUploadPath() . '/', '', $uri);
        // /var/www/wordpress/web/app/uploads/2018/01/adobestock-134771208.jpeg
        $this->absolute_local_path = self::wpUploadDir()['basedir'] . '/' . $this->relative_path;
        // http://example.org/app/uploads/2018/01/adobestock-134771208.jpeg
        $this->absolute_remote_path = rtrim(WPUP_SITEURL, '/') . $this->request_uri;
    }

    /**
     * Initialize using a remote relative path/uri.
     *
     * @param string $relative_remote_path
     * @return GeneroWP\WpUploadsProxy\RemoteImage
     */
    public static function fromRemoteUri(string $relative_remote_path)
    {
        return new self($relative_remote_path);
    }

    /**
     * Initialize using an absolute local path.
     *
     * @param string $absolute_local_path
     * @return GeneroWP\WpUploadsProxy\RemoteImage
     */
    public static function fromLocalPath($absolute_local_path)
    {
        // Remove the entire root path of the uploads dir.
        $request_uri = str_replace(self::wpUploadDir()['basedir'], '', $absolute_local_path);
        // Prefix with the relative upload dir path.
        $request_uri = self::getRelativeUploadPath() . $request_uri;
        // Init
        return new self($request_uri);
    }

    /**
     * Download the remote file.
     *
     * @return mixed
     */
    public function download()
    {
        require_once ABSPATH . 'wp-admin/includes/file.php';
        $cid = 'wpup_' . md5($this->absolute_local_path);
        $tries = 0;

        if (!$this->isUploadFile()) {
            return false;
        }
        if ($cache = get_transient($cid)) {
            $tries = $cache;
            if ($tries > 1) {
                return false;
            }
        }

        $wp_filetype = wp_check_filetype($this->absolute_local_path);
        if (!$wp_filetype['ext']) {
            // Unpermitted file type
            return false;
        }

        $tmp = download_url($this->absolute_remote_path, self::DOWNLOAD_TIMEOUT);
        if (!is_wp_error($tmp)) {
            wp_mkdir_p(dirname($this->absolute_local_path));
            if (rename($tmp, $this->absolute_local_path)) {
                set_transient($cid, ++$tries, HOUR_IN_SECONDS);
                return $this->absolute_local_path;
            }
        }
        return false;
    }

    /**
     * Passthru the contents of the file directly to the browser.
     */
    public function passthru()
    {
        $size = getimagesize($this->absolute_local_path);
        $fp = fopen($file, 'rb');
        header('Content-Type: '. $size['mime']);
        header('Content-Length: '. filesize($file));
        fpassthru($fp);
    }

    /**
     * Check if file is within the upload directory's path.
     *
     * @return bool
     */
    public function isUploadFile()
    {
        return strpos($this->request_uri, self::getRelativeUploadPath()) === 0;
    }

    /**
     * Return the WP Upload directory information
     *
     * @param array
     */
    public static function wpUploadDir()
    {
        if (!self::$wp_upload_dir) {
            self::$wp_upload_dir = wp_upload_dir();
        }
        return self::$wp_upload_dir;
    }

    public static function getRelativeUploadPath()
    {
        if (!self::$site_url) {
            self::$site_url = get_bloginfo('url');
        }
        return str_replace(self::$site_url, '', self::wpUploadDir()['baseurl']);
    }
}
