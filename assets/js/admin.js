/* global wp, WPIWM_Admin, jQuery */
jQuery(function ($) {
    'use strict';

    /* ================================================================
     * Watermark image selector
     * ================================================================ */
    var mediaFrame = null;

    $(document).on('click', '#wpiwm-select-image', function (e) {
        e.preventDefault();
        if (mediaFrame) {
            mediaFrame.open();
            return;
        }
        mediaFrame = wp.media({
            title: '\u9078\u64c7\u6d6e\u6c34\u5370\u5716\u7247',
            button: { text: '\u4f7f\u7528\u6b64\u5716\u7247' },
            multiple: false,
            library: { type: 'image' },
        });
        mediaFrame.on('select', function () {
            var att   = mediaFrame.state().get('selection').first().toJSON();
            var thumb = att.sizes && att.sizes.thumbnail ? att.sizes.thumbnail.url : att.url;
            var full  = att.url;
            $('#watermark_image_id').val(att.id);
            $('#wpiwm-image-preview').html(
                '<img src="' + thumb + '" style="max-height:80px;border:1px solid #ddd;border-radius:4px;">'
            );
            $('#wpiwm-clear-image').show();
            // update preview with new watermark
            renderPreview(full);
        });
        mediaFrame.open();
    });

    $(document).on('click', '#wpiwm-clear-image', function (e) {
        e.preventDefault();
        $('#watermark_image_id').val('');
        $('#wpiwm-image-preview').html('');
        $(this).hide();
        renderPreview(null);
    });

    /* ================================================================
     * Range sliders
     * ================================================================ */
    $(document).on('input', '#watermark_image_opacity', function () {
        $('#watermark_image_opacity_val').text($(this).val());
        schedulePreview();
    });
    $(document).on('input', '#watermark_scale', function () {
        $('#watermark_scale_val').text($(this).val());
        schedulePreview();
    });

    /* ================================================================
     * Position grid
     * ================================================================ */
    $(document).on('change', '#wpiwm-pos-grid input[type="radio"]', function () {
        $('#wpiwm-pos-grid .wpiwm-pos-cell').removeClass('active');
        $(this).closest('.wpiwm-pos-cell').addClass('active');
        schedulePreview();
    });

    /* ================================================================
     * Canvas Preview
     * ================================================================ */
    var previewTimer = null;

    function schedulePreview() {
        clearTimeout(previewTimer);
        previewTimer = setTimeout(function () {
            // use current watermark url if set
            var $img = $('#wpiwm-image-preview img');
            renderPreview($img.length ? $img.attr('src') : null);
        }, 300);
    }

    function renderPreview(wmUrl) {
        var canvas = document.getElementById('wpiwm-preview-canvas');
        if (!canvas) return;
        var ctx = canvas.getContext('2d');

        var CW = canvas.width;
        var CH = canvas.height;

        // draw white background
        ctx.clearRect(0, 0, CW, CH);
        ctx.fillStyle = '#ffffff';
        ctx.fillRect(0, 0, CW, CH);

        // draw checkerboard to show it's a blank canvas
        var tileSize = 20;
        for (var row = 0; row < CH / tileSize; row++) {
            for (var col = 0; col < CW / tileSize; col++) {
                ctx.fillStyle = (row + col) % 2 === 0 ? '#f0f0f0' : '#ffffff';
                ctx.fillRect(col * tileSize, row * tileSize, tileSize, tileSize);
            }
        }

        if (!wmUrl) return;

        var wmImg = new Image();
        wmImg.crossOrigin = 'anonymous';
        wmImg.onload = function () {
            var opacity  = parseInt($('#watermark_image_opacity').val(), 10) / 100;
            var scale    = parseInt($('#watermark_scale').val(), 10) / 100;
            var position = $('input[name="watermark_position"]:checked').val() || 'bottom-right';
            var offsetX  = parseInt($('#watermark_offset_x').val(), 10) || 10;
            var offsetY  = parseInt($('#watermark_offset_y').val(), 10) || 10;

            var wmW = Math.round(CW * scale);
            var wmH = Math.round(wmImg.height * (wmW / wmImg.width));

            var x, y;
            if      (position === 'top-left')      { x = offsetX;            y = offsetY; }
            else if (position === 'top-center')    { x = (CW - wmW) / 2;    y = offsetY; }
            else if (position === 'top-right')     { x = CW - wmW - offsetX; y = offsetY; }
            else if (position === 'middle-left')   { x = offsetX;            y = (CH - wmH) / 2; }
            else if (position === 'center')        { x = (CW - wmW) / 2;    y = (CH - wmH) / 2; }
            else if (position === 'middle-right')  { x = CW - wmW - offsetX; y = (CH - wmH) / 2; }
            else if (position === 'bottom-left')   { x = offsetX;            y = CH - wmH - offsetY; }
            else if (position === 'bottom-center') { x = (CW - wmW) / 2;    y = CH - wmH - offsetY; }
            else                                   { x = CW - wmW - offsetX; y = CH - wmH - offsetY; } // bottom-right

            ctx.globalAlpha = opacity;
            ctx.drawImage(wmImg, x, y, wmW, wmH);
            ctx.globalAlpha = 1;
        };
        wmImg.onerror = function () {
            // crossOrigin blocked – try without
            var wmImg2 = new Image();
            wmImg2.onload = function () { wmImg.onload.call(wmImg2); };
            wmImg2.src = wmUrl;
        };
        wmImg.src = wmUrl;
    }

    // initial render on page load
    (function () {
        var $img = $('#wpiwm-image-preview img');
        renderPreview($img.length ? $img.attr('src') : null);
    })();

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

});
