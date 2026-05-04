<?php
defined( 'ABSPATH' ) || exit;

class WPIWM_Watermark_Engine {

    /**
     * Apply watermark to a file path.
     *
     * @param string $file_path  Absolute path to source image.
     * @param array  $opts       Override settings (optional).
     * @return bool
     */
    public static function apply( $file_path, $opts = array() ) {
        if ( ! file_exists( $file_path ) ) {
            return false;
        }

        $settings = wp_parse_args( $opts, WPIWM_Settings::get() );
        $ext      = strtolower( pathinfo( $file_path, PATHINFO_EXTENSION ) );
        $allowed  = array( 'jpg', 'jpeg', 'png', 'webp' );
        if ( ! in_array( $ext, $allowed, true ) ) {
            return false;
        }

        // Prefer GD (more compatible on shared hosting), fallback to ImageMagick
        if ( extension_loaded( 'gd' ) && function_exists( 'imagecreatetruecolor' ) ) {
            return self::apply_gd( $file_path, $ext, $settings );
        } elseif ( extension_loaded( 'imagick' ) ) {
            return self::apply_imagick( $file_path, $ext, $settings );
        }
        return false;
    }

    /* ------------------------------------------------------------------ */
    /*  GD Implementation                                                  */
    /* ------------------------------------------------------------------ */

    private static function apply_gd( $file_path, $ext, $settings ) {
        $src = self::gd_load( $file_path, $ext );
        if ( ! $src ) {
            return false;
        }

        $src_w = imagesx( $src );
        $src_h = imagesy( $src );

        if ( $settings['watermark_type'] === 'image' ) {
            $result = self::gd_apply_image( $src, $src_w, $src_h, $settings );
        } else {
            $result = self::gd_apply_text( $src, $src_w, $src_h, $settings );
        }

        if ( $result ) {
            self::gd_save( $src, $file_path, $ext );
        }

        imagedestroy( $src );
        return $result;
    }

    private static function gd_load( $path, $ext ) {
        switch ( $ext ) {
            case 'jpg':
            case 'jpeg':
                return function_exists( 'imagecreatefromjpeg' ) ? @imagecreatefromjpeg( $path ) : false;
            case 'png':
                return function_exists( 'imagecreatefrompng' ) ? @imagecreatefrompng( $path ) : false;
            case 'webp':
                return function_exists( 'imagecreatefromwebp' ) ? @imagecreatefromwebp( $path ) : false;
        }
        return false;
    }

    private static function gd_save( $img, $path, $ext ) {
        switch ( $ext ) {
            case 'jpg':
            case 'jpeg':
                imagejpeg( $img, $path, 90 );
                break;
            case 'png':
                imagepng( $img, $path, 6 );
                break;
            case 'webp':
                if ( function_exists( 'imagewebp' ) ) {
                    imagewebp( $img, $path, 85 );
                }
                break;
        }
    }

    private static function gd_apply_image( $src, $src_w, $src_h, $settings ) {
        $wm_id = (int) $settings['watermark_image_id'];
        if ( ! $wm_id ) {
            return false;
        }

        // get_attached_file requires WordPress to be loaded
        $wm_path = get_attached_file( $wm_id );
        if ( ! $wm_path || ! file_exists( $wm_path ) ) {
            return false;
        }

        $wm_ext = strtolower( pathinfo( $wm_path, PATHINFO_EXTENSION ) );
        $wm     = self::gd_load( $wm_path, $wm_ext );
        if ( ! $wm ) {
            return false;
        }

        // Enable alpha for PNG watermarks
        imagealphablending( $wm, true );

        $scale     = max( 1, min( 100, (int) $settings['watermark_scale'] ) );
        $wm_orig_w = imagesx( $wm );
        $wm_orig_h = imagesy( $wm );
        $wm_w      = (int) round( $src_w * $scale / 100 );
        $wm_h      = (int) round( $wm_orig_h * ( $wm_w / $wm_orig_w ) );

        if ( $wm_w < 1 || $wm_h < 1 ) {
            imagedestroy( $wm );
            return false;
        }

        // Resize watermark
        $wm_resized = imagecreatetruecolor( $wm_w, $wm_h );
        imagealphablending( $wm_resized, false );
        imagesavealpha( $wm_resized, true );
        $transparent = imagecolorallocatealpha( $wm_resized, 0, 0, 0, 127 );
        imagefilledrectangle( $wm_resized, 0, 0, $wm_w, $wm_h, $transparent );
        imagecopyresampled( $wm_resized, $wm, 0, 0, 0, 0, $wm_w, $wm_h, $wm_orig_w, $wm_orig_h );
        imagedestroy( $wm );

        $opacity = max( 0, min( 100, (int) $settings['watermark_image_opacity'] ) );
        list( $x, $y ) = self::calc_position( $src_w, $src_h, $wm_w, $wm_h, $settings );

        // Ensure coordinates are within image bounds
        $x = max( 0, min( $src_w - $wm_w, $x ) );
        $y = max( 0, min( $src_h - $wm_h, $y ) );

        self::gd_imagecopymerge_alpha( $src, $wm_resized, $x, $y, 0, 0, $wm_w, $wm_h, $opacity );
        imagedestroy( $wm_resized );
        return true;
    }

