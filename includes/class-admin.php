<?php
defined( 'ABSPATH' ) || exit;

class WPIWM_Admin {

    private static $instance = null;

    public static function get_instance() {
        if ( null === self::$instance ) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    private function __construct() {
        add_action( 'admin_menu', array( $this, 'register_menu' ) );
        add_action( 'admin_enqueue_scripts', array( $this, 'enqueue' ) );
        add_action( 'admin_post_wpiwm_save_settings', array( $this, 'save_settings' ) );
        add_action( 'admin_notices', array( $this, 'admin_notices' ) );
    }

    public function register_menu() {
        add_media_page(
            __( '圖片浮水印設定', 'wp-image-watermark' ),
            __( '浮水印設定', 'wp-image-watermark' ),
            'manage_options',
            'wp-image-watermark',
            array( $this, 'render_page' )
        );
    }

    public function enqueue( $hook ) {
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
            'confirm_batch'       => __( '確定要批次套用浮水印到所有已選取的圖片嗎？此操作無法自動還原，請確保本機有原始備份。', 'wp-image-watermark' ),
            'confirm_remove'      => __( '確定要清除這張圖片的浮水印標記嗎？（圖片本身不會變更）', 'wp-image-watermark' ),
            'applying'            => __( '套用中…', 'wp-image-watermark' ),
            'removing'            => __( '清除中…', 'wp-image-watermark' ),
            'preview_sample_text' => __( '預覽文字', 'wp-image-watermark' ),
        ) );

