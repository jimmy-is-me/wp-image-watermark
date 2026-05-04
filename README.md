# WP Image Watermark

> WordPress plugin to add image or text watermarks to uploaded photos. Supports auto-apply on upload, manual per-image application from the attachment detail screen or media library, and batch processing.

## Features

- **Text watermark** — custom text, font size, color, and opacity
- **Image watermark** — select a PNG/WebP logo from your media library, control scale & opacity
- **Flexible positioning** — 9-point grid (top/center/bottom × left/center/right) with X/Y pixel offset
- **Auto watermark on upload** — enable a global switch; every new image gets watermarked automatically
- **Manual apply per image** — click **套用浮水印** in the attachment detail sidebar (visible when editing any image attachment) or from the Media Library row actions
- **Batch tools** — apply or clear watermark status on all existing media library images from the settings page
- **Image protection** — optional right-click disable and DevTools detection
- **GD & ImageMagick support** — uses whichever library is available on your server
- **Live preview canvas** — preview watermark position and opacity on a blank canvas before saving

## Requirements

- WordPress 5.8+
- PHP 7.4+
- GD or ImageMagick PHP extension
- Supported image formats: JPEG, PNG, WebP

## Installation

1. Upload the `wp-image-watermark` folder to `/wp-content/plugins/`
2. Activate the plugin from **Plugins → Installed Plugins**
3. Go to **Media → 浮水印設定** to configure

## Usage

### Settings page (`Media → 浮水印設定`)

| Section | Options |
|---|---|
| Auto watermark | Toggle to watermark all newly uploaded images automatically |
| Watermark type | Text or Image |
| Text settings | Watermark string, font size, color, opacity |
| Image settings | Media library image, scale (% of source width), opacity |
| Position | 9-point grid + X/Y offset in pixels |
| Image protection | Right-click disable, DevTools detection |
| Batch tools | Apply to all / Clear all status marks |

### Manual apply on a single image

**Method A — Attachment detail sidebar:**
1. Open Media Library
2. Click any image → the detail panel opens on the right
3. Scroll to the **浮水印** field
4. Click **套用浮水印** — the watermark is applied immediately

**Method B — Media Library row actions:**
1. Go to **Media → 媒體庫** (List view)
2. Hover over an image row
3. Click **套用浮水印** in the row actions

### Auto watermark

1. Configure your watermark settings and save
2. Enable **自動浮水印** toggle and save again
3. Every subsequently uploaded image will be watermarked automatically

> ⚠️ Watermarks are written directly to the image file. Always keep backups of original images.

## File Structure

```
wp-image-watermark/
├── wp-image-watermark.php          Main plugin file
├── includes/
│   ├── class-settings.php          Option management
│   ├── class-watermark-engine.php  GD / ImageMagick rendering
│   ├── class-media-handler.php     Upload hooks, AJAX, media library UI, attachment fields
│   ├── class-admin.php             Settings page, enqueue, batch UI
│   ├── class-image-protection.php  Front-end protection scripts
│   └── class-ajax-helpers.php      Batch ID fetcher
├── assets/
│   ├── css/admin.css
│   ├── js/admin.js
│   └── fonts/                      (optional) OpenSans-Regular.ttf for TTF text rendering
└── README.md
```

## Changelog

### 1.1.0
- **New:** Watermark action button in attachment detail sidebar (works in media modal and edit-attachment screen)
- **Fix:** Auto watermark now correctly reads boolean value from saved options
- **Fix:** JS only loaded on relevant admin screens (settings, upload.php, attachment edit) — no longer interferes with media upload
- **Fix:** `auto_watermark` setting stored as integer (1/0) to prevent comparison issues
- **Fix:** Row actions now correctly filter to image attachments only
- **Improvement:** Added detailed `error_log()` output for watermark engine debugging

### 1.0.0
- Initial release
