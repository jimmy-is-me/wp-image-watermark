<?php
defined( 'ABSPATH' ) || exit;

class WPIWM_Admin {

    private static $instance = null;

    public static function get_instance() {
        if ( null === self::$instance ) self::$instance = new self();
        return self::$instance;
    }

    private function __construct() {
        add_action( 'admin_menu',                     array( $this, 'register_menu' ) );
        add_action( 'admin_enqueue_scripts',          array( $this, 'enqueue' ) );
        add_action( 'admin_post_wpiwm_save_settings', array( $this, 'save_settings' ) );
        add_action( 'admin_notices',                  array( $this, 'admin_notices' ) );
    }

    public function register_menu() {
        add_media_page(
            '圖片浮水印設定',
            '浮水印設定',
            'manage_options',
            'wp-image-watermark',
            array( $this, 'render_page' )
        );
    }

    public function enqueue( $hook ) {
        // Load on settings page, media library, and attachment edit screen
        $allowed = array( 'media_page_wp-image-watermark', 'upload.php' );
        if ( $hook === 'post.php' ) {
            $screen = get_current_screen();
            if ( $screen && $screen->id === 'attachment' ) {
                $allowed[] = 'post.php';
            }
        }
        if ( ! in_array( $hook, $allowed, true ) ) return;

        wp_enqueue_script(
            'wpiwm-admin',
            WPIWM_PLUGIN_URL . 'assets/js/admin.js',
            array( 'jquery' ),
            WPIWM_VERSION,
            true
        );
        wp_localize_script( 'wpiwm-admin', 'WPIWM_Admin', array(
            'ajax_url'            => admin_url( 'admin-ajax.php' ),
            'nonce'               => wp_create_nonce( 'wpiwm_nonce' ),
            'confirm_remove'      => '確定要清除這張圖片的浮水印標記嗎？（圖片本身不會變更）',
            'applying'            => '套用中…',
            'removing'            => '清除中…',
            'preview_sample_text' => '浮水印預覽',
        ) );

        if ( $hook === 'media_page_wp-image-watermark' ) {
            wp_enqueue_style(
                'wpiwm-admin',
                WPIWM_PLUGIN_URL . 'assets/css/admin.css',
                array(),
                WPIWM_VERSION
            );
            wp_enqueue_media();
        }
    }

    public function save_settings() {
        check_admin_referer( 'wpiwm_save_settings' );
        if ( ! current_user_can( 'manage_options' ) ) wp_die( '權限不足' );

        $p = $_POST;
        WPIWM_Settings::update( array(
            'auto_watermark'          => ! empty( $p['auto_watermark'] )   ? 1 : 0,
            'watermark_type'          => in_array( $p['watermark_type'] ?? '', array( 'image', 'text' ), true )
                                            ? $p['watermark_type'] : 'text',
            'watermark_image_id'      => (int)( $p['watermark_image_id'] ?? 0 ),
            'watermark_image_opacity' => max( 0, min( 100, (int)( $p['watermark_image_opacity'] ?? 80 ) ) ),
            'watermark_text'          => sanitize_text_field( $p['watermark_text'] ?? '' ),
            'watermark_font_size'     => max( 8, min( 200, (int)( $p['watermark_font_size'] ?? 36 ) ) ),
            'watermark_font_color'    => sanitize_hex_color( $p['watermark_font_color'] ?? '#ffffff' ),
            'watermark_text_opacity'  => max( 0, min( 100, (int)( $p['watermark_text_opacity'] ?? 70 ) ) ),
            'watermark_position'      => sanitize_text_field( $p['watermark_position'] ?? 'bottom-right' ),
            'watermark_offset_x'      => max( 0, (int)( $p['watermark_offset_x'] ?? 10 ) ),
            'watermark_offset_y'      => max( 0, (int)( $p['watermark_offset_y'] ?? 10 ) ),
            'watermark_scale'         => max( 1, min( 100, (int)( $p['watermark_scale'] ?? 20 ) ) ),
            'protect_right_click'     => ! empty( $p['protect_right_click'] ) ? 1 : 0,
        ) );
        wp_redirect( admin_url( 'upload.php?page=wp-image-watermark&saved=1' ) );
        exit;
    }

