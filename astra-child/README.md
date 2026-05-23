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

| Module             | Purpose                                                                                                  |
| ------------------ | -------------------------------------------------------------------------------------------------------- |
| `performance`      | Adds `preconnect` / `dns-prefetch` for AdSense and Google fonts, disables emojis, defers non-critical JS |
| `arabic`           | Forces `ar_AR` Open Graph locale, larger excerpt, RTL-friendly breadcrumb separator, hreflang fallback   |
| `yoast-tweaks`     | Lower priority for Yoast metabox, default Twitter handle, default OG image, Article publisher in schema  |
| `schema-extras`    | `[faq]` / `[faq_item]` shortcodes that emit FAQPage JSON-LD plus a styled accordion                      |
| `images`           | Auto alt fallback (attachment title or post title) for images missing alt text                           |
| `robots`           | Adds sitemap, scraper-bot blocks, and low-value endpoint disallows when WP generates robots.txt          |
| `critical-css`     | Inlines per-template critical CSS and defers full stylesheets via media="print" onload                   |
| `meta-description` | Cleans Yoast meta description output, smart fallback chain, Arabic-aware truncation                      |
| `breadcrumbs`      | Suppresses Astra's microdata, repairs Yoast BreadcrumbList `itemListElement`, drops invalid pieces       |
| `article-schema`   | Upgrades Article -> NewsArticle, adds keywords, multi-res images, speakable, copyright, Arabic wordCount |
| `internal-linking` | Auto-links keyword phrases to internal URLs and appends a related-posts block (with `[related_posts]` shortcode) |
| `early-hints`      | Preloads critical resources (CSS, featured image) via `<link rel=preload>` and `Link:` headers - Cloudflare promotes to 103 |
| `query-strings`    | Strips `?ver=...` cache-buster query strings from local CSS/JS so edge caches and CDNs can fully cache them                  |
| `toc`              | Auto-generated Table of Contents for long posts: nested h2/h3 list, anchor IDs, smooth scroll, `[toc]` shortcode             |
| `serp-ctr`         | Robots `max-image-preview:large`, year auto-stamp on evergreen titles (opt-in), reading-time signal in Article + Twitter cards |
| `reading-time-badge` | Frontend reading-time badge ("⏱ 5 دقيقة قراءة") in Astra's meta line, reusing the cached value from `serp-ctr`                |

### Disable everything

```php
add_filter( 'astra_child_load_seo', '__return_false' );
```

### Disable a single module

```php
add_filter( 'astra_child_seo_module_performance',      '__return_false' );
add_filter( 'astra_child_seo_module_arabic',           '__return_false' );
add_filter( 'astra_child_seo_module_yoast_tweaks',     '__return_false' );
add_filter( 'astra_child_seo_module_schema_extras',    '__return_false' );
add_filter( 'astra_child_seo_module_images',           '__return_false' );
add_filter( 'astra_child_seo_module_robots',           '__return_false' );
add_filter( 'astra_child_seo_module_critical_css',     '__return_false' );
add_filter( 'astra_child_seo_module_meta_description', '__return_false' );
add_filter( 'astra_child_seo_module_breadcrumbs',      '__return_false' );
add_filter( 'astra_child_seo_module_article_schema',   '__return_false' );
add_filter( 'astra_child_seo_module_internal_linking', '__return_false' );
add_filter( 'astra_child_seo_module_early_hints',      '__return_false' );
add_filter( 'astra_child_seo_module_query_strings',    '__return_false' );
add_filter( 'astra_child_seo_module_toc',              '__return_false' );
add_filter( 'astra_child_seo_module_serp_ctr',         '__return_false' );
add_filter( 'astra_child_seo_module_reading_time_badge', '__return_false' );
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
│       ├── critical-css.php     Inline critical CSS + defer the rest
│       ├── meta-description.php Clean Yoast meta description + fallbacks
│       ├── breadcrumbs.php      Repair Yoast BreadcrumbList, suppress Astra microdata
│       ├── article-schema.php   Upgrade Yoast Article schema (NewsArticle, speakable, etc.)
│       ├── internal-linking.php Keyword auto-linking + related posts block
│       ├── early-hints.php      Preload critical resources via <link> + Link: header
│       ├── query-strings.php    Strip cache-buster query strings from local CSS/JS
│       ├── toc.php              Auto Table of Contents for long single posts
│       ├── serp-ctr.php         Robots upgrade, year auto-stamp, reading-time signal
│       └── reading-time-badge.php  Frontend "X دقيقة قراءة" badge near the title
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

## Strong meta descriptions

Yoast SEO Free relies on the editor to type a description per post; when it isn't typed, Yoast falls back to a raw excerpt that is often noisy (shortcodes, byline, emoji, abrupt truncation). The `meta-description` module fixes this without taking control away from Yoast.

### What it does

1. **Cleans** every description Yoast emits: strips shortcodes, HTML, emoji, decodes entities, removes leading "By Author |" patterns, collapses whitespace.
2. **Falls back** when Yoast's value is empty or shorter than 80 chars. The chain is:
   - manual post excerpt
   - first paragraph of post content
   - term description (for category/tag/taxonomy archives)
   - author bio (for author archives)
   - site tagline (last resort)
3. **Trims** to a max of 155 chars without breaking words and adds a Unicode ellipsis (`...`).
4. **Filters** Open Graph and Twitter description fields too, so social previews stay consistent with search results.

### Configuration

```php
// Adjust length thresholds.
add_filter( 'astra_child_seo_metadesc_min', fn() => 100 );  // shorter than this triggers fallback
add_filter( 'astra_child_seo_metadesc_max', fn() => 160 );  // hard truncate

