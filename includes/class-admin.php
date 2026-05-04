<?php
defined( 'ABSPATH' ) || exit;

class WPIWM_Admin {

    private static $instance = null;

    public static function get_instance() {
        if ( null === self::$instance ) self::$instance = new self();
        return self::$instance;
    }

    private function __construct() {
        add_action( 'admin_menu',            array( $this, 'add_menu' ) );
        add_action( 'admin_enqueue_scripts', array( $this, 'enqueue' ) );
        add_action( 'admin_post_wpiwm_save', array( $this, 'save_settings' ) );
        add_action( 'admin_notices',         array( $this, 'font_notice' ) );
    }

    /* ---- Font availability notice ---- */

    public function font_notice() {
        $screen = get_current_screen();
        if ( ! $screen || $screen->id !== 'settings_page_wp-image-watermark' ) return;

        $settings = WPIWM_Settings::get();
        if ( $settings['watermark_type'] !== 'text' ) return;

        // Use reflection to call the private find_font method
        $ref = new ReflectionMethod( 'WPIWM_Watermark_Engine', 'find_font' );
        $ref->setAccessible( true );
        $font = $ref->invoke( null );

        if ( ! $font ) {
            echo '<div class="notice notice-warning"><p>';
            echo '<strong>WP Image Watermark：</strong> ';
            echo '伺服器上找不到支援中文的字型（如 Noto Sans CJK、WQY 微米黑）。文字浮水印中的中文字元將無法正常顯示。';
            echo '<br>解決方式（擇一）：';
            echo '<ol style="margin:6px 0 0 16px;">';
            echo '<li>SSH 安裝字型：<code>sudo apt-get install fonts-noto-cjk</code>（Ubuntu/Debian）或 <code>sudo yum install google-noto-sans-cjk-fonts</code>（CentOS/RHEL）</li>';
            echo '<li>將 <code>NotoSansCJK-Regular.ttf</code> 或 <code>wqy-microhei.ttc</code> 上傳至 <code>wp-content/fonts/</code> 目錄</li>';
            echo '</ol></p></div>';
        }
    }

    /* ---- Menu ---- */

    public function add_menu() {
        add_options_page(
            'WP Image Watermark',
            'Image Watermark',
            'manage_options',
            'wp-image-watermark',
            array( $this, 'render_page' )
        );
    }

    /* ---- Enqueue ---- */

    public function enqueue( $hook ) {
        if ( $hook !== 'settings_page_wp-image-watermark' ) return;
        wp_enqueue_media();
        wp_enqueue_script(
            'wpiwm-admin',
            WPIWM_PLUGIN_URL . 'assets/js/admin.js',
            array( 'jquery', 'media-upload' ),
            WPIWM_VERSION,
            true
        );
        wp_localize_script( 'wpiwm-admin', 'WPIWM', array(
            'nonce'   => wp_create_nonce( 'wpiwm_nonce' ),
            'ajaxurl' => admin_url( 'admin-ajax.php' ),
        ) );
        wp_enqueue_style(
            'wpiwm-admin',
            WPIWM_PLUGIN_URL . 'assets/css/admin.css',
            array(),
            WPIWM_VERSION
        );
    }

    /* ---- Save ---- */