    public function admin_notices() {
        if ( isset( $_GET['page'], $_GET['saved'] ) && $_GET['page'] === 'wp-image-watermark' ) {
            echo '<div class="notice notice-success is-dismissible"><p>設定已儲存。</p></div>';
        }
    }

    public function render_page() {
        $opts = WPIWM_Settings::get();
        $positions = array(
            'top-left'     => '左上', 'top-center'    => '中上', 'top-right'     => '右上',
            'middle-left'  => '左中', 'center'         => '正中', 'middle-right'  => '右中',
            'bottom-left'  => '左下', 'bottom-center' => '中下', 'bottom-right'  => '右下',
        );
        $wm_img_url = $opts['watermark_image_id']
            ? wp_get_attachment_image_url( $opts['watermark_image_id'], 'thumbnail' )
            : '';
        ?>
        <div class="wrap wpiwm-wrap">
            <h1>圖片浮水印設定</h1>

            <form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
                <?php wp_nonce_field( 'wpiwm_save_settings' ); ?>
                <input type="hidden" name="action" value="wpiwm_save_settings">

                <!-- Auto watermark toggle -->
                <div class="wpiwm-card wpiwm-toggle-card <?php echo $opts['auto_watermark'] ? 'is-active' : ''; ?>" id="wpiwm-auto-card">
                    <div class="wpiwm-toggle-header">
                        <div>
                            <h2>自動浮水印</h2>
                            <p class="description">上傳新圖片時自動套用浮水印。</p>
                        </div>
                        <label class="wpiwm-switch">
                            <input type="checkbox" name="auto_watermark" value="1" id="auto_watermark" <?php checked( $opts['auto_watermark'] ); ?>>
                            <span class="wpiwm-slider"></span>
                        </label>
                    </div>
                </div>

                <!-- Watermark type + preview -->
                <div class="wpiwm-card">
                    <h2>浮水印類型</h2>
                    <div class="wpiwm-type-tabs">
                        <label class="wpiwm-type-tab <?php echo $opts['watermark_type']==='text'?'active':''; ?>">
                            <input type="radio" name="watermark_type" value="text" <?php checked( $opts['watermark_type'], 'text' ); ?>>
                            <span class="dashicons dashicons-editor-textcolor"></span> 文字浮水印
                        </label>
                        <label class="wpiwm-type-tab <?php echo $opts['watermark_type']==='image'?'active':''; ?>">
                            <input type="radio" name="watermark_type" value="image" <?php checked( $opts['watermark_type'], 'image' ); ?>>
                            <span class="dashicons dashicons-format-image"></span> 圖像浮水印
                        </label>
                    </div>

                    <div class="wpiwm-preview-box">
                        <div class="wpiwm-preview-header">
                            <h3>效果預覽</h3>
                            <p class="description">此預覽僅供設定位置、大小與透明度參考。</p>
                        </div>
                        <div class="wpiwm-preview-stage">
                            <canvas id="wpiwm-preview-canvas" width="960" height="540"></canvas>
                        </div>
                    </div>

                    <!-- Text settings -->
                    <div id="wpiwm-text-settings" class="wpiwm-type-settings" <?php echo $opts['watermark_type']!=='text'?'style="display:none"':''; ?>>
                        <table class="form-table">
                            <tr>
                                <th>浮水印文字</th>
                                <td><input type="text" name="watermark_text" value="<?php echo esc_attr( $opts['watermark_text'] ); ?>" class="regular-text"></td>
                            </tr>
                            <tr>
                                <th>字體大小 (px)</th>
                                <td><input type="number" name="watermark_font_size" value="<?php echo esc_attr( $opts['watermark_font_size'] ); ?>" min="8" max="200" class="small-text"> px</td>
                            </tr>
                            <tr>
                                <th>字體顏色</th>
                                <td><input type="color" name="watermark_font_color" value="<?php echo esc_attr( $opts['watermark_font_color'] ); ?>"></td>
                            </tr>
                            <tr>
                                <th>不透明度</th>
                                <td>
                                    <input type="range" name="watermark_text_opacity" value="<?php echo esc_attr( $opts['watermark_text_opacity'] ); ?>" min="0" max="100" class="wpiwm-range" oninput="this.nextElementSibling.textContent=this.value+'%'">
                                    <span><?php echo esc_html( $opts['watermark_text_opacity'] ); ?>%</span>
                                </td>
                            </tr>
                        </table>
                    </div>

                    <!-- Image settings -->
                    <div id="wpiwm-image-settings" class="wpiwm-type-settings" <?php echo $opts['watermark_type']!=='image'?'style="display:none"':''; ?>>
                        <table class="form-table">
                            <tr>
                                <th>浮水印圖片</th>
                                <td>
                                    <input type="hidden" name="watermark_image_id" id="watermark_image_id" value="<?php echo esc_attr( $opts['watermark_image_id'] ); ?>">
                                    <div id="wpiwm-image-preview" style="margin-bottom:8px;">
                                        <?php if ( $wm_img_url ) : ?>
                                            <img src="<?php echo esc_url( $wm_img_url ); ?>" style="max-width:120px;max-height:80px;border:1px solid #ddd;border-radius:4px;">
                                        <?php endif; ?>
                                    </div>
                                    <button type="button" class="button" id="wpiwm-select-image">選擇圖片</button>
                                    <button type="button" class="button" id="wpiwm-remove-image" <?php echo ! $opts['watermark_image_id'] ? 'style="display:none"' : ''; ?>>移除</button>
                                </td>
                            </tr>
                            <tr>
                                <th>圖片大小（佔原圖寬 %）</th>
                                <td>
                                    <input type="range" name="watermark_scale" value="<?php echo esc_attr( $opts['watermark_scale'] ); ?>" min="1" max="100" class="wpiwm-range" oninput="this.nextElementSibling.textContent=this.value+'%'">
                                    <span><?php echo esc_html( $opts['watermark_scale'] ); ?>%</span>
                                </td>
                            </tr>
                            <tr>
                                <th>不透明度</th>
                                <td>
                                    <input type="range" name="watermark_image_opacity" value="<?php echo esc_attr( $opts['watermark_image_opacity'] ); ?>" min="0" max="100" class="wpiwm-range" oninput="this.nextElementSibling.textContent=this.value+'%'">
                                    <span><?php echo esc_html( $opts['watermark_image_opacity'] ); ?>%</span>
                                </td>
                            </tr>
                        </table>
                    </div>
                </div>

                <!-- Position -->
                <div class="wpiwm-card">
                    <h2>浮水印位置</h2>
                    <div class="wpiwm-position-grid">
                        <?php foreach ( $positions as $val => $label ) : ?>
                            <label class="wpiwm-pos-cell <?php echo $opts['watermark_position']===$val?'active':''; ?>">
                                <input type="radio" name="watermark_position" value="<?php echo esc_attr( $val ); ?>" <?php checked( $opts['watermark_position'], $val ); ?>>
                                <span><?php echo esc_html( $label ); ?></span>
                            </label>
                        <?php endforeach; ?>
                    </div>
                    <table class="form-table" style="margin-top:12px;">
                        <tr>
                            <th>X 位移 (px)</th>
                            <td><input type="number" name="watermark_offset_x" value="<?php echo esc_attr( $opts['watermark_offset_x'] ); ?>" min="0" class="small-text"></td>
                        </tr>
                        <tr>
                            <th>Y 位移 (px)</th>
                            <td><input type="number" name="watermark_offset_y" value="<?php echo esc_attr( $opts['watermark_offset_y'] ); ?>" min="0" class="small-text"></td>
                        </tr>
                    </table>
                </div>

                <!-- Protection -->
                <div class="wpiwm-card">
                    <h2>影像保護</h2>
                    <table class="form-table">
                        <tr>
                            <th>停用右鍵選單</th>
                            <td><label><input type="checkbox" name="protect_right_click" value="1" <?php checked( $opts['protect_right_click'] ); ?>> 在圖片上停用右鍵選單</label></td>
                        </tr>
                    </table>
                </div>

                <p class="submit">
                    <input type="submit" class="button-primary" value="儲存設定">
                </p>
            </form>
        </div>
        <?php
    }
}
