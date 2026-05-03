<?php
defined( 'ABSPATH' ) || exit;

class WPIWM_Settings {

    private static $instance = null;
    const OPTION_KEY = 'wpiwm_settings';

    public static function get_instance() {
        if ( null === self::$instance ) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    private function __construct() {}

    public static function activate() {
        if ( ! get_option( self::OPTION_KEY ) ) {
            update_option( self::OPTION_KEY, self::defaults() );
        }
    }

    public static function defaults() {
        return array(
            // Global switch
            'auto_watermark'        => false,   // 自動浮水印開關（預設關閉）
            // Watermark type: 'image' | 'text'
            'watermark_type'        => 'text',
            // Image watermark
            'watermark_image_id'    => 0,
            'watermark_image_opacity' => 80,
            // Text watermark
            'watermark_text'        => get_bloginfo( 'name' ),
            'watermark_font_size'   => 36,
            'watermark_font_color'  => '#ffffff',
            'watermark_text_opacity'=> 70,
            'watermark_font_family' => 'arial',
            // Position
            'watermark_position'    => 'bottom-right',
            'watermark_offset_x'    => 10,
            'watermark_offset_y'    => 10,
            'watermark_scale'       => 20,  // % of image width (for image wm)
            // Image protection
            'protect_right_click'   => false,
            'protect_drag_drop'     => false,
            'protect_devtools'      => false,
            // Apply to sizes
            'apply_to_sizes'        => array( 'full' ),
        );
    }

    public static function get( $key = null ) {
        $opts = get_option( self::OPTION_KEY, self::defaults() );
        $opts = wp_parse_args( $opts, self::defaults() );
        if ( $key !== null ) {
            return isset( $opts[ $key ] ) ? $opts[ $key ] : null;
        }
        return $opts;
    }

    public static function update( $data ) {
        $current = self::get();
        $merged  = array_merge( $current, $data );
        update_option( self::OPTION_KEY, $merged );
    }
}
