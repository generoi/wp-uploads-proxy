<?php
/*
Plugin Name:        Uploads Proxy
Plugin URI:         http://genero.fi
Description:        Download uploaded files from produciton when unavailable in development
Version:            0.1.0
Author:             Genero
Author URI:         http://genero.fi/
License:            MIT License
License URI:        http://opensource.org/licenses/MIT
*/
namespace GeneroWP\WpUploadsProxy;

use Puc_v4_Factory;

if (!defined('ABSPATH')) {
    exit;
}

class Plugin
{

    private static $instance = null;
    public $plugin_name = 'wp-uploads-proxy';
    public $plugin_path;
    public $plugin_url;
    public $github_url = 'https://github.com/generoi/wp-uploads-proxy';

    /** @var Local timber image success cache */
    protected $_cache = [];

    public static function get_instance()
    {
        if (null === self::$instance) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    public function __construct()
    {
        $this->plugin_path = plugin_dir_path(__FILE__);
        $this->plugin_url = plugin_dir_url(__FILE__);

        Puc_v4_Factory::buildUpdateChecker($this->github_url, __FILE__, $this->plugin_name);

        add_action('plugins_loaded', [$this, 'init']);
    }

    public function init()
    {
        add_filter('404_template', [$this, 'passthru_404']);
        add_filter('timber_extended/image/init', [$this, 'download_timber_image'], 100);
    }

    /**
     * Filter callback; On page 404, download the request uri from the
     * production site and output its data.
     */
    public function passthru_404()
    {
        $image = RemoteImage::fromRemoteUri($_SERVER['REQUEST_URI']);
        if ($image->isUploadFile() && $image->download()) {
            $image->passthru();
            die();
        }
    }

    /**
     * Filter callback; On timber image initialization, try and download
     * unexisting files from the production site.
     */
    public function download_timber_image($image)
    {
        $image_path = $image->file_loc;
        if (isset($this->_cache[$image_path]) || file_exists($image_path)) {
            return;
        }
        $image = RemoteImage::fromLocalPath($image_path);
        $this->_cache[$image_path] = $image->download();
    }
}

if (file_exists($composer = __DIR__ . '/vendor/autoload.php')) {
    require_once $composer;
}

if (defined('WPUP_IS_LOCAL') && defined('WPUP_SITEURL')) {
    Plugin::get_instance();
}
