<?php
defined( 'ABSPATH' ) || exit;

class WPIWM_Media_Handler {

    private static $instance = null;

    public static function get_instance() {
        if ( null === self::$instance ) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    private function __construct() {
        add_filter( 'wp_handle_upload', array( $this, 'on_upload' ) );
        add_action( 'wp_ajax_wpiwm_apply_single',  array( $this, 'ajax_apply_single' ) );
        add_action( 'wp_ajax_wpiwm_remove_single', array( $this, 'ajax_remove_single' ) );
        add_action( 'wp_ajax_wpiwm_batch_apply',   array( $this, 'ajax_batch_apply' ) );
        add_action( 'wp_ajax_wpiwm_batch_remove',  array( $this, 'ajax_batch_remove' ) );
        add_filter( 'manage_media_columns',        array( $this, 'add_column' ) );
        add_action( 'manage_media_custom_column',  array( $this, 'render_column' ), 10, 2 );
        add_filter( 'media_row_actions',           array( $this, 'row_actions' ), 10, 2 );
    }

    /* ------------------------------------------------------------------ */
    /*  Auto watermark on upload                                           */
    /* ------------------------------------------------------------------ */

    public function on_upload( $upload ) {
        if ( ! WPIWM_Settings::get( 'auto_watermark' ) ) {
            return $upload;
        }
        $file = $upload['file'] ?? '';
        if ( $file ) {
            WPIWM_Watermark_Engine::apply( $file );
        }
        return $upload;
    }

    /* ------------------------------------------------------------------ */
    /*  AJAX handlers                                                      */
    /* ------------------------------------------------------------------ */

    public function ajax_apply_single() {
        check_ajax_referer( 'wpiwm_nonce', 'nonce' );
        if ( ! current_user_can( 'upload_files' ) ) {
            wp_send_json_error( array( 'message' => __( '權限不足', 'wp-image-watermark' ) ) );
        }
        $id = isset( $_POST['attachment_id'] ) ? (int) $_POST['attachment_id'] : 0;
        if ( ! $id ) {
            wp_send_json_error( array( 'message' => __( '無效的 ID', 'wp-image-watermark' ) ) );
        }
        $result = $this->apply_to_attachment( $id );
        if ( $result ) {
            wp_send_json_success( array( 'message' => __( '浮水印已套用', 'wp-image-watermark' ) ) );
        } else {
            wp_send_json_error( array( 'message' => __( '套用失敗，請確認圖片格式', 'wp-image-watermark' ) ) );
        }
    }

    public function ajax_remove_single() {
        check_ajax_referer( 'wpiwm_nonce', 'nonce' );
        if ( ! current_user_can( 'upload_files' ) ) {
            wp_send_json_error( array( 'message' => __( '權限不足', 'wp-image-watermark' ) ) );
        }
        $id = isset( $_POST['attachment_id'] ) ? (int) $_POST['attachment_id'] : 0;
        if ( ! $id ) {
            wp_send_json_error( array( 'message' => __( '無效的 ID', 'wp-image-watermark' ) ) );
        }
        // No backup: simply clear the watermarked flag so the UI reflects the change.
        // The user is responsible for re-uploading the original from their local copy.
        delete_post_meta( $id, '_wpiwm_watermarked' );
        wp_send_json_success( array( 'message' => __( '浮水印標記已清除。如需還原圖片，請重新上傳原始檔案。', 'wp-image-watermark' ) ) );
    }

    public function ajax_batch_apply() {
        check_ajax_referer( 'wpiwm_nonce', 'nonce' );
        if ( ! current_user_can( 'upload_files' ) ) wp_send_json_error();
        $ids     = isset( $_POST['ids'] ) ? array_map( 'intval', (array) $_POST['ids'] ) : array();
        $success = 0;
        $failed  = 0;
        foreach ( $ids as $id ) {
            if ( $this->apply_to_attachment( $id ) ) {
                $success++;
            } else {
                $failed++;
            }
        }
        wp_send_json_success( array(
            'success' => $success,
            'failed'  => $failed,
            'message' => sprintf( __( '成功：%d，失敗：%d', 'wp-image-watermark' ), $success, $failed ),
        ) );
    }

    public function ajax_batch_remove() {
        check_ajax_referer( 'wpiwm_nonce', 'nonce' );
        if ( ! current_user_can( 'upload_files' ) ) wp_send_json_error();
        $ids     = isset( $_POST['ids'] ) ? array_map( 'intval', (array) $_POST['ids'] ) : array();
        $success = 0;
        foreach ( $ids as $id ) {
            delete_post_meta( $id, '_wpiwm_watermarked' );
            $success++;
        }
        wp_send_json_success( array(
            'success' => $success,
            'message' => sprintf( __( '已清除 %d 張圖片的浮水印標記', 'wp-image-watermark' ), $success ),
        ) );
    }

    /* ------------------------------------------------------------------ */
    /*  Core apply                                                         */
    /* ------------------------------------------------------------------ */

    public function apply_to_attachment( $attachment_id ) {
        $file = get_attached_file( $attachment_id );
        if ( ! $file || ! file_exists( $file ) ) {
            return false;
        }
        $result = WPIWM_Watermark_Engine::apply( $file );
        if ( $result ) {
            update_post_meta( $attachment_id, '_wpiwm_watermarked', 1 );
            wp_update_attachment_metadata(
                $attachment_id,
                wp_generate_attachment_metadata( $attachment_id, $file )
            );
        }
        return $result;
    }

    /* ------------------------------------------------------------------ */
    /*  Media library UI                                                   */
    /* ------------------------------------------------------------------ */

    public function add_column( $columns ) {
        $columns['wpiwm_status'] = __( '浮水印', 'wp-image-watermark' );
        return $columns;
    }

    public function render_column( $column_name, $post_id ) {
        if ( $column_name !== 'wpiwm_status' ) return;
        $has_wm = get_post_meta( $post_id, '_wpiwm_watermarked', true );
        if ( $has_wm ) {
            echo '<span style="color:#2a9d8f;font-weight:600;">&#10004; ' . esc_html__( '已套用', 'wp-image-watermark' ) . '</span>';
        } else {
            echo '<span style="color:#999;">&mdash; ' . esc_html__( '未套用', 'wp-image-watermark' ) . '</span>';
        }
    }

    public function row_actions( $actions, $post ) {
        if ( $post->post_type !== 'attachment' ) return $actions;
        $nonce  = wp_create_nonce( 'wpiwm_nonce' );
        $has_wm = get_post_meta( $post->ID, '_wpiwm_watermarked', true );

        $actions['wpiwm_apply'] = sprintf(
            '<a href="#" class="wpiwm-apply" data-id="%d" data-nonce="%s">%s</a>',
            $post->ID, $nonce, esc_html__( '套用浮水印', 'wp-image-watermark' )
        );
        if ( $has_wm ) {
            $actions['wpiwm_remove'] = sprintf(
                '<a href="#" class="wpiwm-remove" data-id="%d" data-nonce="%s" style="color:#c0392b;">%s</a>',
                $post->ID, $nonce, esc_html__( '清除標記', 'wp-image-watermark' )
            );
        }
        return $actions;
    }
}
