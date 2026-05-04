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
        // Auto watermark on upload
        // Priority 999 ensures metadata is already saved before we process
        add_action( 'add_attachment',              array( $this, 'maybe_auto_watermark_attachment' ), 999 );
        add_action( 'wp_ajax_wpiwm_apply_single',  array( $this, 'ajax_apply_single' ) );
        add_action( 'wp_ajax_wpiwm_remove_single', array( $this, 'ajax_remove_single' ) );
        add_action( 'wp_ajax_wpiwm_batch_apply',   array( $this, 'ajax_batch_apply' ) );
        add_action( 'wp_ajax_wpiwm_batch_remove',  array( $this, 'ajax_batch_remove' ) );
        add_filter( 'manage_media_columns',        array( $this, 'add_column' ) );
        add_action( 'manage_media_custom_column',  array( $this, 'render_column' ), 10, 2 );
        add_filter( 'media_row_actions',           array( $this, 'row_actions' ), 10, 2 );
        // Attachment detail sidebar (edit-attachment screen & media modal)
        add_filter( 'attachment_fields_to_edit',   array( $this, 'attachment_fields' ), 10, 2 );
    }

    /* ------------------------------------------------------------------ */
    /*  Auto watermark on upload                                           */
    /* ------------------------------------------------------------------ */

    public function maybe_auto_watermark_attachment( $attachment_id ) {
        $settings = WPIWM_Settings::get();

        // Cast to bool explicitly — option may be stored as '1' or ''
        if ( empty( $settings['auto_watermark'] ) ) {
            return;
        }

        if ( 'attachment' !== get_post_type( $attachment_id ) ) {
            return;
        }

        $mime_type = get_post_mime_type( $attachment_id );
        if ( ! $mime_type || 0 !== strpos( (string) $mime_type, 'image/' ) ) {
            return;
        }

        // Validate settings before attempting
        if ( $settings['watermark_type'] === 'text' && empty( trim( (string) $settings['watermark_text'] ) ) ) {
            error_log( 'WPIWM auto: skipped – watermark_text is empty' );
            return;
        }
        if ( $settings['watermark_type'] === 'image' && empty( $settings['watermark_image_id'] ) ) {
            error_log( 'WPIWM auto: skipped – watermark_image_id not set' );
            return;
        }

        $this->apply_to_attachment( $attachment_id );
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
            $has_wm = (bool) get_post_meta( $id, '_wpiwm_watermarked', true );
            wp_send_json_success( array(
                'message'        => __( '浮水印已套用', 'wp-image-watermark' ),
                'watermarked'    => $has_wm,
                'attachment_id'  => $id,
            ) );
        } else {
            wp_send_json_error( array( 'message' => __( '套用失敗，請確認圖片格式與浮水印設定是否正確', 'wp-image-watermark' ) ) );
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
        delete_post_meta( $id, '_wpiwm_watermarked' );
        wp_send_json_success( array(
            'message'       => __( '浮水印標記已清除。如需還原圖片，請重新上傳原始檔案。', 'wp-image-watermark' ),
            'watermarked'   => false,
            'attachment_id' => $id,
        ) );
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
            error_log( 'WPIWM apply_to_attachment: file not found for id=' . $attachment_id );
            return false;
        }

        $settings = WPIWM_Settings::get();
        if ( $settings['watermark_type'] === 'text' && empty( trim( (string) $settings['watermark_text'] ) ) ) {
            error_log( 'WPIWM apply_to_attachment: watermark_text is empty, skipping' );
            return false;
        }
        if ( $settings['watermark_type'] === 'image' && empty( $settings['watermark_image_id'] ) ) {
            error_log( 'WPIWM apply_to_attachment: watermark_image_id not set, skipping' );
            return false;
        }

        $result = WPIWM_Watermark_Engine::apply( $file );
        if ( $result ) {
            update_post_meta( $attachment_id, '_wpiwm_watermarked', 1 );
            clean_attachment_cache( $attachment_id );
            wp_cache_delete( $attachment_id, 'posts' );
        }
        return $result;
    }

    /* ------------------------------------------------------------------ */
    /*  Attachment detail sidebar fields                                   */
    /* ------------------------------------------------------------------ */

    /**
     * Add a watermark action row into the attachment fields panel.
     * This appears both in the media modal (Attachment Details)
     * and on the standalone edit-attachment admin screen.
     */
    public function attachment_fields( $form_fields, $post ) {
        if ( strpos( (string) get_post_mime_type( $post->ID ), 'image/' ) !== 0 ) {
            return $form_fields;
        }

        $has_wm = (bool) get_post_meta( $post->ID, '_wpiwm_watermarked', true );
        $nonce  = wp_create_nonce( 'wpiwm_nonce' );

        ob_start();
        ?>
        <div class="wpiwm-detail-action" style="display:flex;align-items:center;gap:10px;flex-wrap:wrap;">
            <button type="button"
                class="button wpiwm-detail-apply"
                data-id="<?php echo esc_attr( $post->ID ); ?>"
                data-nonce="<?php echo esc_attr( $nonce ); ?>"
                style="background:#2a9d8f;color:#fff;border-color:#1a6b60;">
                <?php esc_html_e( '套用浮水印', 'wp-image-watermark' ); ?>
            </button>
            <button type="button"
                class="button wpiwm-detail-remove"
                data-id="<?php echo esc_attr( $post->ID ); ?>"
                data-nonce="<?php echo esc_attr( $nonce ); ?>"
                <?php echo ! $has_wm ? 'style="display:none;"' : ''; ?>>
                <?php esc_html_e( '清除浮水印標記', 'wp-image-watermark' ); ?>
            </button>
            <span class="wpiwm-detail-status" style="font-size:13px;color:<?php echo $has_wm ? '#2a9d8f' : '#999'; ?>;font-weight:<?php echo $has_wm ? '600' : '400'; ?>;">
                <?php echo $has_wm ? esc_html__( '✔ 已套用浮水印', 'wp-image-watermark' ) : esc_html__( '未套用浮水印', 'wp-image-watermark' ); ?>
            </span>
        </div>
        <script>
        (function($){
            // Runs once per attachment detail panel load
            $(document).off('click.wpiwm_detail').on('click.wpiwm_detail', '.wpiwm-detail-apply', function(e){
                e.preventDefault();
                var $btn    = $(this);
                var id      = $btn.data('id');
                var nonce   = $btn.data('nonce');
                var $panel  = $btn.closest('.wpiwm-detail-action');
                var $status = $panel.find('.wpiwm-detail-status');
                var $remove = $panel.find('.wpiwm-detail-remove');
                $btn.prop('disabled', true).text('<?php echo esc_js( __( '套用中…', 'wp-image-watermark' ) ); ?>');
                $.post(ajaxurl, {action:'wpiwm_apply_single', attachment_id:id, nonce:nonce}, function(res){
                    $btn.prop('disabled', false).text('<?php echo esc_js( __( '套用浮水印', 'wp-image-watermark' ) ); ?>');
                    if(res.success){
                        $status.text('<?php echo esc_js( __( '✔ 已套用浮水印', 'wp-image-watermark' ) ); ?>').css({color:'#2a9d8f','font-weight':'600'});
                        $remove.show();
                    } else {
                        alert(res.data.message);
                    }
                });
            });
            $(document).off('click.wpiwm_detail_rm').on('click.wpiwm_detail_rm', '.wpiwm-detail-remove', function(e){
                e.preventDefault();
                if(!confirm('<?php echo esc_js( __( '確定要清除此圖片的浮水印標記嗎？', 'wp-image-watermark' ) ); ?>')) return;
                var $btn    = $(this);
                var id      = $btn.data('id');
                var nonce   = $btn.data('nonce');
                var $panel  = $btn.closest('.wpiwm-detail-action');
                var $status = $panel.find('.wpiwm-detail-status');
                $btn.prop('disabled', true).text('<?php echo esc_js( __( '清除中…', 'wp-image-watermark' ) ); ?>');
                $.post(ajaxurl, {action:'wpiwm_remove_single', attachment_id:id, nonce:nonce}, function(res){
                    $btn.prop('disabled', false).hide();
                    if(res.success){
                        $status.text('<?php echo esc_js( __( '未套用浮水印', 'wp-image-watermark' ) ); ?>').css({color:'#999','font-weight':'400'});
                    } else {
                        alert(res.data.message);
                        $btn.show();
                    }
                });
            });
        })(jQuery);
        </script>
        <?php
        $html = ob_get_clean();

        $form_fields['wpiwm_action'] = array(
            'label' => __( '浮水印', 'wp-image-watermark' ),
            'input' => 'html',
            'html'  => $html,
        );

        return $form_fields;
    }

    /* ------------------------------------------------------------------ */
    /*  Media library list UI                                              */
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
        if ( strpos( (string) get_post_mime_type( $post->ID ), 'image/' ) !== 0 ) return $actions;
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
