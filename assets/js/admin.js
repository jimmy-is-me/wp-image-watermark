(function ($) {
    'use strict';

    /* ================================================================
     * Preview canvas (Settings page only)
     * ================================================================ */
    var mediaFrame;
    var previewWatermarkImage = null;
    var previewCanvas = document.getElementById('wpiwm-preview-canvas');
    var previewCtx    = previewCanvas ? previewCanvas.getContext('2d') : null;
    var bgImage       = null;

    /* Load a random sample background image for the preview */
    function loadPreviewBg( cb ) {
        var seeds = ['watermark1','photo2','nature3','city4','landscape5','abstract6'];
        var seed  = seeds[ Math.floor( Math.random() * seeds.length ) ];
        var img   = new Image();
        img.crossOrigin = 'anonymous';
        img.onload  = function () { bgImage = img; if ( cb ) cb(); };
        img.onerror = function () { bgImage = null; if ( cb ) cb(); };
        img.src = 'https://picsum.photos/seed/' + seed + '/960/540';
    }

    function getPositionCoords( pos, bW, bH, eW, eH, ox, oy ) {
        switch ( pos ) {
            case 'top-left':      return { x: ox,           y: oy };
            case 'top-center':    return { x: (bW-eW)/2,    y: oy };
            case 'top-right':     return { x: bW-eW-ox,     y: oy };
            case 'middle-left':   return { x: ox,           y: (bH-eH)/2 };
            case 'center':        return { x: (bW-eW)/2,    y: (bH-eH)/2 };
            case 'middle-right':  return { x: bW-eW-ox,     y: (bH-eH)/2 };
            case 'bottom-left':   return { x: ox,           y: bH-eH-oy };
            case 'bottom-center': return { x: (bW-eW)/2,    y: bH-eH-oy };
            default:              return { x: bW-eW-ox,     y: bH-eH-oy }; // bottom-right
        }
    }

    function renderPreview() {
        if ( ! previewCtx || ! previewCanvas ) return;
        var W = previewCanvas.width, H = previewCanvas.height;
        previewCtx.clearRect( 0, 0, W, H );

        // Draw background
        if ( bgImage && bgImage.complete && bgImage.naturalWidth ) {
            previewCtx.drawImage( bgImage, 0, 0, W, H );
        } else {
            previewCtx.fillStyle = '#e8e8e8';
            previewCtx.fillRect( 0, 0, W, H );
            previewCtx.fillStyle = '#bbb';
            previewCtx.font = '20px sans-serif';
            previewCtx.textAlign = 'center';
            previewCtx.fillText( '示意圖載入中…', W / 2, H / 2 );
            previewCtx.textAlign = 'left';
        }

        var type     = $('input[name="watermark_type"]:checked').val() || 'text';
        var position = $('input[name="watermark_position"]:checked').val() || 'bottom-right';
        var offsetX  = parseInt( $('input[name="watermark_offset_x"]').val(), 10 ) || 0;
        var offsetY  = parseInt( $('input[name="watermark_offset_y"]').val(), 10 ) || 0;

        if ( type === 'image' ) {
            if ( previewWatermarkImage && previewWatermarkImage.complete && previewWatermarkImage.naturalWidth ) {
                var scale   = parseInt( $('input[name="watermark_scale"]').val(), 10 ) || 20;
                var opacity = ( parseInt( $('input[name="watermark_image_opacity"]').val(), 10 ) || 80 ) / 100;
                var drawW   = Math.max( 24, W * scale / 100 );
                var drawH   = drawW * ( previewWatermarkImage.naturalHeight / previewWatermarkImage.naturalWidth );
                var pos     = getPositionCoords( position, W, H, drawW, drawH, offsetX, offsetY );
                previewCtx.save();
                previewCtx.globalAlpha = opacity;
                previewCtx.drawImage( previewWatermarkImage, pos.x, pos.y, drawW, drawH );
                previewCtx.restore();
            } else {
                previewCtx.save();
                previewCtx.fillStyle = 'rgba(0,0,0,0.45)';
                previewCtx.font = '18px sans-serif';
                previewCtx.fillText( '← 請先選擇浮水印圖片', 30, H / 2 );
                previewCtx.restore();
            }
            return;
        }

        // Text watermark
        var text     = $('input[name="watermark_text"]').val() || ( typeof WPIWM_Admin !== 'undefined' ? WPIWM_Admin.preview_sample_text : '浮水印預覽' );
        var fontSize = Math.max( 12, parseInt( $('input[name="watermark_font_size"]').val(), 10 ) || 36 );
        var color    = $('input[name="watermark_font_color"]').val() || '#ffffff';
        var opacity  = ( parseInt( $('input[name="watermark_text_opacity"]').val(), 10 ) || 70 ) / 100;
        var scaled   = Math.max( 12, Math.round( fontSize * W / 960 ) );

        previewCtx.save();
        previewCtx.font        = 'bold ' + scaled + 'px Arial, sans-serif';
        previewCtx.globalAlpha = opacity;
        previewCtx.fillStyle   = color;
        previewCtx.shadowColor = 'rgba(0,0,0,0.5)';
        previewCtx.shadowBlur  = 6;
        var metrics = previewCtx.measureText( text );
        var pos     = getPositionCoords( position, W, H, metrics.width, scaled, offsetX, offsetY );
        previewCtx.fillText( text, pos.x, pos.y + scaled );
        previewCtx.restore();
    }

    function syncRanges() {
        $('.wpiwm-range').each( function () {
            $( this ).next('span').text( $( this ).val() + '%' );
        });
    }

    if ( previewCanvas ) {
        // Init: load bg then render
        loadPreviewBg( function () {
            syncRanges();
            renderPreview();
        });

        $('input[name="watermark_type"]').on('change', function () {
            var val = $( this ).val();
            $('.wpiwm-type-settings').hide();
            $( '#wpiwm-' + val + '-settings' ).show();
            $('.wpiwm-type-tab').removeClass('active');
            $( this ).closest('.wpiwm-type-tab').addClass('active');
            renderPreview();
        });

        $('input[name="watermark_position"]').on('change', function () {
            $('.wpiwm-pos-cell').removeClass('active');
            $( this ).closest('.wpiwm-pos-cell').addClass('active');
            renderPreview();
        });

        $( document ).on('input change',
            'input[name="watermark_text"],' +
            'input[name="watermark_font_size"],' +
            'input[name="watermark_font_color"],' +
            'input[name="watermark_text_opacity"],' +
            'input[name="watermark_scale"],' +
            'input[name="watermark_image_opacity"],' +
            'input[name="watermark_offset_x"],' +
            'input[name="watermark_offset_y"]',
            function () { syncRanges(); renderPreview(); }
        );

        $( '#wpiwm-select-image' ).on('click', function (e) {
            e.preventDefault();
            if ( mediaFrame ) { mediaFrame.open(); return; }
            mediaFrame = wp.media({
                title: '選擇浮水印圖片',
                button: { text: '使用此圖片' },
                multiple: false,
                library: { type: 'image' },
            });
            mediaFrame.on('select', function () {
                var att   = mediaFrame.state().get('selection').first().toJSON();
                var thumb = att.sizes && att.sizes.thumbnail ? att.sizes.thumbnail.url : att.url;
                $( '#watermark_image_id' ).val( att.id );
                $( '#wpiwm-image-preview' ).html(
                    '<img src="' + thumb + '" style="max-width:120px;max-height:80px;border:1px solid #ddd;border-radius:4px;">'
                );
                $( '#wpiwm-remove-image' ).show();
                previewWatermarkImage        = new Image();
                previewWatermarkImage.onload  = renderPreview;
                previewWatermarkImage.onerror = function () { previewWatermarkImage = null; renderPreview(); };
                previewWatermarkImage.src     = att.url;
            });
            mediaFrame.open();
        });

        $( '#wpiwm-remove-image' ).on('click', function () {
            $( '#watermark_image_id' ).val('');
            $( '#wpiwm-image-preview' ).html('');
            previewWatermarkImage = null;
            $( this ).hide();
            renderPreview();
        });

        $( '#auto_watermark' ).on('change', function () {
            $( '#wpiwm-auto-card' ).toggleClass( 'is-active', this.checked );
        });

        // If a watermark image was already selected, pre-load it for the preview
        var existingSrc = $( '#wpiwm-image-preview img' ).attr('src');
        if ( existingSrc ) {
            previewWatermarkImage        = new Image();
            previewWatermarkImage.onload  = renderPreview;
            previewWatermarkImage.onerror = function () { previewWatermarkImage = null; renderPreview(); };
            previewWatermarkImage.src     = existingSrc;
        }
    }

    /* ================================================================
     * Media library – row action links
     * ================================================================ */
    $( document ).on('click', '.wpiwm-row-apply', function (e) {
        e.preventDefault();
        e.stopPropagation();
        var $a    = $( this );
        var id    = $a.data('id');
        var nonce = $a.data('nonce');
        $a.text( WPIWM_Admin.applying ).css( 'pointer-events', 'none' );
        $.post( WPIWM_Admin.ajax_url, {
            action: 'wpiwm_apply_single',
            attachment_id: id,
            nonce: nonce,
        }, function (res) {
            if ( res.success ) {
                location.reload();
            } else {
                alert( res.data.message );
                $a.text('套用浮水印').css('pointer-events', '');
            }
        });
    });

    $( document ).on('click', '.wpiwm-row-remove', function (e) {
        e.preventDefault();
        e.stopPropagation();
        if ( ! confirm( WPIWM_Admin.confirm_remove ) ) return;
        var $a    = $( this );
        var id    = $a.data('id');
        var nonce = $a.data('nonce');
        $a.text( WPIWM_Admin.removing ).css( 'pointer-events', 'none' );
        $.post( WPIWM_Admin.ajax_url, {
            action: 'wpiwm_remove_single',
            attachment_id: id,
            nonce: nonce,
        }, function (res) {
            if ( res.success ) {
                location.reload();
            } else {
                alert( res.data.message );
                $a.text('清除標記').css('pointer-events', '');
            }
        });
    });

    /* ================================================================
     * Attachment detail sidebar – delegated on document
     * Delegation is critical: the sidebar HTML is re-rendered each time
     * the media modal opens, so we cannot bind directly on DOMContentLoaded.
     * ================================================================ */
    $( document ).on('click', '.wpiwm-detail-apply', function (e) {
        e.preventDefault();
        e.stopPropagation();
        var $btn    = $( this );
        var id      = $btn.data('id');
        var nonce   = $btn.data('nonce');
        var $field  = $btn.closest('.wpiwm-field');
        var $status = $field.find('.wpiwm-status');

        $btn.prop('disabled', true).text( WPIWM_Admin.applying );

        $.post( WPIWM_Admin.ajax_url, {
            action: 'wpiwm_apply_single',
            attachment_id: id,
            nonce: nonce,
        }, function (res) {
            $btn.prop('disabled', false).text('套用浮水印');
            if ( res.success ) {
                $status
                    .text('✔ 已套用浮水印')
                    .css({ color: '#2a9d8f', 'font-weight': '600' });
            } else {
                alert( res.data.message );
            }
        }).fail( function () {
            $btn.prop('disabled', false).text('套用浮水印');
            alert('網路錯誤，請重試。');
        });
    });

})(jQuery);
