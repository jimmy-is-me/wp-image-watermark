<?php
defined( 'ABSPATH' ) || exit;

/**
 * WPIWM_Image_Protection
 *
 * Outputs an inline <script> in wp_footer to disable right-click on images.
 * Using wp_footer inline avoids the enqueue dependency / conditional-load bugs
 * that caused the external protection.js to silently not fire on the front end.
 */
class WPIWM_Image_Protection {

    private static $instance = null;

    public static function get_instance() {
        if ( null === self::$instance ) self::$instance = new self();
        return self::$instance;
    }

    private function __construct() {
        add_action( 'wp_footer', array( $this, 'maybe_output_protection' ), 99 );
    }

    public function maybe_output_protection() {
        if ( empty( WPIWM_Settings::get( 'protect_right_click' ) ) ) return;
        ?>
<script id="wpiwm-protection">
/* WP Image Watermark – right-click protection */
(function(){
    document.addEventListener('contextmenu', function(e){
        var el = e.target;
        if ( ! el ) return;
        // Block on <img>, <picture>, or any ancestor containing an img
        if (
            el.tagName === 'IMG' ||
            el.tagName === 'PICTURE' ||
            ( el.closest && el.closest('figure,a,div[style]') &&
              el.closest('figure,a,div[style]').querySelector('img') )
        ) {
            e.preventDefault();
            return false;
        }
    }, true); // capture phase so it fires before any other handler
})();
</script>
        <?php
    }
}