// Append a fixed CTA to every description.
add_filter( 'astra_child_seo_metadesc_final', function ( $desc ) {
    return rtrim( $desc, '...' ) . ' - pyarabic.com';
} );

// Pre-fill Yoast meta description on save (off by default).
// When enabled, the cleaned fallback is written into _yoast_wpseo_metadesc
// only if the editor left the field blank.
add_filter( 'astra_child_seo_metadesc_autosave', '__return_true' );

// Limit autosave to specific post types.
add_filter( 'astra_child_seo_metadesc_post_types', fn() => array( 'post' ) );

// Hide the admin notice that warns about missing descriptions.
add_filter( 'astra_child_seo_metadesc_admin_notice', '__return_false' );
```

### Behavior notes

- Runs at priority 99 on `wpseo_metadesc`, `wpseo_opengraph_desc`, `wpseo_twitter_description` so it is the last word.
- Uses `mb_strlen` / `mb_substr` so Arabic letters are counted correctly.
- The autosave handler only fills `_yoast_wpseo_metadesc` when it's empty; manually crafted descriptions are never overwritten.

## Breadcrumb schema

Fixes the Search Console error **`Missing field "itemListElement" (in "BreadcrumbList")`** that Yoast SEO Free can emit on some pages where the trail collapses to a single item, and removes the duplicate microdata Astra prints on its breadcrumbs.

### What it does

1. **Silences Astra's breadcrumb microdata** so Yoast's JSON-LD is the single source of truth. Astra still renders the visible breadcrumbs - only the structured-data attributes are stripped.
2. **Validates `wpseo_schema_breadcrumb`** at priority 99. If `itemListElement` is missing, empty, or has fewer than 2 items, the module either:
   - rebuilds the trail from the current request (post categories, page hierarchy, taxonomy ancestors, search query, author, archive, 404, etc.), or
   - returns `false` to remove the BreadcrumbList from the JSON-LD `@graph` - cleaner than emitting an invalid one.

### Customization

```php
// Disable the whole module.
add_filter( 'astra_child_seo_module_breadcrumbs', '__return_false' );

// Keep Astra's breadcrumb schema (not recommended).
add_filter( 'astra_child_seo_breadcrumbs_disable_astra_schema', '__return_false' );