    public function save_settings() {
        check_admin_referer( 'wpiwm_settings_save' );
        if ( ! current_user_can( 'manage_options' ) ) wp_die( '權限不足' );

        $post = $_POST;
        WPIWM_Settings::update( array(
            'auto_watermark'          => ! empty( $post['auto_watermark'] ),
            'watermark_type'          => in_array( $post['watermark_type'] ?? '', array( 'text', 'image' ), true ) ? $post['watermark_type'] : 'text',
            'watermark_image_id'      => (int) ( $post['watermark_image_id'] ?? 0 ),
            'watermark_image_opacity' => min( 100, max( 0, (int) ( $post['watermark_image_opacity'] ?? 80 ) ) ),
            'watermark_text'          => sanitize_text_field( $post['watermark_text'] ?? '' ),
            'watermark_font_size'     => min( 200, max( 8, (int) ( $post['watermark_font_size'] ?? 36 ) ) ),
            'watermark_font_color'    => sanitize_hex_color( $post['watermark_font_color'] ?? '#ffffff' ) ?: '#ffffff',
            'watermark_text_opacity'  => min( 100, max( 0, (int) ( $post['watermark_text_opacity'] ?? 70 ) ) ),
            'watermark_position'      => sanitize_text_field( $post['watermark_position'] ?? 'bottom-right' ),
            'watermark_offset_x'      => max( 0, (int) ( $post['watermark_offset_x'] ?? 10 ) ),
            'watermark_offset_y'      => max( 0, (int) ( $post['watermark_offset_y'] ?? 10 ) ),
            'watermark_scale'         => min( 100, max( 1, (int) ( $post['watermark_scale'] ?? 20 ) ) ),
            'protect_right_click'     => ! empty( $post['protect_right_click'] ),
            'protect_devtools'        => ! empty( $post['protect_devtools'] ),
        ) );

        wp_redirect( admin_url( 'options-general.php?page=wp-image-watermark&saved=1' ) );
        exit;
    }

    /* ---- Render page ---- */

