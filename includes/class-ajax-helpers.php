<?php
defined( 'ABSPATH' ) || exit;

add_action( 'wp_ajax_wpiwm_get_all_image_ids', function () {
    check_ajax_referer( 'wpiwm_nonce', 'nonce' );
    if ( ! current_user_can( 'upload_files' ) ) {
        wp_send_json_error();
    }
    $ids = get_posts( array(
        'post_type'      => 'attachment',
        'post_mime_type' => array( 'image/jpeg', 'image/png', 'image/webp' ),
        'posts_per_page' => -1,
        'fields'         => 'ids',
    ) );
    wp_send_json_success( array( 'ids' => $ids ) );
} );
