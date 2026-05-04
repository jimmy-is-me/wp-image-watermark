<?php
defined( 'ABSPATH' ) || exit;

class WPIWM_Media_Handler {

    private static $instance = null;

    public static function get_instance() {
        if ( null === self::$instance ) self::$instance = new self();
        return self::$instance;
    }

    private function __construct() {
        /*
         * Use wp_generate_attachment_metadata (filter, priority 999) instead of
         * add_attachment so the original file is fully written before we touch it.
         */
        add_filter( 'wp_generate_attachment_metadata', array( $this, 'on_generate_metadata' ), 999, 2 );

        add_action( 'wp_ajax_wpiwm_apply_single',  array( $this, 'ajax_apply' ) );
        add_action( 'wp_ajax_wpiwm_remove_single', array( $this, 'ajax_remove' ) );
        add_filter( 'manage_media_columns',        array( $this, 'add_column' ) );
        add_action( 'manage_media_custom_column',  array( $this, 'render_column' ), 10, 2 );
        add_filter( 'media_row_actions',           array( $this, 'row_actions' ), 10, 2 );
        add_filter( 'attachment_fields_to_edit',   array( $this, 'attachment_fields' ), 10, 2 );
    }

    /* ----------------------------------------------------------------
     * Auto watermark – fires after all thumbnails are generated
     * ---------------------------------------------------------------- */

    public function on_generate_metadata( $metadata, $attachment_id ) {
        $settings = WPIWM_Settings::get();

        if ( empty( $settings['auto_watermark'] ) ) {
            return $metadata;
        }

        $mime = get_post_mime_type( $attachment_id );
        if ( ! $mime || strpos( $mime, 'image/' ) !== 0 ) {
            return $metadata;
        }

        // Skip the watermark image itself to prevent watermarking the watermark
        if ( ! empty( $settings['watermark_image_id'] ) &&
             (int) $settings['watermark_image_id'] === (int) $attachment_id ) {
            error_log( 'WPIWM auto: skipped – this is the watermark image itself (ID=' . $attachment_id . ')' );
            return $metadata;
        }

        // Prevent duplicate watermarking (e.g. thumbnail regeneration by other plugins)
        if ( get_post_meta( $attachment_id, '_wpiwm_watermarked', true ) ) {
            error_log( 'WPIWM auto: skipped – already watermarked (ID=' . $attachment_id . ')' );
            return $metadata;
        }

        if ( empty( $settings['watermark_image_id'] ) ) {
            error_log( 'WPIWM auto: skipped – watermark_image_id not set' );
            return $metadata;
        }

        $this->do_apply( $attachment_id );

        return $metadata;
    }

    /* ----------------------------------------------------------------
     * AJAX handlers
     * ---------------------------------------------------------------- */

    public function ajax_apply() {
        check_ajax_referer( 'wpiwm_nonce', 'nonce' );
        if ( ! current_user_can( 'upload_files' ) ) {
            wp_send_json_error( array( 'message' => '權限不足' ) );
        }
        $id = isset( $_POST['attachment_id'] ) ? (int) $_POST['attachment_id'] : 0;
        if ( ! $id ) wp_send_json_error( array( 'message' => '無效的附件 ID' ) );

        $settings = WPIWM_Settings::get();

        // Guard: do not watermark the watermark image
        if ( ! empty( $settings['watermark_image_id'] ) &&
             (int) $settings['watermark_image_id'] === $id ) {
            wp_send_json_error( array( 'message' => '不能對浮水印圖片本身套用浮水印' ) );
        }

        $ok = $this->do_apply( $id );
        if ( $ok ) {
            wp_send_json_success( array(
                'message'       => '浮水印已成功套用',
                'watermarked'   => true,
                'attachment_id' => $id,
            ) );
        } else {
            wp_send_json_error( array(
                'message' => '套用失敗。請確認：①已選擇浮水印圖片②圖片格式為 JPG/PNG/WebP ③伺服器有 GD 或 ImageMagick。詳細錯誤請查 WordPress debug.log。',
            ) );
        }
    }

    public function ajax_remove() {
        check_ajax_referer( 'wpiwm_nonce', 'nonce' );
        if ( ! current_user_can( 'upload_files' ) ) {
            wp_send_json_error( array( 'message' => '權限不足' ) );
        }
        $id = isset( $_POST['attachment_id'] ) ? (int) $_POST['attachment_id'] : 0;
        if ( ! $id ) wp_send_json_error( array( 'message' => '無效的附件 ID' ) );

        delete_post_meta( $id, '_wpiwm_watermarked' );
        wp_send_json_success( array(
            'message'       => '浮水印標記已清除（圖片本身不會還原）',
            'watermarked'   => false,
            'attachment_id' => $id,
        ) );
    }

