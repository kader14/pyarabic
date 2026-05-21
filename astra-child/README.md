# Astra Child Theme

A minimal child theme for [Astra](https://wordpress.org/themes/astra/) used by **pyarabic.com**.

It exists for two main reasons:

1. Keep site-specific customizations (like the Google AdSense loader) outside of the parent theme so updating Astra never wipes them out.
2. Layer technical-SEO improvements that complement Yoast SEO Free without conflicting with it.

## What it does

- Loads the parent Astra stylesheet then the child stylesheet on top.
- Injects the Google AdSense loader (`adsbygoogle.js`) into `<head>` via `wp_head` instead of editing `header.php`.
- Skips the AdSense script in admin, feeds, and AMP endpoints.
- Loads a small set of SEO modules (toggleable, see below).

## Installation

1. Copy the `astra-child/` folder into your WordPress installation under:
   ```
   wp-content/themes/astra-child/
   ```
2. Make sure the parent theme **Astra** is also installed under `wp-content/themes/astra/`.
3. Log in to WordPress admin, go to **Appearance -> Themes**, find *Astra Child*, and click **Activate**.
4. Restore the parent `header.php` to its original Astra contents (the inline AdSense `<script>` is no longer needed there). A clean copy is provided at the repository root as `header.php`.

## Configuration

Override these from `wp-config.php`, a site-specific plugin, or the child theme `functions.php`:

```php
define( 'ASTRA_CHILD_ADSENSE_CLIENT_ID',    'ca-pub-XXXXXXXXXXXXXXXX' );
define( 'ASTRA_CHILD_SEO_TWITTER_HANDLE',   '@your_handle' );
define( 'ASTRA_CHILD_SEO_DEFAULT_OG_IMAGE', 'https://example.com/og.jpg' );
```

Or via filters at runtime:

```php
add_filter( 'astra_child_adsense_client_id',     fn() => 'ca-pub-XXXXXXXXXXXXXXXX' );
add_filter( 'astra_child_seo_twitter_handle',    fn() => '@your_handle' );
add_filter( 'astra_child_seo_default_og_image',  fn() => 'https://example.com/og.jpg' );
```

To turn the AdSense script off entirely, return an empty client ID from the filter.

## SEO modules

All modules live under `inc/seo/` and are loaded by `inc/seo/loader.php`. They are designed to **complement Yoast SEO Free**, not replace it.

| Module          | Purpose                                                                                                  |
| --------------- | -------------------------------------------------------------------------------------------------------- |
| `performance`   | Adds `preconnect` / `dns-prefetch` for AdSense and Google fonts, disables emojis, defers non-critical JS |
| `arabic`        | Forces `ar_AR` Open Graph locale, larger excerpt, RTL-friendly breadcrumb separator, hreflang fallback   |
| `yoast-tweaks`  | Lower priority for Yoast metabox, default Twitter handle, default OG image, Article publisher in schema  |
| `schema-extras` | `[faq]` / `[faq_item]` shortcodes that emit FAQPage JSON-LD plus a styled accordion                      |
| `images`        | Auto alt fallback (attachment title or post title) for images missing alt text                           |

### Disable everything

```php
add_filter( 'astra_child_load_seo', '__return_false' );
```

### Disable a single module

```php
add_filter( 'astra_child_seo_module_performance',   '__return_false' );
add_filter( 'astra_child_seo_module_arabic',        '__return_false' );
add_filter( 'astra_child_seo_module_yoast_tweaks',  '__return_false' );
add_filter( 'astra_child_seo_module_schema_extras', '__return_false' );
add_filter( 'astra_child_seo_module_images',        '__return_false' );
```

### Per-feature toggles

```php
// Keep emojis loading.
add_filter( 'astra_child_seo_disable_emojis', '__return_false' );

// Also strip oEmbed (off by default because some content depends on it).
add_filter( 'astra_child_seo_disable_embeds', '__return_true' );

// Don't defer a specific script handle.
add_filter( 'astra_child_seo_blocking_scripts', function ( $handles ) {
    $handles[] = 'my-critical-script';
    return $handles;
} );
```

## FAQ shortcode usage

In any post or page (Classic editor, or a Shortcode block in the Block editor):

```
[faq]
  [faq_item q="ما هو بايثون؟"]بايثون لغة برمجة عالية المستوى عامة الاستخدام.[/faq_item]
  [faq_item q="من أين أبدأ؟"]من الموقع الرسمي python.org ثم تثبيت محرر مثل VS Code.[/faq_item]
[/faq]
```

This renders a native `<details>` accordion and emits a single `FAQPage` JSON-LD block, which makes the entries eligible for FAQ rich results in Google search.

## Adding a screenshot

WordPress shows `screenshot.png` on the Themes page. Drop a 1200x900 PNG named `screenshot.png` into this folder when you have one ready.

## File overview

```
astra-child/
├── style.css                    Child theme header + custom CSS
├── functions.php                Enqueues styles, prints AdSense, loads SEO modules
├── README.md                    This file
└── inc/
    └── seo/
        ├── loader.php           Loads enabled SEO modules
        ├── performance.php      Resource hints, emoji removal, defer JS
        ├── arabic.php           ar_AR locale, RTL breadcrumb, hreflang
        ├── yoast-tweaks.php     Yoast SEO Free filter improvements
        ├── schema-extras.php    [faq] shortcode + FAQPage JSON-LD
        └── images.php           Alt-text fallback for images
```

## Testing checklist

After activating, verify:

- View page source on the homepage and confirm:
  - `<link rel="preconnect" href="https://pagead2.googlesyndication.com" ...>` is present.
  - `<script async src=".../adsbygoogle.js?client=ca-pub-..." crossorigin="anonymous">` is in `<head>`.
  - No `s.w.org/images/core/emoji/...` script tag is present.
  - `<meta property="og:locale" content="ar_AR" />` is present (requires Yoast active).
- View source on a post that uses `[faq]` and confirm a `<script type="application/ld+json">` containing `"@type":"FAQPage"` is rendered.
- Run the [Rich Results Test](https://search.google.com/test/rich-results) on a FAQ post and confirm Google detects the FAQ.
- Run [PageSpeed Insights](https://pagespeed.web.dev/) before and after to confirm the resource hints and deferred scripts improved LCP / Total Blocking Time.
