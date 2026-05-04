<?php
defined( 'ABSPATH' ) || exit;

class WPIWM_Watermark_Engine {

    public static function apply( $file_path, $opts = array() ) {
        if ( ! file_exists( $file_path ) ) {
            error_log( 'WPIWM Engine: file not found – ' . $file_path );
            return false;
        }
        if ( ! is_writable( $file_path ) ) {
            error_log( 'WPIWM Engine: file not writable – ' . $file_path );
            return false;
        }

        $settings = wp_parse_args( $opts, WPIWM_Settings::get() );
        $ext      = strtolower( pathinfo( $file_path, PATHINFO_EXTENSION ) );

        if ( ! in_array( $ext, array( 'jpg', 'jpeg', 'png', 'webp' ), true ) ) {
            error_log( 'WPIWM Engine: unsupported extension "' . $ext . '"' );
            return false;
        }

        if ( $settings['watermark_type'] === 'text' ) {
            if ( '' === trim( (string) $settings['watermark_text'] ) ) {
                error_log( 'WPIWM Engine: watermark_text is empty' );
                return false;
            }
        } else {
            if ( empty( $settings['watermark_image_id'] ) ) {
                error_log( 'WPIWM Engine: watermark_image_id is 0' );
                return false;
            }
        }

        error_log( sprintf( 'WPIWM Engine: start type=%s file=%s', $settings['watermark_type'], basename( $file_path ) ) );

        if ( extension_loaded( 'gd' ) && function_exists( 'imagecreatetruecolor' ) ) {
            $ok = self::via_gd( $file_path, $ext, $settings );
            error_log( 'WPIWM Engine: GD result=' . ( $ok ? 'OK' : 'FAIL' ) );
            return $ok;
        }
        if ( extension_loaded( 'imagick' ) ) {
            $ok = self::via_imagick( $file_path, $ext, $settings );
            error_log( 'WPIWM Engine: Imagick result=' . ( $ok ? 'OK' : 'FAIL' ) );
            return $ok;
        }

        error_log( 'WPIWM Engine: no GD or Imagick available' );
        return false;
    }

    /* ----------------------------------------------------------------
     * GD
     * ---------------------------------------------------------------- */

    private static function via_gd( $file_path, $ext, $settings ) {
        $canvas = self::gd_load( $file_path, $ext );
        if ( ! $canvas ) {
            error_log( 'WPIWM GD: imagecreatefrom* failed' );
            return false;
        }
        $w = imagesx( $canvas );
        $h = imagesy( $canvas );
        imagealphablending( $canvas, true );
        imagesavealpha( $canvas, true );

        $ok = ( $settings['watermark_type'] === 'image' )
            ? self::gd_stamp_image( $canvas, $w, $h, $settings )
            : self::gd_stamp_text( $canvas, $w, $h, $settings );

        if ( $ok ) {
            $saved = self::gd_save( $canvas, $file_path, $ext );
            if ( ! $saved ) {
                error_log( 'WPIWM GD: save failed' );
                $ok = false;
            }
        }
        imagedestroy( $canvas );
        return $ok;
    }

    private static function gd_load( $path, $ext ) {
        switch ( $ext ) {
            case 'jpg':
            case 'jpeg': return function_exists( 'imagecreatefromjpeg' ) ? @imagecreatefromjpeg( $path ) : false;
            case 'png':  return function_exists( 'imagecreatefrompng' )  ? @imagecreatefrompng( $path )  : false;
            case 'webp': return function_exists( 'imagecreatefromwebp' ) ? @imagecreatefromwebp( $path ) : false;
        }
        return false;
    }

    /**
     * Atomic save: write to .wpiwm_tmp then rename to avoid partial-write corruption.
     */
    private static function gd_save( $img, $path, $ext ) {
        $tmp = $path . '.wpiwm_tmp';
        $ok  = false;
        switch ( $ext ) {
            case 'jpg':
            case 'jpeg': $ok = imagejpeg( $img, $tmp, 90 ); break;
            case 'png':  $ok = imagepng( $img, $tmp, 6 );   break;
            case 'webp':
                if ( function_exists( 'imagewebp' ) ) $ok = imagewebp( $img, $tmp, 85 );
                break;
        }
        if ( $ok && file_exists( $tmp ) ) {
            if ( rename( $tmp, $path ) ) return true;
            if ( copy( $tmp, $path ) )  { @unlink( $tmp ); return true; }
            @unlink( $tmp );
        }
        return false;
    }