    private static function gd_apply_text( $src, $src_w, $src_h, $settings ) {
        $text = trim( (string) $settings['watermark_text'] );
        if ( '' === $text ) {
            return false;
        }

        $font_size = max( 8, (int) $settings['watermark_font_size'] );
        $color_hex = ltrim( (string) $settings['watermark_font_color'], '#' );
        if ( strlen( $color_hex ) !== 6 ) {
            $color_hex = 'ffffff';
        }
        $opacity  = max( 0, min( 100, (int) $settings['watermark_text_opacity'] ) );
        $r        = hexdec( substr( $color_hex, 0, 2 ) );
        $g        = hexdec( substr( $color_hex, 2, 2 ) );
        $b        = hexdec( substr( $color_hex, 4, 2 ) );
        // GD alpha: 0=opaque, 127=transparent
        $alpha_gd = (int) round( 127 - ( $opacity / 100 * 127 ) );

        $font_file = WPIWM_PLUGIN_DIR . 'assets/fonts/OpenSans-Regular.ttf';
        $has_ttf   = file_exists( $font_file ) && function_exists( 'imagettftext' );

        if ( $has_ttf ) {
            // TTF rendering
            $color = imagecolorallocatealpha( $src, $r, $g, $b, $alpha_gd );
            $bbox  = imagettfbbox( $font_size, 0, $font_file, $text );
            // bbox: [BL-x, BL-y, BR-x, BR-y, TR-x, TR-y, TL-x, TL-y]
            $text_w = abs( $bbox[4] - $bbox[6] );
            $text_h = abs( $bbox[7] - $bbox[1] );
            list( $x, $y ) = self::calc_position( $src_w, $src_h, $text_w, $text_h, $settings );
            $x = max( 0, $x );
            $y = max( 0, $y );
            // imagettftext baseline is at $y + $text_h (bottom of text)
            imagettftext( $src, $font_size, 0, $x, $y + $text_h, $color, $font_file, $text );
        } else {
            // Fallback: GD built-in font
            $font_gd = 5; // largest built-in GD font
            $char_w  = imagefontwidth( $font_gd );
            $char_h  = imagefontheight( $font_gd );
            // Scale repetitions to approximate font_size
            $scale_factor = max( 1, (int) round( $font_size / $char_h ) );

            // Draw text multiple times with a simple repeat trick, or just draw once
            $text_w = $char_w * mb_strlen( $text );
            $text_h = $char_h;

            list( $x, $y ) = self::calc_position( $src_w, $src_h, $text_w, $text_h, $settings );
            $x = max( 0, $x );
            $y = max( 0, $y );

            $color = imagecolorallocatealpha( $src, $r, $g, $b, $alpha_gd );
            imagestring( $src, $font_gd, $x, $y, $text, $color );
        }

        return true;
    }

    /**
     * Alpha-correct imagecopymerge replacement.
     * Handles PNG watermarks with transparency.
     */
    private static function gd_imagecopymerge_alpha( $dst, $src, $dst_x, $dst_y, $src_x, $src_y, $src_w, $src_h, $pct ) {
        if ( $pct >= 100 ) {
            // Full opacity: direct copy preserving src alpha
            imagecopy( $dst, $src, $dst_x, $dst_y, $src_x, $src_y, $src_w, $src_h );
            return;
        }
        if ( $pct <= 0 ) {
            return;
        }

        // Create a temp canvas with dst content
        $cut = imagecreatetruecolor( $src_w, $src_h );
        imagealphablending( $cut, false );
        imagesavealpha( $cut, true );
        imagecopy( $cut, $dst, 0, 0, $dst_x, $dst_y, $src_w, $src_h );

        // Blend src over cut with $pct opacity
        imagecopymerge( $cut, $src, 0, 0, $src_x, $src_y, $src_w, $src_h, $pct );

        // Stamp result onto dst
        imagealphablending( $dst, true );
        imagecopy( $dst, $cut, $dst_x, $dst_y, 0, 0, $src_w, $src_h );
        imagedestroy( $cut );
    }

    /* ------------------------------------------------------------------ */
    /*  ImageMagick Implementation                                         */
    /* ------------------------------------------------------------------ */

