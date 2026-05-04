<?php
/**
 * Plugin Name: WP Image Watermark
 * Plugin URI:  https://github.com/jimmy-is-me/wp-image-watermark
 * Description: 為圖片新增圖像或文字浮水印，支援自動/手動/批次處理，可在媒體庫或附件詳細資料頁面手動套用。
 * Version:     1.1.0
 * Author:      jimmy-is-me
 * License:     GPL-2.0+
 * Text Domain: wp-image-watermark
 * Domain Path: /languages
 */

defined( 'ABSPATH' ) || exit;

define( 'WPIWM_VERSION', '1.1.0' );
define( 'WPIWM_PLUGIN_DIR', plugin_dir_path( __FILE__ ) );
define( 'WPIWM_PLUGIN_URL', plugin_dir_url( __FILE__ ) );
define( 'WPIWM_PLUGIN_FILE', __FILE__ );

require_once WPIWM_PLUGIN_DIR . 'includes/class-settings.php';
require_once WPIWM_PLUGIN_DIR . 'includes/class-watermark-engine.php';
require_once WPIWM_PLUGIN_DIR . 'includes/class-media-handler.php';
require_once WPIWM_PLUGIN_DIR . 'includes/class-image-protection.php';
require_once WPIWM_PLUGIN_DIR . 'includes/class-admin.php';
require_once WPIWM_PLUGIN_DIR . 'includes/class-ajax-helpers.php';

register_activation_hook( __FILE__, array( 'WPIWM_Settings', 'activate' ) );

add_action( 'plugins_loaded', function () {
    WPIWM_Settings::get_instance();
    WPIWM_Media_Handler::get_instance();
    WPIWM_Image_Protection::get_instance();
    if ( is_admin() ) {
        WPIWM_Admin::get_instance();
    }
} );