    private static function gd_stamp_image( $canvas, $src_w, $src_h, $settings ) {
        $wm_path = get_attached_file( (int) $settings['watermark_image_id'] );
        if ( ! $wm_path || ! file_exists( $wm_path ) ) {
            error_log( 'WPIWM GD image: watermark file not found' );
            return false;
        }
        $wm_ext = strtolower( pathinfo( $wm_path, PATHINFO_EXTENSION ) );
        $wm     = self::gd_load( $wm_path, $wm_ext );
        if ( ! $wm ) return false;

        $scale     = max( 1, min( 100, (int) $settings['watermark_scale'] ) );
        $wm_orig_w = imagesx( $wm );
        $wm_orig_h = imagesy( $wm );
        $wm_w      = max( 1, (int) round( $src_w * $scale / 100 ) );
        $wm_h      = max( 1, (int) round( $wm_orig_h * ( $wm_w / max( 1, $wm_orig_w ) ) ) );

        $wm_r = imagecreatetruecolor( $wm_w, $wm_h );
        imagealphablending( $wm_r, false );
        imagesavealpha( $wm_r, true );
        $trans = imagecolorallocatealpha( $wm_r, 0, 0, 0, 127 );
        imagefilledrectangle( $wm_r, 0, 0, $wm_w, $wm_h, $trans );
        imagecopyresampled( $wm_r, $wm, 0, 0, 0, 0, $wm_w, $wm_h, $wm_orig_w, $wm_orig_h );
        imagedestroy( $wm );

        $opacity       = max( 0, min( 100, (int) $settings['watermark_image_opacity'] ) );
        list( $x, $y ) = self::position_xy( $src_w, $src_h, $wm_w, $wm_h, $settings );
        $x = max( 0, min( $src_w - $wm_w, $x ) );
        $y = max( 0, min( $src_h - $wm_h, $y ) );

        self::gd_merge_alpha( $canvas, $wm_r, $x, $y, 0, 0, $wm_w, $wm_h, $opacity );
        imagedestroy( $wm_r );
        return true;
    }

    private static function gd_stamp_text( $canvas, $src_w, $src_h, $settings ) {
        $text = trim( (string) $settings['watermark_text'] );
        if ( '' === $text ) return false;

        $font_size = max( 8, (int) $settings['watermark_font_size'] );
        $hex = ltrim( (string) $settings['watermark_font_color'], '#' );
        if ( strlen( $hex ) !== 6 ) $hex = 'ffffff';
        $r       = hexdec( substr( $hex, 0, 2 ) );
        $g       = hexdec( substr( $hex, 2, 2 ) );
        $b       = hexdec( substr( $hex, 4, 2 ) );
        $opacity = max( 0, min( 100, (int) $settings['watermark_text_opacity'] ) );
        // GD alpha: 0=opaque, 127=transparent
        $alpha_gd = (int) round( 127 - ( $opacity / 100.0 * 127 ) );
        $color    = imagecolorallocatealpha( $canvas, $r, $g, $b, $alpha_gd );

        $font_file = WPIWM_PLUGIN_DIR . 'assets/fonts/OpenSans-Regular.ttf';
        $use_ttf   = file_exists( $font_file ) && function_exists( 'imagettftext' ) && function_exists( 'imagettfbbox' );

        error_log( sprintf( 'WPIWM GD text: "%s" size=%d #%s opacity=%d ttf=%s', $text, $font_size, $hex, $opacity, $use_ttf ? 'yes' : 'no' ) );

        if ( $use_ttf ) {
            $bbox   = imagettfbbox( $font_size, 0, $font_file, $text );
            $text_w = abs( $bbox[4] - $bbox[6] );
            $text_h = abs( $bbox[7] - $bbox[1] );
            list( $x, $y ) = self::position_xy( $src_w, $src_h, $text_w, $text_h, $settings );
            imagettftext( $canvas, $font_size, 0, max( 0, $x ), max( $text_h, $y + $text_h ), $color, $font_file, $text );
        } else {
            $gd_font = 5;
            $text_w  = imagefontwidth( $gd_font ) * mb_strlen( $text );
            $text_h  = imagefontheight( $gd_font );
            list( $x, $y ) = self::position_xy( $src_w, $src_h, $text_w, $text_h, $settings );
            imagestring( $canvas, $gd_font, max( 0, $x ),     max( 0, $y ), $text, $color );
            imagestring( $canvas, $gd_font, max( 0, $x ) + 1, max( 0, $y ), $text, $color );
        }
        return true;
    }

    private static function gd_merge_alpha( $dst, $src, $dst_x, $dst_y, $src_x, $src_y, $src_w, $src_h, $pct ) {
        if ( $pct <= 0 )   return;
        if ( $pct >= 100 ) { imagecopy( $dst, $src, $dst_x, $dst_y, $src_x, $src_y, $src_w, $src_h ); return; }
        $cut = imagecreatetruecolor( $src_w, $src_h );
        imagealphablending( $cut, false );
        imagesavealpha( $cut, true );
        imagecopy( $cut, $dst, 0, 0, $dst_x, $dst_y, $src_w, $src_h );
        imagecopy( $cut, $src, 0, 0, $src_x, $src_y, $src_w, $src_h );
        imagecopymerge( $dst, $cut, $dst_x, $dst_y, 0, 0, $src_w, $src_h, $pct );
        imagedestroy( $cut );
    }