        if ( $hook !== 'media_page_wp-image-watermark' ) return;
        wp_enqueue_style(
            'wpiwm-admin',
            WPIWM_PLUGIN_URL . 'assets/css/admin.css',
            array(),
            WPIWM_VERSION
        );
        wp_enqueue_media();
    }

    public function save_settings() {
        check_admin_referer( 'wpiwm_save_settings' );
        if ( ! current_user_can( 'manage_options' ) ) {
            wp_die( esc_html__( '權限不足', 'wp-image-watermark' ) );
        }
        $post = $_POST;
        $data = array(
            'auto_watermark'          => ! empty( $post['auto_watermark'] ),
            'watermark_type'          => in_array( $post['watermark_type'] ?? '', array( 'image', 'text' ), true ) ? $post['watermark_type'] : 'text',
            'watermark_image_id'      => (int) ( $post['watermark_image_id'] ?? 0 ),
            'watermark_image_opacity' => max( 0, min( 100, (int) ( $post['watermark_image_opacity'] ?? 80 ) ) ),
            'watermark_text'          => sanitize_text_field( $post['watermark_text'] ?? '' ),
            'watermark_font_size'     => max( 8, min( 200, (int) ( $post['watermark_font_size'] ?? 36 ) ) ),
            'watermark_font_color'    => sanitize_hex_color( $post['watermark_font_color'] ?? '#ffffff' ),
            'watermark_text_opacity'  => max( 0, min( 100, (int) ( $post['watermark_text_opacity'] ?? 70 ) ) ),
            'watermark_font_family'   => sanitize_text_field( $post['watermark_font_family'] ?? 'arial' ),
            'watermark_position'      => sanitize_text_field( $post['watermark_position'] ?? 'bottom-right' ),
            'watermark_offset_x'      => max( 0, (int) ( $post['watermark_offset_x'] ?? 10 ) ),
            'watermark_offset_y'      => max( 0, (int) ( $post['watermark_offset_y'] ?? 10 ) ),
            'watermark_scale'         => max( 1, min( 100, (int) ( $post['watermark_scale'] ?? 20 ) ) ),
            'protect_right_click'     => ! empty( $post['protect_right_click'] ),
            'protect_devtools'        => ! empty( $post['protect_devtools'] ),
        );
        WPIWM_Settings::update( $data );
        wp_redirect( admin_url( 'upload.php?page=wp-image-watermark&saved=1' ) );
        exit;
    }

    public function admin_notices() {
        if ( isset( $_GET['page'] ) && $_GET['page'] === 'wp-image-watermark' && isset( $_GET['saved'] ) ) {
            echo '<div class="notice notice-success is-dismissible"><p>' . esc_html__( '設定已儲存。', 'wp-image-watermark' ) . '</p></div>';
        }
    }

    public function render_page() {
        $opts      = WPIWM_Settings::get();
        $positions = array(
            'top-left'      => __( '左上', 'wp-image-watermark' ),
            'top-center'    => __( '中上', 'wp-image-watermark' ),
            'top-right'     => __( '右上', 'wp-image-watermark' ),
            'middle-left'   => __( '左中', 'wp-image-watermark' ),
            'center'        => __( '正中', 'wp-image-watermark' ),
            'middle-right'  => __( '右中', 'wp-image-watermark' ),
            'bottom-left'   => __( '左下', 'wp-image-watermark' ),
            'bottom-center' => __( '中下', 'wp-image-watermark' ),
            'bottom-right'  => __( '右下', 'wp-image-watermark' ),
        );
        $wm_img_url = $opts['watermark_image_id'] ? wp_get_attachment_image_url( $opts['watermark_image_id'], 'thumbnail' ) : '';
        ?>
        <div class="wrap wpiwm-wrap">
            <h1><?php esc_html_e( '圖片浮水印設定', 'wp-image-watermark' ); ?></h1>

            <form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
                <?php wp_nonce_field( 'wpiwm_save_settings' ); ?>
                <input type="hidden" name="action" value="wpiwm_save_settings">

                <div class="wpiwm-card wpiwm-toggle-card <?php echo $opts['auto_watermark'] ? 'is-active' : ''; ?>" id="wpiwm-auto-card">
                    <div class="wpiwm-toggle-header">
                        <div>
                            <h2><?php esc_html_e( '自動浮水印', 'wp-image-watermark' ); ?></h2>
                            <p class="description"><?php esc_html_e( '上傳新圖片時自動套用浮水印。可隨時切換關閉，對已上傳的圖片不影響。', 'wp-image-watermark' ); ?></p>
                        </div>
                        <label class="wpiwm-switch">
                            <input type="checkbox" name="auto_watermark" value="1" id="auto_watermark" <?php checked( $opts['auto_watermark'] ); ?>>
                            <span class="wpiwm-slider"></span>
                        </label>
                    </div>
                </div>

                <div class="wpiwm-card">
                    <h2><?php esc_html_e( '浮水印類型', 'wp-image-watermark' ); ?></h2>
                    <div class="wpiwm-type-tabs">
                        <label class="wpiwm-type-tab <?php echo $opts['watermark_type'] === 'text' ? 'active' : ''; ?>">
                            <input type="radio" name="watermark_type" value="text" <?php checked( $opts['watermark_type'], 'text' ); ?>>
                            <span class="dashicons dashicons-editor-textcolor"></span>
                            <?php esc_html_e( '文字浮水印', 'wp-image-watermark' ); ?>
                        </label>
                        <label class="wpiwm-type-tab <?php echo $opts['watermark_type'] === 'image' ? 'active' : ''; ?>">
                            <input type="radio" name="watermark_type" value="image" <?php checked( $opts['watermark_type'], 'image' ); ?>>
                            <span class="dashicons dashicons-format-image"></span>
                            <?php esc_html_e( '圖像浮水印', 'wp-image-watermark' ); ?>
                        </label>
                    </div>

                    <div class="wpiwm-preview-box">
                        <div class="wpiwm-preview-header">
                            <h3><?php esc_html_e( '效果預覽', 'wp-image-watermark' ); ?></h3>
                            <p class="description"><?php esc_html_e( '此預覽僅供設定位置、大小與透明度參考。', 'wp-image-watermark' ); ?></p>
                        </div>
                        <div class="wpiwm-preview-stage">
                            <canvas id="wpiwm-preview-canvas" width="960" height="540"></canvas>
                        </div>
                    </div>

                    <div id="wpiwm-text-settings" class="wpiwm-type-settings" <?php echo $opts['watermark_type'] !== 'text' ? 'style="display:none"' : ''; ?>>
                        <table class="form-table">
                            <tr>
                                <th><?php esc_html_e( '浮水印文字', 'wp-image-watermark' ); ?></th>
                                <td><input type="text" name="watermark_text" value="<?php echo esc_attr( $opts['watermark_text'] ); ?>" class="regular-text"></td>
                            </tr>
                            <tr>
                                <th><?php esc_html_e( '字體大小 (px)', 'wp-image-watermark' ); ?></th>
                                <td><input type="number" name="watermark_font_size" value="<?php echo esc_attr( $opts['watermark_font_size'] ); ?>" min="8" max="200" class="small-text"> px</td>
                            </tr>
                            <tr>
                                <th><?php esc_html_e( '字體顏色', 'wp-image-watermark' ); ?></th>
                                <td><input type="color" name="watermark_font_color" value="<?php echo esc_attr( $opts['watermark_font_color'] ); ?>"></td>
                            </tr>
                            <tr>
                                <th><?php esc_html_e( '不透明度', 'wp-image-watermark' ); ?></th>
                                <td>
                                    <input type="range" name="watermark_text_opacity" value="<?php echo esc_attr( $opts['watermark_text_opacity'] ); ?>" min="0" max="100" class="wpiwm-range" oninput="this.nextElementSibling.textContent=this.value+'%'">
                                    <span><?php echo esc_html( $opts['watermark_text_opacity'] ); ?>%</span>
                                </td>
                            </tr>
                        </table>
                    </div>

                    <div id="wpiwm-image-settings" class="wpiwm-type-settings" <?php echo $opts['watermark_type'] !== 'image' ? 'style="display:none"' : ''; ?>>
                        <table class="form-table">
                            <tr>
                                <th><?php esc_html_e( '浮水印圖片', 'wp-image-watermark' ); ?></th>
                                <td>
                                    <input type="hidden" name="watermark_image_id" id="watermark_image_id" value="<?php echo esc_attr( $opts['watermark_image_id'] ); ?>">
                                    <div id="wpiwm-image-preview" style="margin-bottom:8px;">
                                        <?php if ( $wm_img_url ) : ?>
                                            <img src="<?php echo esc_url( $wm_img_url ); ?>" style="max-width:120px;max-height:80px;border:1px solid #ddd;border-radius:4px;">
                                        <?php endif; ?>
                                    </div>
                                    <button type="button" class="button" id="wpiwm-select-image"><?php esc_html_e( '選擇圖片', 'wp-image-watermark' ); ?></button>
                                    <button type="button" class="button" id="wpiwm-remove-image" <?php echo ! $opts['watermark_image_id'] ? 'style="display:none"' : ''; ?>><?php esc_html_e( '移除', 'wp-image-watermark' ); ?></button>
                                </td>
                            </tr>
                            <tr>
                                <th><?php esc_html_e( '圖片大小（佔原圖寬 %）', 'wp-image-watermark' ); ?></th>
                                <td>
                                    <input type="range" name="watermark_scale" value="<?php echo esc_attr( $opts['watermark_scale'] ); ?>" min="1" max="100" class="wpiwm-range" oninput="this.nextElementSibling.textContent=this.value+'%'">
                                    <span><?php echo esc_html( $opts['watermark_scale'] ); ?>%</span>
                                </td>
                            </tr>
                            <tr>
                                <th><?php esc_html_e( '不透明度', 'wp-image-watermark' ); ?></th>
                                <td>
                                    <input type="range" name="watermark_image_opacity" value="<?php echo esc_attr( $opts['watermark_image_opacity'] ); ?>" min="0" max="100" class="wpiwm-range" oninput="this.nextElementSibling.textContent=this.value+'%'">
                                    <span><?php echo esc_html( $opts['watermark_image_opacity'] ); ?>%</span>
                                </td>
                            </tr>
                        </table>
                    </div>
                </div>

                <div class="wpiwm-card">
                    <h2><?php esc_html_e( '浮水印位置', 'wp-image-watermark' ); ?></h2>
                    <div class="wpiwm-position-grid">
                        <?php foreach ( $positions as $val => $label ) : ?>
                            <label class="wpiwm-pos-cell <?php echo $opts['watermark_position'] === $val ? 'active' : ''; ?>">
                                <input type="radio" name="watermark_position" value="<?php echo esc_attr( $val ); ?>" <?php checked( $opts['watermark_position'], $val ); ?>>
                                <span><?php echo esc_html( $label ); ?></span>
                            </label>
                        <?php endforeach; ?>
                    </div>
                    <table class="form-table" style="margin-top:12px;">
                        <tr>
                            <th><?php esc_html_e( 'X 位移 (px)', 'wp-image-watermark' ); ?></th>
                            <td><input type="number" name="watermark_offset_x" value="<?php echo esc_attr( $opts['watermark_offset_x'] ); ?>" min="0" class="small-text"></td>
                        </tr>
                        <tr>
                            <th><?php esc_html_e( 'Y 位移 (px)', 'wp-image-watermark' ); ?></th>
                            <td><input type="number" name="watermark_offset_y" value="<?php echo esc_attr( $opts['watermark_offset_y'] ); ?>" min="0" class="small-text"></td>
                        </tr>
                    </table>
                </div>

                <div class="wpiwm-card">
                    <h2><?php esc_html_e( '影像保護', 'wp-image-watermark' ); ?></h2>
                    <p class="description" style="margin-bottom:12px;"><?php esc_html_e( '注意：前端保護為輔助措施，無法 100% 阻止有技術能力的使用者。', 'wp-image-watermark' ); ?></p>
                    <table class="form-table">
                        <tr>
                            <th><?php esc_html_e( '停用右鍵選單', 'wp-image-watermark' ); ?></th>
                            <td><label><input type="checkbox" name="protect_right_click" value="1" <?php checked( $opts['protect_right_click'] ); ?>> <?php esc_html_e( '在圖片上停用右鍵選單', 'wp-image-watermark' ); ?></label></td>
                        </tr>
                        <tr>
                            <th><?php esc_html_e( '開發者工具偵測', 'wp-image-watermark' ); ?></th>
                            <td><label><input type="checkbox" name="protect_devtools" value="1" <?php checked( $opts['protect_devtools'] ); ?>> <?php esc_html_e( '偵測到開發者工具時隱藏圖片（實驗性）', 'wp-image-watermark' ); ?></label></td>
                        </tr>
                    </table>
                </div>

                <p class="submit">
                    <input type="submit" class="button-primary" value="<?php esc_attr_e( '儲存設定', 'wp-image-watermark' ); ?>">
                </p>
            </form>

            <div class="wpiwm-card">
                <h2><?php esc_html_e( '批次工具', 'wp-image-watermark' ); ?></h2>
                <p class="description"><?php esc_html_e( '對媒體庫中現有圖片進行批次操作。請先在上方儲存設定後再執行。', 'wp-image-watermark' ); ?></p>
                <div class="wpiwm-notice-box" style="background:#fff8e1;border-left:4px solid #f0ad4e;padding:10px 14px;border-radius:4px;margin:12px 0;font-size:13px;">
                    ⚠️ <?php esc_html_e( '注意：套用後圖片將被直接覆寫，無伺服器端備份。請確保本機已保留原始照片再執行批次套用。', 'wp-image-watermark' ); ?>
                </div>
                <div style="margin-top:12px;display:flex;gap:12px;flex-wrap:wrap;">
                    <button type="button" class="button button-primary" id="wpiwm-batch-all-apply"><?php esc_html_e( '批次套用浮水印（全部圖片）', 'wp-image-watermark' ); ?></button>
                    <button type="button" class="button" id="wpiwm-batch-all-remove" style="color:#c0392b;border-color:#c0392b;"><?php esc_html_e( '批次清除浮水印標記', 'wp-image-watermark' ); ?></button>
                </div>
                <div id="wpiwm-batch-progress" style="margin-top:12px;display:none;">
                    <div class="wpiwm-progress-bar"><div class="wpiwm-progress-fill" id="wpiwm-progress-fill"></div></div>
                    <p id="wpiwm-batch-status"></p>
                </div>
            </div>
        </div>
        <?php
    }
}
