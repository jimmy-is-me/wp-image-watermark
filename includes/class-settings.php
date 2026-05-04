<?php
defined( 'ABSPATH' ) || exit;

class WPIWM_Settings {

    private static $instance = null;
    const OPTION_KEY = 'wpiwm_settings';

    public static function get_instance() {
        if ( null === self::$instance ) self::$instance = new self();
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
            'auto_watermark'          => 0,
            'watermark_type'          => 'text',
            'watermark_image_id'      => 0,
            'watermark_image_opacity' => 80,
            'watermark_text'          => '',
            'watermark_font_size'     => 36,
            'watermark_font_color'    => '#ffffff',
            'watermark_text_opacity'  => 70,
            'watermark_font_family'   => 'arial',
            'watermark_position'      => 'bottom-right',
            'watermark_offset_x'      => 10,
            'watermark_offset_y'      => 10,
            'watermark_scale'         => 20,
            'protect_right_click'     => 0,
        );
    }

    public static function get( $key = null ) {
        $opts = get_option( self::OPTION_KEY, array() );
        $opts = array_merge( self::defaults(), (array) $opts );
        if ( null !== $key ) {
            return isset( $opts[ $key ] ) ? $opts[ $key ] : null;
        }
        return $opts;
    }

    public static function update( $data ) {
        $current = self::get();
        update_option( self::OPTION_KEY, array_merge( $current, $data ) );
    }
}