// Customize the rebuilt items - e.g. prepend a "Blog" segment.
add_filter( 'astra_child_seo_breadcrumb_items', function ( $items ) {
    array_splice( $items, 1, 0, array( array(
        '@type'    => 'ListItem',
        'position' => 99,
        'name'     => 'Blog',
        'item'     => home_url( '/blog/' ),
    ) ) );
    // Re-number positions so they stay 1..n.
    foreach ( $items as $i => &$item ) {
        $item['position'] = $i + 1;
    }
    return $items;
} );
```

### Verifying the fix

1. Open the affected URL in [Rich Results Test](https://search.google.com/test/rich-results).
2. Confirm `Breadcrumbs` either passes validation or is no longer present (both are valid outcomes).
3. In Search Console, click **Validate fix** on the affected URLs and wait 24-48h for re-crawl.



## Advanced Article schema

Yoast SEO Free emits a sensible Article schema node, but it stops short of the fields that meaningfully improve Rich Result and Discover eligibility. The `article-schema` module fills the gaps without disabling Yoast.

### What it adds to every singular post

- `@type: NewsArticle` (when the post is detected as news - see below).
- `articleSection` from the Yoast primary category, or the first category as a fallback.
- `keywords` built from the post tags (comma-separated).
- `image` as an array of multiple resolutions of the featured image (full / large / medium_large) so Google Discover can pick the best aspect ratio.
- `thumbnailUrl` separate from `image`.
- `speakable` SpeakableSpecification covering the headline and first paragraph - useful for Google Assistant TTS, especially on Arabic content.
- `copyrightYear` (from publication date) and `copyrightHolder` (linked to the existing Organization node).
- `wordCount` computed with a Unicode-aware split that counts Arabic words correctly.

### NewsArticle detection

A post is treated as `NewsArticle` when **any** of the following is true:

- It belongs to a category whose slug or name is in the news list. Default list: `news`, `أخبار`, `اخبار`.
- Its post type is in the news post-type list. Default list: `news`.
- It has post meta `_is_news` set to a truthy value.

Customize each:

```php
// Treat additional categories as news.
add_filter( 'astra_child_seo_news_categories', function ( $cats ) {
    $cats[] = 'breaking';
    $cats[] = 'عاجل';
    return $cats;
} );

// Use a custom post type for news.
add_filter( 'astra_child_seo_news_post_types', fn() => array( 'news', 'breaking' ) );

// Or decide per-post with arbitrary logic.
add_filter( 'astra_child_seo_is_news_post', function ( $is_news, $post ) {
    if ( has_term( 'live', 'event-status', $post ) ) {
        return true;
    }
    return $is_news;
}, 10, 2 );
```

### Customization

```php
// Disable the whole module.
add_filter( 'astra_child_seo_module_article_schema', '__return_false' );

// Keep Yoast's image as-is (don't replace with the multi-res array).
add_filter( 'astra_child_seo_article_replace_images', '__return_false' );

// Skip speakable.
add_filter( 'astra_child_seo_article_enable_speakable', '__return_false' );

// Customize speakable selectors.
add_filter( 'astra_child_seo_article_speakable_selectors', function () {
    return array( 'h1.entry-title', '.lede', '.entry-content p:first-of-type' );
} );

// Final tweak: inject any extra fields (sponsor, isAccessibleForFree, video).
add_filter( 'astra_child_seo_article_data', function ( $data, $post, $context ) {
    $data['isAccessibleForFree'] = true;
    return $data;
}, 10, 3 );
```

### Verifying

1. Open a published post in [Rich Results Test](https://search.google.com/test/rich-results).
2. Confirm the Article (or NewsArticle) section shows `articleSection`, `keywords`, multi-resolution `image`, `speakable`, `wordCount`, and `copyrightYear`.
3. For news posts, confirm `@type` reads `NewsArticle`, not `Article`.



## Internal linking

Two complementary tools that strengthen the internal link graph of the site without manual editor work.

### 1) Keyword auto-linking

Go to **Settings -> Internal Links** and add one entry per line in the form `keyword | URL`:

```
WordPress | https://pyarabic.com/wordpress-guide
بايثون | https://pyarabic.com/python-intro
Yoast SEO | https://pyarabic.com/yoast-seo-tutorial
```

The first occurrence of each keyword in any singular post is converted into an `<a class="ac-internal-link">` link. The replacement uses `DOMDocument` so:

- Text inside `<a>`, `<code>`, `<pre>`, `<kbd>`, `<samp>`, `<h1>`-`<h6>`, `<script>`, `<style>`, `<input>`, `<textarea>`, `<button>`, and `<svg>` is never touched.
- Self-links (linking the current page back to itself) are skipped.
- Each keyword links once per post.
- Total per-post auto-links cap at 5 (filterable).

```php
// Add or override pairs in code (e.g. from a site plugin).
add_filter( 'astra_child_il_link_map', function ( $map ) {
    $map['ووردبريس'] = home_url( '/wp-guide/' );
    return $map;
} );