    /* ----------------------------------------------------------------
     * Core apply logic
     * ---------------------------------------------------------------- */

    public function do_apply( $attachment_id ) {
        $file = get_attached_file( $attachment_id );
        if ( ! $file || ! file_exists( $file ) ) {
            error_log( 'WPIWM do_apply: file not found for attachment ' . $attachment_id );
            return false;
        }
        $ok = WPIWM_Watermark_Engine::apply( $file );
        if ( $ok ) {
            update_post_meta( $attachment_id, '_wpiwm_watermarked', 1 );
            clean_attachment_cache( $attachment_id );
            wp_cache_delete( $attachment_id, 'posts' );
        }
        return $ok;
    }

    /* ----------------------------------------------------------------
     * Media library – columns
     * ---------------------------------------------------------------- */

    public function add_column( $columns ) {
        $columns['wpiwm_status'] = '浮水印';
        return $columns;
    }

    public function render_column( $column, $post_id ) {
        if ( $column !== 'wpiwm_status' ) return;
        $settings = WPIWM_Settings::get();
        // Mark the watermark image itself as protected
        if ( ! empty( $settings['watermark_image_id'] ) &&
             (int) $settings['watermark_image_id'] === (int) $post_id ) {
            echo '<span style="color:#b0820d;font-weight:600;" title="浮水印圖片">★ 浮水印原圖</span>';
            return;
        }
        echo get_post_meta( $post_id, '_wpiwm_watermarked', true )
            ? '<span style="color:#2a9d8f;font-weight:600;">&#10004; 已套用</span>'
            : '<span style="color:#aaa;">— 未套用</span>';
    }

    public function row_actions( $actions, $post ) {
        if ( $post->post_type !== 'attachment' ) return $actions;
        if ( strpos( (string) get_post_mime_type( $post->ID ), 'image/' ) !== 0 ) return $actions;

        $settings = WPIWM_Settings::get();
        // Do not show apply action on the watermark image itself
        if ( ! empty( $settings['watermark_image_id'] ) &&
             (int) $settings['watermark_image_id'] === (int) $post->ID ) {
            return $actions;
        }

        $nonce = wp_create_nonce( 'wpiwm_nonce' );
        $actions['wpiwm_apply'] = sprintf(
            '<a href="#" class="wpiwm-row-apply" data-id="%d" data-nonce="%s">套用浮水印</a>',
            $post->ID, esc_attr( $nonce )
        );
        if ( get_post_meta( $post->ID, '_wpiwm_watermarked', true ) ) {
            $actions['wpiwm_remove'] = sprintf(
                '<a href="#" class="wpiwm-row-remove" data-id="%d" data-nonce="%s" style="color:#c0392b;">清除標記</a>',
                $post->ID, esc_attr( $nonce )
            );
        }
        return $actions;
    }

    /* ----------------------------------------------------------------
     * Attachment detail sidebar
     * ---------------------------------------------------------------- */

    public function attachment_fields( $form_fields, $post ) {
        if ( strpos( (string) get_post_mime_type( $post->ID ), 'image/' ) !== 0 ) return $form_fields;

        $settings = WPIWM_Settings::get();

        // Do not show apply button on the watermark image itself
        if ( ! empty( $settings['watermark_image_id'] ) &&
             (int) $settings['watermark_image_id'] === (int) $post->ID ) {
            $form_fields['wpiwm_action'] = array(
                'label' => '浮水印',
                'input' => 'html',
                'html'  => '<span style="color:#b0820d;font-weight:600;">★ 此圖片為浮水印圖片陳列</span>',
            );
            return $form_fields;
        }

        $has_wm = (bool) get_post_meta( $post->ID, '_wpiwm_watermarked', true );
        $nonce  = wp_create_nonce( 'wpiwm_nonce' );

        $status_html = $has_wm
            ? '<span class="wpiwm-status" style="color:#2a9d8f;font-weight:600;">&#10004; 已套用浮水印</span>'
            : '<span class="wpiwm-status" style="color:#aaa;">未套用浮水印</span>';

        $form_fields['wpiwm_action'] = array(
            'label' => '浮水印',
            'input' => 'html',
            'html'  => sprintf(
                '<div class="wpiwm-field" style="display:flex;align-items:center;gap:10px;flex-wrap:wrap;">'
                . '<button type="button" class="button wpiwm-detail-apply"'
                . ' data-id="%1$d" data-nonce="%2$s"'
                . ' style="background:#2a9d8f;color:#fff;border-color:#1a6b60;">套用浮水印</button>'
                . '%3$s'
                . '</div>',
                $post->ID,
                esc_attr( $nonce ),
                $status_html
            ),
        );
        return $form_fields;
    }
}
