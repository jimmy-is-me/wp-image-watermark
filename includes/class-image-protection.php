<?php
defined( 'ABSPATH' ) || exit;

class WPIWM_Image_Protection {

    private static $instance = null;

    public static function get_instance() {
        if ( null === self::$instance ) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    private function __construct() {
        add_action( 'wp_enqueue_scripts', array( $this, 'enqueue' ) );
        add_action( 'wp_head', array( $this, 'inline_css' ) );
    }

    public function enqueue() {
        $opts = WPIWM_Settings::get();
        if ( $opts['protect_right_click'] || $opts['protect_drag_drop'] || $opts['protect_devtools'] ) {
            wp_enqueue_script(
                'wpiwm-protection',
                WPIWM_PLUGIN_URL . 'assets/js/protection.js',
                array(),
                WPIWM_VERSION,
                true
            );
            wp_localize_script( 'wpiwm-protection', 'WPIWM_Protection', array(
                'rightClick' => (bool) $opts['protect_right_click'],
                'dragDrop'   => (bool) $opts['protect_drag_drop'],
                'devTools'   => (bool) $opts['protect_devtools'],
            ) );
        }
    }

    public function inline_css() {
        if ( WPIWM_Settings::get( 'protect_drag_drop' ) ) {
            echo '<style>img{-webkit-user-drag:none;-khtml-user-drag:none;-moz-user-drag:none;-o-user-drag:none;user-drag:none;-webkit-user-select:none;user-select:none;}</style>' . "\n";
        }
    }
}
