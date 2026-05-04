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
    }

    public function add_menu() {
        add_options_page(
            'WP Image Watermark',
            'Image Watermark',
            'manage_options',
            'wp-image-watermark',
            array( $this, 'render_page' )
        );
    }

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
        wp_localize_script( 'wpiwm-admin', 'WPIWM_Admin', array(
            'nonce'          => wp_create_nonce( 'wpiwm_nonce' ),
            'ajax_url'       => admin_url( 'admin-ajax.php' ),
            'applying'       => '套用中…',
            'removing'       => '清除中…',
            'confirm_remove' => '確定要清除浮水印標記嗎？（圖片本身不會還原）',
        ) );
        wp_enqueue_style(
            'wpiwm-admin',
            WPIWM_PLUGIN_URL . 'assets/css/admin.css',
            array(),
            WPIWM_VERSION
        );
    }

    public function save_settings() {
        check_admin_referer( 'wpiwm_settings_save' );
        if ( ! current_user_can( 'manage_options' ) ) wp_die( '權限不足' );

        $post = $_POST;
        WPIWM_Settings::update( array(
            'auto_watermark'          => ! empty( $post['auto_watermark'] ),
            'watermark_image_id'      => (int) ( $post['watermark_image_id'] ?? 0 ),
            'watermark_image_opacity' => min( 100, max( 0, (int) ( $post['watermark_image_opacity'] ?? 80 ) ) ),
            'watermark_position'      => sanitize_text_field( $post['watermark_position'] ?? 'bottom-right' ),
            'watermark_offset_x'      => max( 0, (int) ( $post['watermark_offset_x'] ?? 10 ) ),
            'watermark_offset_y'      => max( 0, (int) ( $post['watermark_offset_y'] ?? 10 ) ),
            'watermark_scale'         => min( 100, max( 1, (int) ( $post['watermark_scale'] ?? 20 ) ) ),
            'protect_right_click'     => ! empty( $post['protect_right_click'] ),
        ) );

        wp_redirect( admin_url( 'options-general.php?page=wp-image-watermark&saved=1' ) );
        exit;
    }

    public function render_page() {
        $s     = WPIWM_Settings::get();
        $saved = isset( $_GET['saved'] );
        ?>
        <div class="wrap">
        <h1>WP Image Watermark 設定</h1>

        <?php if ( $saved ) : ?>
            <div class="notice notice-success is-dismissible"><p>設定已儲存。</p></div>
        <?php endif; ?>

        <?php if ( empty( $s['watermark_image_id'] ) ) : ?>
            <div class="notice notice-warning"><p>請先選擇一張浮水印圖片，自動套用才會生效。</p></div>
        <?php endif; ?>

        <form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
            <?php wp_nonce_field( 'wpiwm_settings_save' ); ?>
            <input type="hidden" name="action" value="wpiwm_save">

            <table class="form-table" role="presentation">

                <!-- 浮水印圖片 -->
                <tr>
                    <th scope="row"><?php esc_html_e( '浮水印圖片', 'wp-image-watermark' ); ?></th>
                    <td>
                        <div id="wpiwm-image-preview" style="margin-bottom:8px;min-height:32px;">
                            <?php if ( $s['watermark_image_id'] ) : ?>
                                <img src="<?php echo esc_url( wp_get_attachment_thumb_url( $s['watermark_image_id'] ) ); ?>" style="max-height:80px;border:1px solid #ddd;border-radius:4px;">
                            <?php endif; ?>
                        </div>
                        <input type="hidden" name="watermark_image_id" id="watermark_image_id" value="<?php echo (int) $s['watermark_image_id']; ?>">
                        <button type="button" class="button" id="wpiwm-select-image">選擇媒體</button>
                        <button type="button" class="button" id="wpiwm-clear-image" <?php echo $s['watermark_image_id'] ? '' : 'style="display:none;"'; ?>>清除</button>
                        <p class="description" style="margin-top:6px;">建議使用背景透明的 PNG 圖片作為浮水印。此圖片本身不會被自動套用浮水印。</p>
                    </td>
                </tr>

                <!-- 圖片透明度 -->
                <tr>
                    <th scope="row"><?php esc_html_e( '透明度 (%)', 'wp-image-watermark' ); ?></th>
                    <td>
                        <input type="range" name="watermark_image_opacity" id="watermark_image_opacity"
                               value="<?php echo (int) $s['watermark_image_opacity']; ?>" min="0" max="100"
                               style="width:200px;vertical-align:middle;">
                        <span id="watermark_image_opacity_val"><?php echo (int) $s['watermark_image_opacity']; ?></span>%
                    </td>
                </tr>

                <!-- 圖片尺寸 -->
                <tr>
                    <th scope="row"><?php esc_html_e( '圖片尺寸 (%)', 'wp-image-watermark' ); ?></th>
                    <td>
                        <input type="range" name="watermark_scale" id="watermark_scale"
                               value="<?php echo (int) $s['watermark_scale']; ?>" min="1" max="100"
                               style="width:200px;vertical-align:middle;">
                        <span id="watermark_scale_val"><?php echo (int) $s['watermark_scale']; ?></span>%
                    </td>
                </tr>

                <!-- 位置 -->
                <tr>
                    <th scope="row"><?php esc_html_e( '浮水印位置', 'wp-image-watermark' ); ?></th>
                    <td>
                        <?php
                        $positions = array(
                            'top-left'    => '左上', 'top-center'    => '上中', 'top-right'    => '右上',
                            'middle-left' => '左中', 'center'        => '正中', 'middle-right' => '右中',
                            'bottom-left' => '左下', 'bottom-center' => '下中', 'bottom-right' => '右下',
                        );
                        echo '<table style="border-collapse:separate;border-spacing:4px;">';
                        $i = 0;
                        foreach ( $positions as $val => $label ) :
                            if ( $i % 3 === 0 ) echo '<tr>';
                        ?>
                            <td>
                                <label style="display:flex;align-items:center;justify-content:center;width:64px;height:40px;border:2px solid <?php echo $s['watermark_position']===$val?'#2a9d8f':'#c3c4c7'; ?>;border-radius:4px;cursor:pointer;background:<?php echo $s['watermark_position']===$val?'#e8f5f4':'#f6f7f7'; ?>;font-size:13px;">
                                    <input type="radio" name="watermark_position" value="<?php echo esc_attr($val); ?>" <?php checked($s['watermark_position'],$val); ?> style="display:none;">
                                    <?php echo esc_html($label); ?>
                                </label>
                            </td>
                        <?php
                            $i++;
                            if ( $i % 3 === 0 ) echo '</tr>';
                        endforeach;
                        echo '</table>';
                        ?>
                    </td>
                </tr>

                <!-- X/Y 偏移 -->
                <tr>
                    <th scope="row"><?php esc_html_e( 'X 偏移 (px)', 'wp-image-watermark' ); ?></th>
                    <td><input type="number" name="watermark_offset_x" value="<?php echo (int) $s['watermark_offset_x']; ?>" min="0" class="small-text"></td>
                </tr>
                <tr>
                    <th scope="row"><?php esc_html_e( 'Y 偏移 (px)', 'wp-image-watermark' ); ?></th>
                    <td><input type="number" name="watermark_offset_y" value="<?php echo (int) $s['watermark_offset_y']; ?>" min="0" class="small-text"></td>
                </tr>

                <!-- 自動套用 -->
                <tr>
                    <th scope="row"><?php esc_html_e( '自動套用', 'wp-image-watermark' ); ?></th>
                    <td>
                        <label>
                            <input type="checkbox" name="auto_watermark" value="1" <?php checked( $s['auto_watermark'] ); ?>>
                            上傳圖片時自動套用浮水印
                        </label>
                    </td>
                </tr>

                <!-- 停用右鍵 -->
                <tr>
                    <th scope="row"><?php esc_html_e( '停用右鍵', 'wp-image-watermark' ); ?></th>
                    <td>
                        <label>
                            <input type="checkbox" name="protect_right_click" value="1" <?php checked( $s['protect_right_click'] ); ?>>
                            停用前台頁面滑鼠右鍵選單
                        </label>
                    </td>
                </tr>

            </table>

            <?php submit_button( '儲存設定' ); ?>
        </form>
        </div>
        <?php
    }
}
