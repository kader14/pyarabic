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
| `robots`        | Adds sitemap, scraper-bot blocks, and low-value endpoint disallows when WP generates robots.txt          |
| `critical-css`  | Inlines per-template critical CSS and defers full stylesheets via media="print" onload                   |

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
add_filter( 'astra_child_seo_module_robots',        '__return_false' );
add_filter( 'astra_child_seo_module_critical_css',  '__return_false' );
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
├── inc/
│   └── seo/
│       ├── loader.php           Loads enabled SEO modules
│       ├── performance.php      Resource hints, emoji removal, defer JS
│       ├── arabic.php           ar_AR locale, RTL breadcrumb, hreflang
│       ├── yoast-tweaks.php     Yoast SEO Free filter improvements
│       ├── schema-extras.php    [faq] shortcode + FAQPage JSON-LD
│       ├── images.php           Alt-text fallback for images
│       ├── robots.php           robots.txt filter (sitemap, scraper blocks)
│       └── critical-css.php     Inline critical CSS + defer the rest
└── assets/
    └── critical/
        ├── default.css          Fallback critical CSS (used when no template match)
        ├── home.css             (optional) Homepage-specific critical CSS
        ├── single.css           (optional) Single post critical CSS
        ├── page.css             (optional) Static page critical CSS
        └── archive.css          (optional) Archive / search critical CSS
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

## robots.txt

Two ways to manage `robots.txt`:

### A) Physical file (recommended)

Upload `robots.txt` from the repository root to the document root of the site (same folder as `wp-config.php`). The file ships with sane defaults for a Yoast-powered Arabic site:

- Blocks `wp-admin/`, `xmlrpc.php`, search results, replytocom, and tracking-parameter URLs.
- Allows assets (CSS/JS/images) so Google can render pages.
- Blocks aggressive SEO scrapers (Semrush, Ahrefs, MJ12, etc.).
- Has a commented-out section for AI-training crawlers (GPTBot, ClaudeBot, Google-Extended, etc.) - uncomment to opt out.
- Declares the Yoast sitemap: `https://pyarabic.com/sitemap_index.xml`.

When this physical file exists, Apache/Nginx serves it directly and the `robots` SEO module is skipped.

### B) Dynamic (delete the physical file)

If `robots.txt` is not present at the document root, WordPress generates one on the fly. The `robots` module then:

1. Adds the sitemap URL (only if Yoast hasn't already done so - no duplicates).
2. Appends scraper-bot blocks.
3. Appends extra disallow rules for low-value endpoints.

### Configure the sitemap URL

```php
define( 'ASTRA_CHILD_SEO_SITEMAP_URL', 'https://pyarabic.com/sitemap_index.xml' );
```

Or:

```php
add_filter( 'astra_child_seo_sitemap_url', fn() => 'https://pyarabic.com/sitemap_index.xml' );
```

### Customize blocked bots

```php
add_filter( 'astra_child_seo_blocked_bots', function ( $bots ) {
    $bots[] = 'GPTBot';        // also block AI training
    $bots[] = 'ClaudeBot';
    return $bots;
} );
```

## Critical CSS

Inlines a small "above the fold" stylesheet directly in `<head>` so the page can paint without waiting for the full Astra stylesheet, then loads the full CSS asynchronously using the `media="print"` onload swap. Users without JavaScript still get the full styles via a `<noscript>` fallback.

### How it picks a file

The module looks under `astra-child/assets/critical/` for a file matching the current template:

| Template     | File           |
| ------------ | -------------- |
| Front page / blog index | `home.css`     |
| Single post  | `single.css`   |
| Static page  | `page.css`     |
| Archive / search | `archive.css` |
| Anything else | `default.css` |

If a template-specific file is missing, `default.css` is used. The shipped `default.css` covers Astra RTL basics (header, hero, typography) but is intentionally generic.

### Generate a real critical CSS for your live URL

The shipped fallback is a sane minimum, but **a critical CSS generated against your actual rendered page will give you the best LCP score**. Pick one:

```bash
# Penthouse - precise, headless Chrome based.
npx penthouse --url https://pyarabic.com --css ./full.css --width 1366 --height 900 > assets/critical/home.css

# critical (Addy Osmani) - similar, with Tailwind-style API.
npx critical https://pyarabic.com --width 1366 --height 900 --inline=false > assets/critical/home.css
```

Or use a hosted generator and paste the output:

- https://www.corewebvitals.io/criticalcss
- https://www.sitelocity.com/critical-path-css-generator

Repeat per template (`home`, `single`, `page`, `archive`) for best results.

### Configuration

```php
// Disable critical CSS entirely.
add_filter( 'astra_child_seo_critical_css_enabled', '__return_false' );

// Or disable the whole module via the loader.
add_filter( 'astra_child_seo_module_critical_css', '__return_false' );

// Keep specific stylesheets blocking (i.e. don't defer them).
add_filter( 'astra_child_seo_critical_css_handles', function ( $handles ) {
    $handles[] = 'astra-theme-css';   // example: keep Astra parent blocking
    $handles[] = 'wp-block-library';
    return $handles;
} );

// Override the chosen template (useful for landing pages).
add_filter( 'astra_child_seo_critical_css_template', function ( $template ) {
    if ( is_page( 'landing' ) ) {
        return 'landing';
    }
    return $template;
} );
```

### Behavior

- Skipped for: admin screens, feeds, AMP endpoints, Customizer preview, and logged-in editors (so editing always shows the full design).
- Critical CSS is inlined as `<style id="ac-critical-css" data-version="...">` at priority 1 on `wp_head`, before any other tag.
- Deferred stylesheets are marked with `data-ac-deferred="1"` so they're easy to spot in DevTools.