    public function render_page() {
        $s = WPIWM_Settings::get();
        $saved = isset( $_GET['saved'] );
        ?>
        <div class="wrap">
        <h1>WP Image Watermark 設定</h1>
        <?php if ( $saved ) : ?>
            <div class="notice notice-success is-dismissible"><p>設定已儲存。</p></div>
        <?php endif; ?>

        <form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
            <?php wp_nonce_field( 'wpiwm_settings_save' ); ?>
            <input type="hidden" name="action" value="wpiwm_save">

            <table class="form-table" role="presentation">

                <!-- ===== 浮水印類型 ===== -->
                <tr>
                    <th><?php esc_html_e( '浮水印類型', 'wp-image-watermark' ); ?></th>
                    <td>
                        <label><input type="radio" name="watermark_type" value="text"  <?php checked( $s['watermark_type'], 'text' ); ?>> 文字浮水印</label>&nbsp;&nbsp;
                        <label><input type="radio" name="watermark_type" value="image" <?php checked( $s['watermark_type'], 'image' ); ?>> 圖片浮水印</label>
                    </td>
                </tr>

                <!-- ===== 文字設定 ===== -->
                <tr class="wpiwm-text-row">
                    <th><?php esc_html_e( '浮水印文字', 'wp-image-watermark' ); ?></th>
                    <td><input type="text" name="watermark_text" value="<?php echo esc_attr( $s['watermark_text'] ); ?>" class="regular-text"></td>
                </tr>
                <tr class="wpiwm-text-row">
                    <th><?php esc_html_e( '字型大小 (px)', 'wp-image-watermark' ); ?></th>
                    <td><input type="number" name="watermark_font_size" value="<?php echo (int) $s['watermark_font_size']; ?>" min="8" max="200" class="small-text"></td>
                </tr>
                <tr class="wpiwm-text-row">
                    <th><?php esc_html_e( '字型顏色', 'wp-image-watermark' ); ?></th>
                    <td><input type="color" name="watermark_font_color" value="<?php echo esc_attr( $s['watermark_font_color'] ); ?>"></td>
                </tr>
                <tr class="wpiwm-text-row">
                    <th><?php esc_html_e( '文字透明度 (%)', 'wp-image-watermark' ); ?></th>
                    <td><input type="range" name="watermark_text_opacity" value="<?php echo (int) $s['watermark_text_opacity']; ?>" min="0" max="100" class="wpiwm-range"> <span class="wpiwm-range-val"><?php echo (int) $s['watermark_text_opacity']; ?></span>%</td>
                </tr>

                <!-- ===== 圖片設定 ===== -->
                <tr class="wpiwm-image-row">
                    <th><?php esc_html_e( '浮水印圖片', 'wp-image-watermark' ); ?></th>
                    <td>
                        <div id="wpiwm-image-preview" style="margin-bottom:8px;">
                            <?php if ( $s['watermark_image_id'] ) : ?>
                                <img src="<?php echo esc_url( wp_get_attachment_thumb_url( $s['watermark_image_id'] ) ); ?>" style="max-height:80px;">
                            <?php endif; ?>
                        </div>
                        <input type="hidden" name="watermark_image_id" id="watermark_image_id" value="<?php echo (int) $s['watermark_image_id']; ?>">
                        <button type="button" class="button" id="wpiwm-select-image">選擇媒體</button>
                        <button type="button" class="button" id="wpiwm-clear-image">清除</button>
                    </td>
                </tr>
                <tr class="wpiwm-image-row">
                    <th><?php esc_html_e( '圖片透明度 (%)', 'wp-image-watermark' ); ?></th>
                    <td><input type="range" name="watermark_image_opacity" value="<?php echo (int) $s['watermark_image_opacity']; ?>" min="0" max="100" class="wpiwm-range"> <span class="wpiwm-range-val"><?php echo (int) $s['watermark_image_opacity']; ?></span>%</td>
                </tr>
                <tr class="wpiwm-image-row">
                    <th><?php esc_html_e( '圖片尺寸 (%)', 'wp-image-watermark' ); ?></th>
                    <td><input type="range" name="watermark_scale" value="<?php echo (int) $s['watermark_scale']; ?>" min="1" max="100" class="wpiwm-range"> <span class="wpiwm-range-val"><?php echo (int) $s['watermark_scale']; ?></span>%</td>
                </tr>

                <!-- ===== 位置 ===== -->
                <tr>
                    <th><?php esc_html_e( '浮水印位置', 'wp-image-watermark' ); ?></th>
                    <td>
                        <?php
                        $positions = array(
                            'top-left'=>'左上', 'top-center'=>'上中', 'top-right'=>'右上',
                            'middle-left'=>'左中', 'center'=>'正中', 'middle-right'=>'右中',
                            'bottom-left'=>'左下', 'bottom-center'=>'下中', 'bottom-right'=>'右下',
                        );
                        foreach ( $positions as $val => $label ) :
                            $checked = checked( $s['watermark_position'], $val, false );
                        ?>
                            <label style="margin-right:12px;"><input type="radio" name="watermark_position" value="<?php echo esc_attr($val); ?>" <?php echo $checked; ?>> <?php echo esc_html($label); ?></label>
                        <?php endforeach; ?>
                    </td>
                </tr>
                <tr>
                    <th><?php esc_html_e( 'X 偏移 (px)', 'wp-image-watermark' ); ?></th>
                    <td><input type="number" name="watermark_offset_x" value="<?php echo (int) $s['watermark_offset_x']; ?>" min="0" class="small-text"></td>
                </tr>
                <tr>
                    <th><?php esc_html_e( 'Y 偏移 (px)', 'wp-image-watermark' ); ?></th>
                    <td><input type="number" name="watermark_offset_y" value="<?php echo (int) $s['watermark_offset_y']; ?>" min="0" class="small-text"></td>
                </tr>

                <!-- ===== 自動套用 ===== -->
                <tr>
                    <th><?php esc_html_e( '自動套用', 'wp-image-watermark' ); ?></th>
                    <td><label><input type="checkbox" name="auto_watermark" <?php checked( $s['auto_watermark'] ); ?>> 上傳圖片時自動套用浮水印</label></td>
                </tr>

                <!-- ===== 圖片保護 ===== -->
                <tr>
                    <th><?php esc_html_e( '停用右鍵', 'wp-image-watermark' ); ?></th>
                    <td><label><input type="checkbox" name="protect_right_click" <?php checked( $s['protect_right_click'] ); ?>> 停用滑鼠右鍵選單</label></td>
                </tr>
                <tr>
                    <th><?php esc_html_e( '停用開發工具', 'wp-image-watermark' ); ?></th>
                    <td><label><input type="checkbox" name="protect_devtools" <?php checked( ! empty( $s['protect_devtools'] ) ); ?>> 嘗試阻擋 F12 開發工具</label></td>
                </tr>

            </table>

            <!-- Preview -->
            <h2>浮水印預覽</h2>
            <p class="description">以下為模擬預覽，實際效果以儲存後上傳圖片為準。</p>
            <canvas id="wpiwm-preview" width="640" height="360" style="max-width:100%;border:1px solid #ddd;border-radius:4px;"></canvas>

            <?php submit_button( '儲存設定' ); ?>
        </form>
        </div>
        <?php
    }
}
