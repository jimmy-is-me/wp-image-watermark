(function ($) {
    'use strict';

    var mediaFrame;
    var previewWatermarkImage = null;
    var previewBgImage = null;
    var previewCanvas = document.getElementById('wpiwm-preview-canvas');
    var previewCtx = previewCanvas ? previewCanvas.getContext('2d') : null;

    // Picsum sample seeds for random preview background
    var sampleSeeds = ['nature', 'landscape', 'mountains', 'city', 'forest', 'ocean'];
    var currentSeed = sampleSeeds[Math.floor(Math.random() * sampleSeeds.length)];

    function loadPreviewBg(callback) {
        if (previewBgImage && previewBgImage.complete && previewBgImage.naturalWidth) {
            if (callback) callback();
            return;
        }
        var img = new Image();
        img.crossOrigin = 'anonymous';
        img.onload = function () {
            previewBgImage = img;
            if (callback) callback();
        };
        img.onerror = function () {
            previewBgImage = null;
            if (callback) callback();
        };
        img.src = 'https://picsum.photos/seed/' + currentSeed + '/960/540';
    }

    function getPositionCoords(position, baseW, baseH, elementW, elementH, offsetX, offsetY) {
        switch (position) {
            case 'top-left':
                return { x: offsetX, y: offsetY };
            case 'top-center':
                return { x: (baseW - elementW) / 2, y: offsetY };
            case 'top-right':
                return { x: baseW - elementW - offsetX, y: offsetY };
            case 'middle-left':
                return { x: offsetX, y: (baseH - elementH) / 2 };
            case 'center':
                return { x: (baseW - elementW) / 2, y: (baseH - elementH) / 2 };
            case 'middle-right':
                return { x: baseW - elementW - offsetX, y: (baseH - elementH) / 2 };
            case 'bottom-left':
                return { x: offsetX, y: baseH - elementH - offsetY };
            case 'bottom-center':
                return { x: (baseW - elementW) / 2, y: baseH - elementH - offsetY };
            case 'bottom-right':
            default:
                return { x: baseW - elementW - offsetX, y: baseH - elementH - offsetY };
        }
    }

    function drawPreviewBackground() {
        if (!previewCtx || !previewCanvas) return;
        if (previewBgImage && previewBgImage.complete && previewBgImage.naturalWidth) {
            previewCtx.drawImage(previewBgImage, 0, 0, previewCanvas.width, previewCanvas.height);
        } else {
            var gradient = previewCtx.createLinearGradient(0, 0, previewCanvas.width, previewCanvas.height);
            gradient.addColorStop(0, '#c9d6df');
            gradient.addColorStop(1, '#52616b');
            previewCtx.fillStyle = gradient;
            previewCtx.fillRect(0, 0, previewCanvas.width, previewCanvas.height);
            previewCtx.fillStyle = 'rgba(255,255,255,0.08)';
            previewCtx.fillRect(48, 48, previewCanvas.width - 96, previewCanvas.height - 96);
            previewCtx.fillStyle = 'rgba(255,255,255,0.5)';
            previewCtx.font = '600 28px sans-serif';
            previewCtx.fillText('Preview Image', 72, 100);
            previewCtx.font = '400 20px sans-serif';
            previewCtx.fillText('示意圖載入中…', 72, 138);
        }
    }

    function renderPreview() {
        if (!previewCtx || !previewCanvas) return;

        drawPreviewBackground();

        var type = $('input[name="watermark_type"]:checked').val();
        var position = $('input[name="watermark_position"]:checked').val() || 'bottom-right';
        var offsetX = parseInt($('input[name="watermark_offset_x"]').val(), 10) || 0;
        var offsetY = parseInt($('input[name="watermark_offset_y"]').val(), 10) || 0;

        if (type === 'image' && previewWatermarkImage && previewWatermarkImage.complete) {
            var scale = parseInt($('input[name="watermark_scale"]').val(), 10) || 20;
            var opacity = (parseInt($('input[name="watermark_image_opacity"]').val(), 10) || 80) / 100;
            var drawW = Math.max(24, previewCanvas.width * scale / 100);
            var ratio = previewWatermarkImage.naturalWidth ? (previewWatermarkImage.naturalHeight / previewWatermarkImage.naturalWidth) : 1;
            var drawH = drawW * ratio;
            var pos = getPositionCoords(position, previewCanvas.width, previewCanvas.height, drawW, drawH, offsetX, offsetY);
            previewCtx.save();
            previewCtx.globalAlpha = opacity;
            previewCtx.drawImage(previewWatermarkImage, pos.x, pos.y, drawW, drawH);
            previewCtx.restore();
            return;
        }

        var text = $('input[name="watermark_text"]').val() || WPIWM_Admin.preview_sample_text;
        var fontSize = parseInt($('input[name="watermark_font_size"]').val(), 10) || 36;
        var color = $('input[name="watermark_font_color"]').val() || '#ffffff';
        var opacityText = (parseInt($('input[name="watermark_text_opacity"]').val(), 10) || 70) / 100;
        fontSize = Math.max(12, Math.round(fontSize * 1.4));

        previewCtx.save();
        previewCtx.font = '700 ' + fontSize + 'px sans-serif';
        var metrics = previewCtx.measureText(text);
        var textW = metrics.width;
        var textH = fontSize;
        var posText = getPositionCoords(position, previewCanvas.width, previewCanvas.height, textW, textH, offsetX, offsetY);
        previewCtx.globalAlpha = opacityText;
        previewCtx.fillStyle = color;
        previewCtx.shadowColor = 'rgba(0,0,0,0.22)';
        previewCtx.shadowBlur = 8;
        previewCtx.fillText(text, posText.x, posText.y + textH);
        previewCtx.restore();
    }

    function syncRangeLabels() {
        $('.wpiwm-range').each(function () {
            var $range = $(this);
            $range.next('span').text($range.val() + '%');
        });
    }

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

    $('#auto_watermark').on('change', function () {
        $('#wpiwm-auto-card').toggleClass('is-active', this.checked);
    });

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
            var preview = attachment.sizes && attachment.sizes.thumbnail ? attachment.sizes.thumbnail.url : attachment.url;
            $('#wpiwm-image-preview').html('<img src="' + preview + '" style="max-width:120px;max-height:80px;border:1px solid #ddd;border-radius:4px;">');
            $('#wpiwm-remove-image').show();

            previewWatermarkImage = new Image();
            previewWatermarkImage.crossOrigin = 'anonymous';
            previewWatermarkImage.onload = renderPreview;
            previewWatermarkImage.src = attachment.url;
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

    $(document).on('input change', 'input[name="watermark_text"], input[name="watermark_font_size"], input[name="watermark_font_color"], input[name="watermark_text_opacity"], input[name="watermark_scale"], input[name="watermark_image_opacity"], input[name="watermark_offset_x"], input[name="watermark_offset_y"]', function () {
        syncRangeLabels();
        renderPreview();
    });

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

    $('#wpiwm-batch-all-apply').on('click', function () {
        if (!confirm(WPIWM_Admin.confirm_batch)) return;
        runBatch('apply');
    });

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

    var existingPreviewImg = $('#wpiwm-image-preview img').attr('src');
    if (existingPreviewImg) {
        previewWatermarkImage = new Image();
        previewWatermarkImage.crossOrigin = 'anonymous';
        previewWatermarkImage.onload = renderPreview;
        previewWatermarkImage.src = existingPreviewImg;
    }

    syncRangeLabels();
    // Load background sample image then render preview
    loadPreviewBg(renderPreview);

})(jQuery);
