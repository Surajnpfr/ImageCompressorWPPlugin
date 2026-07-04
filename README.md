# Privacy Image Compressor

WordPress plugin that serves a privacy-first image compressor at **`/compressor`** — no WordPress page, shortcode, Gutenberg, or REST API required.

**Version:** 1.2.0  
**Requires:** WordPress 5.8+, PHP 7.4+  
**Author:** Hamro Niti  
**License:** GPL-2.0-or-later

---

## Features

- Direct route: `https://yoursite.com/compressor`
- Two compression modes:
  - **Max Size** — target file size (KB/MB), iterative quality + resize
  - **Percentage** — quality slider (0–100%)
- Drag & drop upload
- Progress indicator and download results (original size, compressed size, savings %)
- **No permanent storage** — temp files only, deleted after response
- Imagick preferred, GD fallback
- Max upload: **20 MB**
- Allowed types: **JPG, JPEG, PNG, WebP**
- Works when REST API / `admin-ajax.php` is blocked (POST goes to `/compressor`)
- SEO: `noindex, follow`, canonical, Open Graph, `WebApplication` schema

---

## Install

1. Copy this folder into `wp-content/plugins/image-compressor/`  
   (or upload a zip of the plugin folder via **Plugins → Add New → Upload**).
2. Activate **Privacy Image Compressor**.
3. Visit `https://yoursite.com/compressor`.

Activation flushes rewrite rules automatically. If you get a **404**:

1. Go to **Settings → Permalinks**
2. Click **Save Changes** (no need to change settings)
3. Use a pretty permalink structure (e.g. **Post name**), not **Plain**

No page creation or shortcode setup is required.

---

## How it works

| Request | Behavior |
|---------|----------|
| `GET /compressor` | Standalone UI (full HTML page) |
| `POST /compressor` (`ic_action=compress`) | Compress image, return JSON, delete temp files |

Compression never uses the Media Library or database storage.

### Privacy

- Images are validated (MIME, extension, size) and written to the system temp directory only
- Output is returned as base64 in the JSON response
- Original and compressed temp files are unlinked in a `finally` block
- POST responses send `X-Robots-Tag: noindex, nofollow`

---

## Plugin structure

```
image-compressor/
├── image-compressor.php      # Bootstrap, assets, activation hooks
├── includes/
│   ├── router.php            # /compressor rewrite + template_redirect
│   ├── seo.php               # Meta, robots, schema for the route
│   ├── upload.php            # Validation + temp storage
│   ├── compressor.php        # Imagick / GD compression engine
│   └── processor.php         # Request orchestration + JSON response
├── templates/
│   └── compressor-ui.php     # Standalone frontend UI
└── assets/
    ├── css/compressor.css
    └── js/compressor.js
```

---

## Requirements

| Requirement | Notes |
|-------------|--------|
| PHP 7.4+ | Required |
| Imagick **or** GD | Imagick preferred for better quality/size control |
| Pretty permalinks | Required for `/compressor` rewrite |
| Writable temp dir | `sys_get_temp_dir()` must be writable |

---

## SEO notes

The `/compressor` page is treated as a **utility tool**, not primary content:

- Meta robots: **`noindex, follow`**
- `robots.txt` adds `Disallow: /compressor`
- Canonical, description, Open Graph / Twitter tags
- `WebApplication` JSON-LD for AI/search understanding
- Compatible overrides for Rank Math and Yoast when active

To allow indexing (e.g. for “image compressor” keywords), change the robots logic in `includes/seo.php`.

---

## Security

- Nonce verification on compress requests
- Strict MIME + extension checks (`finfo`, `getimagesize`)
- 20 MB upload cap
- No persistent file storage
- Outputs escaped in the UI template

---

## Troubleshooting

| Problem | Fix |
|---------|-----|
| **404 on /compressor** | Settings → Permalinks → Save; ensure not Plain permalinks |
| **403 on compress** | Plugin posts to `/compressor`, not `admin-ajax.php`. If still blocked, check host WAF/ModSecurity for multipart uploads |
| **Target size not met** | Max Size mode resizes when quality alone is not enough (common for PNG). Unreachable targets return an error instead of an oversized file |
| **WebP fails** | Server GD/Imagick must support WebP |

---

## Development

Local layout matches a standard WordPress plugin. Zip for upload should contain:

```
image-compressor/image-compressor.php
image-compressor/includes/...
```

Use forward slashes in the zip (required for WordPress plugin installers on some hosts).

---

## License

GPL-2.0-or-later. See plugin header in `image-compressor.php`.
