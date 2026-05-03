(function ($) {
    'use strict';

    // ── Type tabs toggle ──
    $('input[name="watermark_type"]').on('change', function () {
        var val = $(this).val();
        $('.wpiwm-type-settings').hide();
        $('#wpiwm-' + val + '-settings').show();
        $('.wpiwm-type-tab').removeClass('active');
        $(this).closest('.wpiwm-type-tab').addClass('active');
    });

    // ── Position grid ──
    $('input[name="watermark_position"]').on('change', function () {
        $('.wpiwm-pos-cell').removeClass('active');
        $(this).closest('.wpiwm-pos-cell').addClass('active');
    });

    // ── Auto watermark toggle card ──
    $('#auto_watermark').on('change', function () {
        $('#wpiwm-auto-card').toggleClass('is-active', this.checked);
    });

    // ── Media picker ──
    var mediaFrame;
    $('#wpiwm-select-image').on('click', function (e) {
        e.preventDefault();
        if (mediaFrame) { mediaFrame.open(); return; }
        mediaFrame = wp.media({
            title: '選擇浮水印圖片',
            button: { text: '使用此圖片' },
            multiple: false,
            library: { type: 'image' }
        });
        mediaFrame.on('select', function () {
            var attachment = mediaFrame.state().get('selection').first().toJSON();
            $('#watermark_image_id').val(attachment.id);
            var preview = attachment.sizes && attachment.sizes.thumbnail
                ? attachment.sizes.thumbnail.url
                : attachment.url;
            $('#wpiwm-image-preview').html('<img src="' + preview + '" style="max-width:120px;max-height:80px;border:1px solid #ddd;border-radius:4px;">');
            $('#wpiwm-remove-image').show();
        });
        mediaFrame.open();
    });

    $('#wpiwm-remove-image').on('click', function () {
        $('#watermark_image_id').val('');
        $('#wpiwm-image-preview').html('');
        $(this).hide();
    });

    // ── Single row action: apply ──
    $(document).on('click', '.wpiwm-apply', function (e) {
        e.preventDefault();
        var $link = $(this);
        var id    = $link.data('id');
        var nonce = $link.data('nonce');
        $link.text(WPIWM_Admin.applying);
        $.post(WPIWM_Admin.ajax_url, {
            action: 'wpiwm_apply_single',
            attachment_id: id,
            nonce: nonce
        }, function (res) {
            if (res.success) {
                alert(res.data.message);
                location.reload();
            } else {
                alert(res.data.message);
                $link.text('套用浮水印');
            }
        });
    });

    // ── Single row action: remove ──
    $(document).on('click', '.wpiwm-remove', function (e) {
        e.preventDefault();
        if (!confirm(WPIWM_Admin.confirm_remove)) return;
        var $link = $(this);
        var id    = $link.data('id');
        var nonce = $link.data('nonce');
        $link.text(WPIWM_Admin.removing);
        $.post(WPIWM_Admin.ajax_url, {
            action: 'wpiwm_remove_single',
            attachment_id: id,
            nonce: nonce
        }, function (res) {
            if (res.success) {
                alert(res.data.message);
                location.reload();
            } else {
                alert(res.data.message);
                $link.text('移除浮水印');
            }
        });
    });

    // ── Batch apply all ──
    $('#wpiwm-batch-all-apply').on('click', function () {
        if (!confirm(WPIWM_Admin.confirm_batch)) return;
        runBatch('apply');
    });

    // ── Batch remove all ──
    $('#wpiwm-batch-all-remove').on('click', function () {
        if (!confirm('確定要還原所有有備份的圖片嗎？')) return;
        runBatch('remove');
    });

    function runBatch(mode) {
        $('#wpiwm-batch-progress').show();
        $('#wpiwm-batch-status').text('正在取得圖片清單…');
        $('#wpiwm-progress-fill').css('width', '0%');

        $.post(WPIWM_Admin.ajax_url, {
            action: 'wpiwm_get_all_image_ids',
            nonce: WPIWM_Admin.nonce
        }, function (res) {
            if (!res.success || !res.data.ids.length) {
                $('#wpiwm-batch-status').text('找不到圖片。');
                return;
            }
            var ids       = res.data.ids;
            var total     = ids.length;
            var chunk     = 10;
            var processed = 0;
            var success   = 0;

            function processChunk(offset) {
                var batch = ids.slice(offset, offset + chunk);
                if (!batch.length) {
                    $('#wpiwm-batch-status').text('完成！成功處理 ' + success + ' / ' + total + ' 張圖片。');
                    return;
                }
                var action = mode === 'apply' ? 'wpiwm_batch_apply' : 'wpiwm_batch_remove';
                $.post(WPIWM_Admin.ajax_url, {
                    action: action,
                    ids: batch,
                    nonce: WPIWM_Admin.nonce
                }, function (r) {
                    processed += batch.length;
                    if (r.success) success += r.data.success;
                    var pct = Math.round(processed / total * 100);
                    $('#wpiwm-progress-fill').css('width', pct + '%');
                    $('#wpiwm-batch-status').text('處理中… ' + processed + ' / ' + total);
                    processChunk(offset + chunk);
                });
            }
            processChunk(0);
        });
    }

})(jQuery);
