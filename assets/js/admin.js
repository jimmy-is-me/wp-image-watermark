(function ($) {
    'use strict';

    /* ====================================================================
     * Preview (Settings page only)
     * ==================================================================== */
    var mediaFrame;
    var previewWatermarkImage = null;
    var previewCanvas         = document.getElementById('wpiwm-preview-canvas');
    var previewCtx            = previewCanvas ? previewCanvas.getContext('2d') : null;

    function drawPreviewBackground() {
        if (!previewCtx || !previewCanvas) return;
        var W = previewCanvas.width;
        var H = previewCanvas.height;
        // plain white background
        previewCtx.clearRect(0, 0, W, H);
        previewCtx.fillStyle = '#ffffff';
        previewCtx.fillRect(0, 0, W, H);
    }

    function getPositionCoords(position, baseW, baseH, elementW, elementH, offsetX, offsetY) {
        switch (position) {
            case 'top-left':     return { x: offsetX, y: offsetY };
            case 'top-center':   return { x: (baseW - elementW) / 2, y: offsetY };
            case 'top-right':    return { x: baseW - elementW - offsetX, y: offsetY };
            case 'middle-left':  return { x: offsetX, y: (baseH - elementH) / 2 };
            case 'center':       return { x: (baseW - elementW) / 2, y: (baseH - elementH) / 2 };
            case 'middle-right': return { x: baseW - elementW - offsetX, y: (baseH - elementH) / 2 };
            case 'bottom-left':  return { x: offsetX, y: baseH - elementH - offsetY };
            case 'bottom-center':return { x: (baseW - elementW) / 2, y: baseH - elementH - offsetY };
            case 'bottom-right':
            default:             return { x: baseW - elementW - offsetX, y: baseH - elementH - offsetY };
        }
    }

    function renderPreview() {
        if (!previewCtx || !previewCanvas) return;
        drawPreviewBackground();

        var W        = previewCanvas.width;   // 960
        var H        = previewCanvas.height;  // 540
        var type     = $('input[name="watermark_type"]:checked').val() || 'text';
        var position = $('input[name="watermark_position"]:checked').val() || 'bottom-right';
        var offsetX  = parseInt($('input[name="watermark_offset_x"]').val(), 10) || 0;
        var offsetY  = parseInt($('input[name="watermark_offset_y"]').val(), 10) || 0;

        if (type === 'image') {
            if (previewWatermarkImage && previewWatermarkImage.complete && previewWatermarkImage.naturalWidth) {
                var scale   = parseInt($('input[name="watermark_scale"]').val(), 10) || 20;
                var opacity = (parseInt($('input[name="watermark_image_opacity"]').val(), 10) || 80) / 100;
                var drawW   = Math.max(24, W * scale / 100);
                var ratio   = previewWatermarkImage.naturalHeight / previewWatermarkImage.naturalWidth;
                var drawH   = drawW * ratio;
                var pos     = getPositionCoords(position, W, H, drawW, drawH, offsetX, offsetY);
                previewCtx.save();
                previewCtx.globalAlpha = opacity;
                previewCtx.drawImage(previewWatermarkImage, pos.x, pos.y, drawW, drawH);
                previewCtx.restore();
            } else {
                previewCtx.save();
                previewCtx.fillStyle = '#aaa';
                previewCtx.font      = '20px sans-serif';
                previewCtx.fillText('← 請先選擇浮水印圖片', 30, H / 2);
                previewCtx.restore();
            }
            return;
        }

        // --- text watermark ---
        var text    = $('input[name="watermark_text"]').val();
        if (!text) text = (typeof WPIWM_Admin !== 'undefined') ? WPIWM_Admin.preview_sample_text : '浮水印預覽';
        var fontSize = Math.max(12, parseInt($('input[name="watermark_font_size"]').val(), 10) || 36);
        var color    = $('input[name="watermark_font_color"]').val() || '#000000';
        var opacity  = (parseInt($('input[name="watermark_text_opacity"]').val(), 10) || 70) / 100;
        var scaled   = Math.max(12, Math.round(fontSize * W / 960));

        previewCtx.save();
        previewCtx.font        = 'bold ' + scaled + 'px Arial, sans-serif';
        var metrics = previewCtx.measureText(text);
        var textW   = metrics.width;
        var textH   = scaled;
        var pos     = getPositionCoords(position, W, H, textW, textH, offsetX, offsetY);
        previewCtx.globalAlpha  = opacity;
        previewCtx.fillStyle    = color;
        previewCtx.shadowColor  = 'rgba(0,0,0,0.35)';
        previewCtx.shadowBlur   = 5;
        previewCtx.fillText(text, pos.x, pos.y + textH);
        previewCtx.restore();
    }

    function syncRangeLabels() {
        $('.wpiwm-range').each(function () {
            $(this).next('span').text($(this).val() + '%');
        });
    }

    // Only run settings-page logic if canvas exists
    if (previewCanvas) {
        $('input[name="watermark_type"]').on('change', function () {
            var val = $(this).val();
            $('.wpiwm-type-settings').hide();
            $('#wpiwm-' + val + '-settings').show();
            $('.wpiwm-type-tab').removeClass('active');
            $(this).closest('.wpiwm-type-tab').addClass('active');
            renderPreview();
        });

        $('input[name="watermark_position"]').on('change', function () {
            $('.wpiwm-pos-cell').removeClass('active');
            $(this).closest('.wpiwm-pos-cell').addClass('active');
            renderPreview();
        });

        $(document).on(
            'input change',
            'input[name="watermark_text"], input[name="watermark_font_size"], ' +
            'input[name="watermark_font_color"], input[name="watermark_text_opacity"], ' +
            'input[name="watermark_scale"], input[name="watermark_image_opacity"], ' +
            'input[name="watermark_offset_x"], input[name="watermark_offset_y"]',
            function () { syncRangeLabels(); renderPreview(); }
        );

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
                var att    = mediaFrame.state().get('selection').first().toJSON();
                var thumb  = att.sizes && att.sizes.thumbnail ? att.sizes.thumbnail.url : att.url;
                $('#watermark_image_id').val(att.id);
                $('#wpiwm-image-preview').html('<img src="' + thumb + '" style="max-width:120px;max-height:80px;border:1px solid #ddd;border-radius:4px;">');
                $('#wpiwm-remove-image').show();
                previewWatermarkImage       = new Image();
                previewWatermarkImage.onload  = renderPreview;
                previewWatermarkImage.onerror = function () { previewWatermarkImage = null; renderPreview(); };
                previewWatermarkImage.src   = att.url;
            });
            mediaFrame.open();
        });

        $('#wpiwm-remove-image').on('click', function () {
            $('#watermark_image_id').val('');
            $('#wpiwm-image-preview').html('');
            previewWatermarkImage = null;
            $(this).hide();
            renderPreview();
        });

        $('#auto_watermark').on('change', function () {
            $('#wpiwm-auto-card').toggleClass('is-active', this.checked);
        });

        // Load existing watermark image
        var existingImgSrc = $('#wpiwm-image-preview img').attr('src');
        if (existingImgSrc) {
            previewWatermarkImage       = new Image();
            previewWatermarkImage.onload  = renderPreview;
            previewWatermarkImage.onerror = function () { previewWatermarkImage = null; renderPreview(); };
            previewWatermarkImage.src   = existingImgSrc;
        }

        syncRangeLabels();
        renderPreview();
    } // end if(previewCanvas)

    /* ====================================================================
     * Batch tools (Settings page)
     * ==================================================================== */
    $('#wpiwm-batch-all-apply').on('click', function () {
        if (!confirm(WPIWM_Admin.confirm_batch)) return;
        runBatch('apply');
    });
    $('#wpiwm-batch-all-remove').on('click', function () {
        if (!confirm('確定要清除所有浮水印標記嗎？')) return;
        runBatch('remove');
    });
    function runBatch(mode) {
        $('#wpiwm-batch-progress').show();
        $('#wpiwm-batch-status').text('正在取得圖片清單…');
        $('#wpiwm-progress-fill').css('width', '0%');
        $.post(WPIWM_Admin.ajax_url, { action: 'wpiwm_get_all_image_ids', nonce: WPIWM_Admin.nonce },
            function (res) {
                if (!res.success || !res.data.ids.length) { $('#wpiwm-batch-status').text('找不到圖片。'); return; }
                var ids = res.data.ids, total = ids.length, chunk = 10, processed = 0, success = 0;
                function processChunk(offset) {
                    var batch = ids.slice(offset, offset + chunk);
                    if (!batch.length) { $('#wpiwm-batch-status').text('完成！成功 ' + success + ' / ' + total + ' 張。'); return; }
                    $.post(WPIWM_Admin.ajax_url,
                        { action: mode === 'apply' ? 'wpiwm_batch_apply' : 'wpiwm_batch_remove', ids: batch, nonce: WPIWM_Admin.nonce },
                        function (r) {
                            processed += batch.length;
                            if (r.success) success += r.data.success;
                            $('#wpiwm-progress-fill').css('width', Math.round(processed / total * 100) + '%');
                            $('#wpiwm-batch-status').text('處理中… ' + processed + ' / ' + total);
                            processChunk(offset + chunk);
                        });
                }
                processChunk(0);
            });
    }

    /* ====================================================================
     * Media library row actions  — loaded on ALL admin pages
     * ==================================================================== */
    $(document).on('click', '.wpiwm-apply', function (e) {
        e.preventDefault();
        e.stopPropagation(); // prevent bubbling to WP upload handlers
        var $link = $(this);
        var id    = $link.data('id');
        var nonce = $link.data('nonce');
        $link.text(WPIWM_Admin.applying).css('pointer-events', 'none');
        $.post(WPIWM_Admin.ajax_url,
            { action: 'wpiwm_apply_single', attachment_id: id, nonce: nonce },
            function (res) {
                alert(res.success ? res.data.message : res.data.message);
                if (res.success) { location.reload(); }
                else { $link.text('套用浮水印').css('pointer-events', ''); }
            });
    });

    $(document).on('click', '.wpiwm-remove', function (e) {
        e.preventDefault();
        e.stopPropagation();
        if (!confirm(WPIWM_Admin.confirm_remove)) return;
        var $link = $(this);
        var id    = $link.data('id');
        var nonce = $link.data('nonce');
        $link.text(WPIWM_Admin.removing).css('pointer-events', 'none');
        $.post(WPIWM_Admin.ajax_url,
            { action: 'wpiwm_remove_single', attachment_id: id, nonce: nonce },
            function (res) {
                alert(res.success ? res.data.message : res.data.message);
                if (res.success) { location.reload(); }
                else { $link.text('清除標記').css('pointer-events', ''); }
            });
    });

})(jQuery);