// Raise or lower the per-post link cap.
add_filter( 'astra_child_il_max_links_per_post', fn() => 8 );
```

### 2) Related posts

After every single post, an accessible `<aside class="ac-related-posts">` block is appended showing 4 posts that share at least one tag or category with the current one. Results are cached per post for 1 hour using transients (busted on `save_post` / `deleted_post`). If a post has fewer than 4 related candidates, the list is padded with the latest posts so the block never feels empty.

#### Manual placement

Drop the shortcode anywhere inside content. The automatic append is then suppressed for that post:

```
[related_posts count="6" title="اقرأ أيضاً"]
```

#### Configuration

```php
// Disable the auto-append while keeping the shortcode.
add_filter( 'astra_child_il_auto_append_related', '__return_false' );

// Change the default count.
add_filter( 'astra_child_il_related_count', fn() => 6 );

// Override the heading.
add_filter( 'astra_child_il_related_title', fn() => 'مقالات مشابهة قد تعجبك' );

// Tweak the underlying WP_Query args (e.g. limit to a category).
add_filter( 'astra_child_il_related_query_args', function ( $args, $post_id ) {
    $args['cat'] = 12;
    return $args;
}, 10, 2 );
```

#### Disable the whole module

```php
add_filter( 'astra_child_seo_module_internal_linking', '__return_false' );
```


## Early Hints

Speeds up Largest Contentful Paint by telling the browser which resources to start fetching before the full HTML body has been parsed.

The same configuration drives three layers of behavior, all activated automatically:

1. `<link rel="preload">` tags injected at the very top of `<head>`. Works in every browser and on every host, with no infrastructure changes.
2. `Link:` HTTP response headers carrying the same hints. Reverse proxies and CDNs (Cloudflare, Fastly, mod_http2) read them and either preload eagerly or, on supporting hosts, materialize an actual `103 Early Hints` interim response that lands on the client before PHP has finished building the page.
3. When the origin sits behind **Cloudflare** with Early Hints enabled in the dashboard, the same `Link:` headers are promoted to a real `103` automatically - giving you the full Early Hints win without writing any platform-specific code.

### What gets preloaded by default

- Astra parent stylesheet (`style.min.css` if present, otherwise `style.css`).
- The child theme stylesheet.
- The post's featured image at the `large` size, with `imagesrcset` / `imagesizes` for responsive variants. Skipped on archive / search / 404 pages where there is no clear LCP image.

The total list is capped at 5 entries so we never over-push and waste bandwidth.

### Customization

```php
// Disable the whole module.
add_filter( 'astra_child_seo_module_early_hints', '__return_false' );

// Or disable just at runtime (e.g. on staging).
add_filter( 'astra_child_seo_early_hints_enabled', '__return_false' );

// Cap the number of hints (default 5).
add_filter( 'astra_child_seo_early_hints_max', fn() => 3 );

// Add a hero image used on category archives so it gets preloaded too.
add_filter( 'astra_child_seo_early_hints_resources', function ( $resources ) {
    if ( is_category( 'featured' ) ) {
        $resources[] = array(
            'href' => 'https://pyarabic.com/wp-content/uploads/featured-hero.jpg',
            'as'   => 'image',
        );
    }
    return $resources;
} );