    /* ----------------------------------------------------------------
     * Imagick
     * ---------------------------------------------------------------- */

    private static function via_imagick( $file_path, $ext, $settings ) {
        try {
            $img = new Imagick( $file_path );
            $img->setImageCompressionQuality( 90 );
            $src_w = $img->getImageWidth();
            $src_h = $img->getImageHeight();
            $ok = ( $settings['watermark_type'] === 'image' )
                ? self::imagick_stamp_image( $img, $src_w, $src_h, $settings )
                : self::imagick_stamp_text( $img, $src_w, $src_h, $settings );
            if ( $ok ) $img->writeImage( $file_path );
            $img->destroy();
            return $ok;
        } catch ( Exception $e ) {
            error_log( 'WPIWM Imagick: ' . $e->getMessage() );
            return false;
        }
    }

    private static function imagick_stamp_image( $img, $src_w, $src_h, $settings ) {
        $wm_path = get_attached_file( (int) $settings['watermark_image_id'] );
        if ( ! $wm_path || ! file_exists( $wm_path ) ) return false;
        try {
            $wm   = new Imagick( $wm_path );
            $wm_w = max( 1, (int) round( $src_w * max(1,min(100,(int)$settings['watermark_scale'])) / 100 ) );
            $wm->resizeImage( $wm_w, 0, Imagick::FILTER_LANCZOS, 1 );
            $wm_h = $wm->getImageHeight();
            $wm->evaluateImage(
                Imagick::EVALUATE_MULTIPLY,
                max(0,min(100,(int)$settings['watermark_image_opacity'])) / 100,
                Imagick::CHANNEL_ALPHA
            );
            list( $x, $y ) = self::position_xy( $src_w, $src_h, $wm_w, $wm_h, $settings );
            $img->compositeImage( $wm, Imagick::COMPOSITE_OVER, $x, $y );
            $wm->destroy();
            return true;
        } catch ( Exception $e ) {
            error_log( 'WPIWM Imagick stamp_image: ' . $e->getMessage() );
            return false;
        }
    }

    private static function imagick_stamp_text( $img, $src_w, $src_h, $settings ) {
        $text = trim( (string) $settings['watermark_text'] );
        if ( '' === $text ) return false;
        try {
            $draw = new ImagickDraw();
            $draw->setFontSize( max( 8, (int) $settings['watermark_font_size'] ) );
            $draw->setFillColor( new ImagickPixel( (string) $settings['watermark_font_color'] ) );
            $draw->setFillOpacity( max(0,min(100,(int)$settings['watermark_text_opacity'])) / 100 );
            $font_file = WPIWM_PLUGIN_DIR . 'assets/fonts/OpenSans-Regular.ttf';
            if ( file_exists( $font_file ) ) $draw->setFont( $font_file );
            $m = $img->queryFontMetrics( $draw, $text );
            list( $x, $y ) = self::position_xy( $src_w, $src_h, (int)$m['textWidth'], (int)$m['textHeight'], $settings );
            $img->annotateImage( $draw, $x, $y + (int)$m['textHeight'], 0, $text );
            return true;
        } catch ( Exception $e ) {
            error_log( 'WPIWM Imagick stamp_text: ' . $e->getMessage() );
            return false;
        }
    }

    /* ----------------------------------------------------------------
     * Shared helpers
     * ---------------------------------------------------------------- */

    private static function position_xy( $src_w, $src_h, $el_w, $el_h, $settings ) {
        $pos = (string) $settings['watermark_position'];
        $ox  = max( 0, (int) $settings['watermark_offset_x'] );
        $oy  = max( 0, (int) $settings['watermark_offset_y'] );
        switch ( $pos ) {
            case 'top-left':      return array( $ox,              $oy );
            case 'top-center':    return array( (int)(($src_w-$el_w)/2), $oy );
            case 'top-right':     return array( $src_w-$el_w-$ox, $oy );
            case 'middle-left':   return array( $ox,              (int)(($src_h-$el_h)/2) );
            case 'center':        return array( (int)(($src_w-$el_w)/2), (int)(($src_h-$el_h)/2) );
            case 'middle-right':  return array( $src_w-$el_w-$ox, (int)(($src_h-$el_h)/2) );
            case 'bottom-left':   return array( $ox,              $src_h-$el_h-$oy );
            case 'bottom-center': return array( (int)(($src_w-$el_w)/2), $src_h-$el_h-$oy );
            case 'bottom-right':
            default:              return array( $src_w-$el_w-$ox, $src_h-$el_h-$oy );
        }
    }
}