    private static function apply_imagick( $file_path, $ext, $settings ) {
        try {
            $image = new Imagick( $file_path );
            $image->setImageCompressionQuality( 90 );

            $src_w = $image->getImageWidth();
            $src_h = $image->getImageHeight();

            if ( $settings['watermark_type'] === 'image' ) {
                $result = self::imagick_apply_image( $image, $src_w, $src_h, $settings );
            } else {
                $result = self::imagick_apply_text( $image, $src_w, $src_h, $settings );
            }

            if ( $result ) {
                $image->writeImage( $file_path );
            }
            $image->destroy();
            return $result;
        } catch ( Exception $e ) {
            error_log( 'WPIWM Imagick error: ' . $e->getMessage() );
            return false;
        }
    }

    private static function imagick_apply_image( $image, $src_w, $src_h, $settings ) {
        $wm_id = (int) $settings['watermark_image_id'];
        if ( ! $wm_id ) return false;
        $wm_path = get_attached_file( $wm_id );
        if ( ! $wm_path || ! file_exists( $wm_path ) ) return false;

        try {
            $wm    = new Imagick( $wm_path );
            $scale = max( 1, min( 100, (int) $settings['watermark_scale'] ) );
            $wm_w  = (int) round( $src_w * $scale / 100 );
            $wm->resizeImage( $wm_w, 0, Imagick::FILTER_LANCZOS, 1 );
            $wm_h = $wm->getImageHeight();

            $opacity = max( 0, min( 100, (int) $settings['watermark_image_opacity'] ) );
            $wm->evaluateImage( Imagick::EVALUATE_MULTIPLY, $opacity / 100, Imagick::CHANNEL_ALPHA );

            list( $x, $y ) = self::calc_position( $src_w, $src_h, $wm_w, $wm_h, $settings );
            $image->compositeImage( $wm, Imagick::COMPOSITE_OVER, $x, $y );
            $wm->destroy();
            return true;
        } catch ( Exception $e ) {
            error_log( 'WPIWM Imagick image watermark error: ' . $e->getMessage() );
            return false;
        }
    }

    private static function imagick_apply_text( $image, $src_w, $src_h, $settings ) {
        $text = trim( (string) $settings['watermark_text'] );
        if ( '' === $text ) return false;

        $font_size = max( 8, (int) $settings['watermark_font_size'] );
        $color_hex = (string) $settings['watermark_font_color'];
        $opacity   = max( 0, min( 100, (int) $settings['watermark_text_opacity'] ) );
        $alpha     = round( $opacity / 100, 2 );

        try {
            $draw = new ImagickDraw();
            $draw->setFontSize( $font_size );
            $draw->setFillColor( new ImagickPixel( $color_hex ) );
            $draw->setFillOpacity( $alpha );

            $font_file = WPIWM_PLUGIN_DIR . 'assets/fonts/OpenSans-Regular.ttf';
            if ( file_exists( $font_file ) ) {
                $draw->setFont( $font_file );
            }

            $metrics = $image->queryFontMetrics( $draw, $text );
            $text_w  = (int) $metrics['textWidth'];
            $text_h  = (int) $metrics['textHeight'];

            list( $x, $y ) = self::calc_position( $src_w, $src_h, $text_w, $text_h, $settings );
            $image->annotateImage( $draw, $x, $y + $text_h, 0, $text );
            return true;
        } catch ( Exception $e ) {
            error_log( 'WPIWM Imagick text watermark error: ' . $e->getMessage() );
            return false;
        }
    }

    /* ------------------------------------------------------------------ */
    /*  Position helper                                                    */
    /* ------------------------------------------------------------------ */

    private static function calc_position( $src_w, $src_h, $el_w, $el_h, $settings ) {
        $pos      = (string) $settings['watermark_position'];
        $offset_x = max( 0, (int) $settings['watermark_offset_x'] );
        $offset_y = max( 0, (int) $settings['watermark_offset_y'] );

        switch ( $pos ) {
            case 'top-left':
                return array( $offset_x, $offset_y );
            case 'top-center':
                return array( (int) ( ( $src_w - $el_w ) / 2 ), $offset_y );
            case 'top-right':
                return array( $src_w - $el_w - $offset_x, $offset_y );
            case 'middle-left':
                return array( $offset_x, (int) ( ( $src_h - $el_h ) / 2 ) );
            case 'center':
                return array( (int) ( ( $src_w - $el_w ) / 2 ), (int) ( ( $src_h - $el_h ) / 2 ) );
            case 'middle-right':
                return array( $src_w - $el_w - $offset_x, (int) ( ( $src_h - $el_h ) / 2 ) );
            case 'bottom-left':
                return array( $offset_x, $src_h - $el_h - $offset_y );
            case 'bottom-center':
                return array( (int) ( ( $src_w - $el_w ) / 2 ), $src_h - $el_h - $offset_y );
            case 'bottom-right':
            default:
                return array( $src_w - $el_w - $offset_x, $src_h - $el_h - $offset_y );
        }
    }
}
