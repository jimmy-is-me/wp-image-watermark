(function ($) {
    'use strict';

    /* ================================================================
     * Watermark image selector (Settings page)
     * ================================================================ */
    var mediaFrame;

    $('#wpiwm-select-image').on('click', function (e) {
        e.preventDefault();
        if (mediaFrame) { mediaFrame.open(); return; }
        mediaFrame = wp.media({
            title: '\u9078\u64c7\u6d6e\u6c34\u5370\u5716\u7247',
            button: { text: '\u4f7f\u7528\u6b64\u5716\u7247' },
            multiple: false,
            library: { type: 'image' },
        });
        mediaFrame.on('select', function () {
            var att   = mediaFrame.state().get('selection').first().toJSON();
            var thumb = att.sizes && att.sizes.thumbnail ? att.sizes.thumbnail.url : att.url;
            $('#watermark_image_id').val(att.id);
            $('#wpiwm-image-preview').html(
                '<img src="' + thumb + '" style="max-height:80px;border:1px solid #ddd;border-radius:4px;">'
            );
            $('#wpiwm-clear-image').show();
        });
        mediaFrame.open();
    });

    $('#wpiwm-clear-image').on('click', function () {
        $('#watermark_image_id').val('');
        $('#wpiwm-image-preview').html('');
        $(this).hide();
    });

    /* Range sliders */
    $('#watermark_image_opacity').on('input', function () {
        $('#watermark_image_opacity_val').text($(this).val());
    });
    $('#watermark_scale').on('input', function () {
        $('#watermark_scale_val').text($(this).val());
    });

    /* ================================================================
     * Media library – row action links
     * ================================================================ */
    $(document).on('click', '.wpiwm-row-apply', function (e) {
        e.preventDefault();
        e.stopPropagation();
        var $a    = $(this);
        var id    = $a.data('id');
        var nonce = $a.data('nonce');
        $a.text(WPIWM_Admin.applying).css('pointer-events', 'none');
        $.post(WPIWM_Admin.ajax_url, {
            action: 'wpiwm_apply_single',
            attachment_id: id,
            nonce: nonce,
        }, function (res) {
            if (res.success) {
                location.reload();
            } else {
                alert(res.data.message);
                $a.text('\u5957\u7528\u6d6e\u6c34\u5370').css('pointer-events', '');
            }
        });
    });

    $(document).on('click', '.wpiwm-row-remove', function (e) {
        e.preventDefault();
        e.stopPropagation();
        if (!confirm(WPIWM_Admin.confirm_remove)) return;
        var $a    = $(this);
        var id    = $a.data('id');
        var nonce = $a.data('nonce');
        $a.text(WPIWM_Admin.removing).css('pointer-events', 'none');
        $.post(WPIWM_Admin.ajax_url, {
            action: 'wpiwm_remove_single',
            attachment_id: id,
            nonce: nonce,
        }, function (res) {
            if (res.success) {
                location.reload();
            } else {
                alert(res.data.message);
                $a.text('\u6e05\u9664\u6a19\u8a18').css('pointer-events', '');
            }
        });
    });

    /* ================================================================
     * Attachment detail sidebar
     * ================================================================ */
    $(document).on('click', '.wpiwm-detail-apply', function (e) {
        e.preventDefault();
        e.stopPropagation();
        var $btn    = $(this);
        var id      = $btn.data('id');
        var nonce   = $btn.data('nonce');
        var $field  = $btn.closest('.wpiwm-field');
        var $status = $field.find('.wpiwm-status');

        $btn.prop('disabled', true).text(WPIWM_Admin.applying);

        $.post(WPIWM_Admin.ajax_url, {
            action: 'wpiwm_apply_single',
            attachment_id: id,
            nonce: nonce,
        }, function (res) {
            $btn.prop('disabled', false).text('\u5957\u7528\u6d6e\u6c34\u5370');
            if (res.success) {
                $status
                    .text('\u2714 \u5df2\u5957\u7528\u6d6e\u6c34\u5370')
                    .css({ color: '#2a9d8f', 'font-weight': '600' });
            } else {
                alert(res.data.message);
            }
        }).fail(function () {
            $btn.prop('disabled', false).text('\u5957\u7528\u6d6e\u6c34\u5370');
            alert('\u7db2\u8def\u932f\u8aa4\uff0c\u8acb\u91cd\u8a66\u3002');
        });
    });

})(jQuery);