// Preload a self-hosted Arabic font.
add_filter( 'astra_child_seo_early_hints_resources', function ( $resources ) {
    $resources[] = array(
        'href' => get_stylesheet_directory_uri() . '/fonts/Cairo-Regular.woff2',
        'as'   => 'font',
        'type' => 'font/woff2',
    );
    return $resources;
} );
```

### Verifying

1. Open a single post URL and view source - the first lines inside `<head>` should be `<link rel="preload" ...>` tags.
2. From a terminal: `curl -sI https://pyarabic.com/some-post/ | grep -i ^link` - the response should include a `Link:` header listing the same resources.
3. On Cloudflare-fronted sites, enable **Speed -> Optimization -> Early Hints** in the dashboard and run [WebPageTest](https://webpagetest.org/) - the waterfall should show resources starting before the main document finishes downloading.



## Remove cache-buster query strings

WordPress, Astra, and most plugins append a version query string to every CSS and JS URL: `style.min.css?ver=4.6.0`. Many edge caches and reverse proxies are configured to skip caching of any URL with a query string, even when the underlying file is static. PageSpeed Insights and GTmetrix audits flag this as **"Remove query strings from static resources"**.

The `query-strings` module fixes that by filtering `style_loader_src` and `script_loader_src` and stripping the conventional cache-buster parameters (`ver`, `v`, `rev`, `cb`, `ts`, `_`).

### Behavior

- Runs only on front-end requests. Admin, REST, AJAX, feeds, AMP, and Customizer preview keep their original URLs (the Customizer iframe needs the version to refresh while editing).
- Touches only URLs that point to the same host as the site. Third-party CDNs (e.g. fonts.googleapis.com, AdSense) are left alone.
- Other unrelated query parameters (e.g. signed CDN tokens) are preserved - only the cache-buster names are removed.

### Trade-off

Once `?ver=` is gone, browsers cache the asset under its bare URL. After you ship a CSS/JS change, returning visitors will not see it until their own cache expires unless you either:

- rename the file (e.g. `style.css` -> `style.v2.css`),
- bump the file's `filemtime()` (which most caching plugins do automatically), or
- click "purge cache" in your caching plugin / CDN.

### Customization

```php
// Disable the whole module.
add_filter( 'astra_child_seo_module_query_strings', '__return_false' );

// Or disable just at runtime (e.g. on staging).
add_filter( 'astra_child_seo_remove_query_strings_enabled', '__return_false' );

// Keep query strings on a specific URL (e.g. a configurator script).
add_filter( 'astra_child_seo_remove_query_strings', function ( $strip, $src ) {
    if ( false !== strpos( $src, '/configurator.js' ) ) {
        return false;
    }
    return $strip;
}, 10, 2 );

// Strip extra parameter names that some plugin uses as cache-buster.
add_filter( 'astra_child_seo_qs_param_names', function ( $params ) {
    $params[] = 'asset_ver';
    return $params;
} );
```

### Verifying

1. View page source on a single post and search for `?ver=` - there should be no occurrences inside `<link>` / `<script>` tags pointing to your own host.
2. Run [PageSpeed Insights](https://pagespeed.web.dev/) - the "Remove query strings from static resources" or "Use efficient cache policy" warnings should drop or disappear for first-party files.
3. From a terminal:
   ```bash
   curl -s https://pyarabic.com/some-post/ | grep -oE '(href|src)="[^"]+\.(css|js)[^"]*"' | grep '\?ver='
   ```
   Should return zero matches for local URLs.



## Auto Table of Contents

Adds an automatic TOC to long single posts. Builds a nested ordered list from h2 / h3 headings, gives each heading an anchor `id` (Arabic-friendly slug via `sanitize_title()`), and renders an accessible `<details>` element so the TOC can be collapsed without any JavaScript.

### When it activates

- Singular post, in the main loop, on the front end.
- Content has at least 500 words (filterable).
- Content has at least 3 matching headings (filterable).

If the editor places a `[toc]` shortcode somewhere in the content, those word/heading thresholds are bypassed and the TOC renders exactly where the shortcode is.

### Manual placement

```
[toc]
```

That single line acts as a placeholder. The module's `the_content` filter replaces it with the rendered TOC after collecting the headings.

### Customization

```php
// Disable the whole module.
add_filter( 'astra_child_seo_module_toc', '__return_false' );

// Or disable just at runtime (e.g. on a specific category).
add_filter( 'astra_child_seo_toc_enabled', function ( $enabled ) {
    if ( in_category( 'no-toc' ) ) {
        return false;
    }
    return $enabled;
} );

// Include h4 in the TOC.
add_filter( 'astra_child_seo_toc_levels', fn() => array( 'h2', 'h3', 'h4' ) );

// Lower the word threshold for how long is "long enough".
add_filter( 'astra_child_seo_toc_min_words', fn() => 300 );

// Show the TOC even with just two headings.
add_filter( 'astra_child_seo_toc_min_headings', fn() => 2 );

// Translate the title.
add_filter( 'astra_child_seo_toc_title', fn() => 'محتويات المقال' );

// Render as a non-collapsible <nav> instead of <details>.
add_filter( 'astra_child_seo_toc_collapsible', '__return_false' );

// Disable auto insertion (keeps the [toc] shortcode working).
add_filter( 'astra_child_seo_toc_auto_insert', '__return_false' );

// Tune the scroll offset to match a sticky header height.
add_filter( 'astra_child_seo_toc_scroll_offset', fn() => 120 );
```

### Behavior notes

- The filter runs on `the_content` at priority 11. Headings get `id` attributes added even if the post is too short for a TOC, so other code (e.g. external shares to a section anchor) keeps working.
- Slugs come from `sanitize_title()`, which preserves Arabic characters and gives clean `id="ما-هو-بايثون"` style anchors.
- Duplicate headings get auto-numbered (`-2`, `-3`, ...) so anchors stay unique.
- Smooth scrolling and a configurable `scroll-padding-top` are added on `<html>` to play nicely with sticky headers.




## SERP CTR

Targets the highest-leverage organic search click-through-rate levers that Yoast SEO Free does not enable by default. Three independent features, each with its own toggle.

### 1) Robots meta upgrade

Yoast Free emits the standard `index, follow` robots meta but does not add the preview-size hints that unlock the largest snippet, image, and video previews in Google SERP. This module adds them via the core `wp_robots` filter:

```html
<meta name="robots" content="index, follow, max-snippet:-1, max-image-preview:large, max-video-preview:-1" />
```

Documented to lift image-result CTR by 5-15% on rich-content sites and is required for posts to appear in Google Discover at full width.

The module is **on by default** and skipped when the page is already `noindex`.

```php
// Disable just this feature.
add_filter( 'astra_child_seo_ctr_robots_enabled', '__return_false' );

// Or override the directives entirely.
add_filter( 'astra_child_seo_ctr_robots_directives', function () {
    return array(
        'max-snippet'       => '320',  // Limit to a one-line snippet.
        'max-image-preview' => 'standard',
        'max-video-preview' => '-1',
    );
} );
```

### 2) Year auto-stamp on evergreen titles

When a singular post title contains a "guide / best / top / دليل / أفضل / كيفية" pattern and either lacks a 4-digit year or carries a stale one (within a configurable window), the module appends or refreshes the year so SERP listings stay current. `"دليل بايثون"` becomes `"دليل بايثون 2026"`; `"Best Python Tools 2024"` becomes `"Best Python Tools 2026"`.

The site-name suffix (e.g. `" | pyarabic.com"`) is preserved - the year is inserted before the separator so titles read `"دليل بايثون 2026 | pyarabic.com"`.

**Off by default** because title rewriting can surprise editors. Enable per-site:

```php
add_filter( 'astra_child_seo_ctr_year_stamp_enabled', '__return_true' );
```

#### Tuning

```php
// Add or replace the evergreen patterns. Match is case-insensitive
// (mb_stripos) and matches anywhere in the title.
add_filter( 'astra_child_seo_ctr_evergreen_patterns', function ( $patterns ) {
    $patterns[] = 'تطور';
    $patterns[] = 'roundup';
    return $patterns;
} );

// How stale a mentioned year can be before we refresh it.
// Default: 3 - matches 2023, 2024, 2025 against current 2026.
add_filter( 'astra_child_seo_ctr_year_window', fn() => 5 );

// sprintf-style format used when no year is present.
// Default: ' %d' so titles get "Title 2026". Use ' (%d)' for parentheses.
add_filter( 'astra_child_seo_ctr_year_format', fn() => ' (%d)' );
```

### 3) Reading-time signal

Counts the words in the post once (Arabic-aware via Unicode word splitting), caches the result on `_astra_child_reading_minutes` post meta, and exposes the value in two places:

- **Article schema:** `timeRequired` ISO 8601 duration (`PT5M`) added to Yoast's `wpseo_schema_article` node so search engines can surface the hint.
- **Twitter card:** `twitter:label1` / `twitter:data1` so Twitter and LinkedIn previews show "وقت القراءة: 5 دقيقة".

The cache is busted automatically on `save_post` and `deleted_post` so the value never lags behind real content.

#### Tuning

```php
// Disable just this feature.
add_filter( 'astra_child_seo_ctr_reading_time_enabled', '__return_false' );

// Words-per-minute for the calculation. Default 200 (typical adult reader).
// Arabic averages a bit lower; 180 is a defensible value.
add_filter( 'astra_child_seo_ctr_reading_wpm', fn() => 180 );

// Translate / re-brand the Twitter label.
add_filter( 'astra_child_seo_ctr_reading_label', fn() => 'مدة القراءة' );

// Translate the value template. Receives sprintf( $template, $minutes ).
add_filter( 'astra_child_seo_ctr_reading_value_format', fn() => '%d دقيقة قراءة' );
```

### Disable the whole module

```php
add_filter( 'astra_child_seo_module_serp_ctr', '__return_false' );
```

### Verifying

1. View page source on a single post and check `<head>`:
   - `<meta name="robots" content="...max-image-preview:large...">` is present.
   - `<meta name="twitter:label1" content="وقت القراءة">` and `twitter:data1` are present.
2. Open the URL in [Rich Results Test](https://search.google.com/test/rich-results) and confirm the Article node shows `timeRequired: PT<N>M`.
3. (If year stamp is enabled) confirm the rendered `<title>` reflects the current year.
4. Re-crawl the URL in Search Console after deploying. CTR changes typically show up in Performance reports within 2-4 weeks.





## Reading-time badge (frontend)

Visible counterpart of the reading-time signal that the [SERP CTR](#serp-ctr) module already pushes to Twitter cards (`twitter:label1`/`twitter:data1`) and Article schema (`timeRequired`). Without this module, social previews promise the reader "5 دقيقة قراءة" but the actual page doesn't show it - this module closes that loop.

### Where it renders

By default it sits **inside Astra's single-post meta line**, next to the date and author:

```
23 مايو · أحمد · ⏱ 5 دقيقة قراءة
```

If the parent theme isn't Astra (`ASTRA_THEME_VERSION` not defined), the module falls back automatically to prepending a small line at the top of the post content. You can also force either placement explicitly:

```php
// Force the meta line (Astra) - silently no-ops on non-Astra themes.
add_filter( 'astra_child_seo_reading_badge_position', fn() => 'meta' );

// Force prepended-to-content placement (works on every theme).
add_filter( 'astra_child_seo_reading_badge_position', fn() => 'content' );
```

### Where the data comes from

The module reads the cached value from the `serp-ctr` module's
`astra_child_seo_ctr_reading_time()` helper - no extra database hit, no
extra calculation per pageview. If `serp-ctr` is disabled, this module
silently no-ops because there is no real reading-time number to display.

### Customization

```php
// Disable the whole module.
add_filter( 'astra_child_seo_module_reading_time_badge', '__return_false' );

// Or disable just at runtime (e.g. on a specific category).
add_filter( 'astra_child_seo_reading_badge_enabled', function ( $enabled ) {
    return ! in_category( 'shorts' );
} );

// Translate or restyle the format (sprintf, %d is minutes).
add_filter( 'astra_child_seo_reading_badge_format', fn() => 'وقت القراءة: %d دقيقة' );
add_filter( 'astra_child_seo_reading_badge_format', fn() => '⏱ %d min read' );

// Hide the badge for very short posts. Default 1; set to 2 to hide
// 1-minute reads, 3 to hide 1-2 minute reads, etc.
add_filter( 'astra_child_seo_reading_badge_min_minutes', fn() => 2 );

// Show on pages and a custom post type as well.
add_filter( 'astra_child_seo_reading_badge_post_types', fn() => array( 'post', 'page', 'tutorial' ) );
```

### Styling

The badge ships with ~250 bytes of inline CSS and uses Astra's CSS custom
properties when available so it inherits the active palette:

```css
.ac-reading-time { color: var(--ast-global-color-3, #5b5b5b); font-size: .875em; }
.ac-reading-time::before { content: "·"; margin: 0 .5em; opacity: .7; }
.ac-reading-time-line { margin: 0 0 1em; }
```

Override these from the child theme's `style.css` to match a different
design system. The badge carries a `data-minutes="5"` attribute so CSS
can target specific ranges (e.g. add an icon for long reads).

### Behavior notes

- Renders only on `is_singular( 'post' )` by default. Filterable.
- Skipped in admin, REST, AJAX, feeds, AMP, and the Customizer preview by virtue of `is_singular()` being false in those contexts.
- The CSS prints once per request and only when the badge will actually render.
- A request-scoped `$GLOBALS` flag prevents the meta filter and the content fallback from both rendering the badge.
