# Changelog

All notable changes to this repository are documented here.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.1.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

The version below refers to the `astra-child` theme (`Version:` header in
`astra-child/style.css`). The repository also ships a top-level `header.php`
and `robots.txt` that are deployed alongside the theme.

## [Unreleased]

### Added

- **Auto Table of Contents** module (`inc/seo/toc.php`). Builds a nested
  `<ol>` from h2/h3 headings on long single posts (>= 500 words and >= 3
  matching headings, both filterable). Adds anchor `id` attributes via
  `sanitize_title()` so Arabic headings get clean Arabic slugs, with
  duplicate auto-suffix. Renders an accessible `<details>` / `<summary>`
  block with smooth scroll and configurable scroll padding for sticky
  headers. `[toc]` shortcode bypasses thresholds and places the TOC
  exactly where it appears in the content.
  ([#11](https://github.com/kader14/pyarabic/pull/11))
- **Query-strings stripping** module (`inc/seo/query-strings.php`). Removes
  cache-buster parameters (`ver`, `v`, `rev`, `cb`, `ts`, `_`) from local
  CSS/JS URLs via `style_loader_src` / `script_loader_src` so edge caches
  and CDNs can fully cache them. Skipped on admin, REST, AJAX, feeds, AMP,
  and the Customizer preview. Only touches same-host URLs; third-party CDN
  query strings are preserved.
  ([#10](https://github.com/kader14/pyarabic/pull/10))
- **Early Hints** module (`inc/seo/early-hints.php`). Emits resource hints
  in three coordinated layers from a single configuration source: top-of-
  `<head>` `<link rel="preload">` tags, `Link:` HTTP response headers, and
  real HTTP `103 Early Hints` when behind Cloudflare with the feature
  enabled. Default hints cover Astra parent stylesheet, child stylesheet,
  and the post's featured image at `large` size with `imagesrcset` /
  `imagesizes`. Capped at five entries by default.
  ([#9](https://github.com/kader14/pyarabic/pull/9))
- **Internal linking** module (`inc/seo/internal-linking.php`). Two
  complementary tools: a `Settings -> Internal Links` admin page that lets
  admins maintain `keyword | URL` pairs (first-occurrence linking via
  `DOMDocument`, capped at 5 auto-links per post, never replaces text
  inside `<a>`, `<code>`, headings, etc.); and an auto-appended related-
  posts `<aside>` after every single post (4 posts sharing tags or
  categories, transient-cached for one hour, busted on `save_post` /
  `deleted_post`). Manual `[related_posts]` shortcode suppresses the
  auto-append for that post.
  ([#8](https://github.com/kader14/pyarabic/pull/8))
- **Article schema** module (`inc/seo/article-schema.php`). Layers advanced
  fields on top of Yoast's `wpseo_schema_article` at priority 99: switches
  `@type` to `NewsArticle` when the post is detected as news (by category
  list, post type list, or `_is_news` post meta); adds `articleSection`,
  `keywords`, multi-resolution `image[]`, separate `thumbnailUrl`,
  `speakable`, `copyrightYear`, `copyrightHolder`, and Arabic-aware
  `wordCount`.
  ([#7](https://github.com/kader14/pyarabic/pull/7))
- **Breadcrumbs** module (`inc/seo/breadcrumbs.php`). Fixes the Search
  Console error `Missing field "itemListElement" (in "BreadcrumbList")`
  emitted by Yoast on edge cases. Suppresses Astra's breadcrumb
  microdata so Yoast's JSON-LD becomes the single source of truth.
  Validates `wpseo_schema_breadcrumb` at priority 99 and either rebuilds
  the trail from the current request (post categories, page hierarchy,
  taxonomy ancestors, search, author, archive, 404) or drops the
  BreadcrumbList from the `@graph` when no meaningful trail exists.
  ([#6](https://github.com/kader14/pyarabic/pull/6))
- **Meta description** module (`inc/seo/meta-description.php`). Hardens
  Yoast's meta description output by stripping shortcodes, HTML, emoji,
  decoding entities, removing leading "By Author |" patterns, and
  collapsing whitespace. Trims to 155 characters using `mb_strlen` /
  `mb_substr` so Arabic characters are counted correctly. Smart fallback
  chain (excerpt -> first paragraph -> term description -> author bio ->
  site tagline) when Yoast emits an empty or short value. Optional
  `save_post` autofill of `_yoast_wpseo_metadesc` (off by default) and
  admin notice for posts missing a description.
  ([#5](https://github.com/kader14/pyarabic/pull/5))
- **Critical CSS** module (`inc/seo/critical-css.php`) plus
  `assets/critical/default.css`. Inlines a small above-the-fold stylesheet
  in `<head>` and defers all other stylesheets via the
  `media="print" onload="this.media='all'"` swap with a `<noscript>`
  fallback. Per-template files: `home.css`, `single.css`, `page.css`,
  `archive.css`, falling back to `default.css`. Skipped in admin, feeds,
  AMP, Customizer preview, and for logged-in editors so editing always
  shows the full design.
  ([#4](https://github.com/kader14/pyarabic/pull/4))
- **Robots** module (`inc/seo/robots.php`) and physical `robots.txt` at
  the repository root. The physical file declares the Yoast sitemap,
  blocks `wp-admin/`, `xmlrpc.php`, internal search, replytocom, and
  tracking-parameter URLs, allows assets so Google can render pages, and
  blocks aggressive SEO scrapers (Semrush, Ahrefs, MJ12, etc.). The
  module mirrors the same rules through the `robots_txt` filter when no
  physical file exists, and skips the `Sitemap:` line if Yoast has
  already added one.
  ([#3](https://github.com/kader14/pyarabic/pull/3))
- **SEO module suite** baseline: resource hints (preconnect / dns-prefetch
  for AdSense and Google Fonts), WordPress emoji loader removal, deferred
  non-critical JS via `script_loader_tag`, forced `ar_AR` Open Graph
  locale, larger Arabic excerpt, RTL-friendly breadcrumb separator,
  hreflang fallback, Yoast filter tweaks (low-priority metabox, default
  Twitter handle, default OG image, Article publisher schema), `[faq]` /
  `[faq_item]` shortcodes that emit FAQPage JSON-LD plus a styled
  accordion, and an auto alt-text fallback for images using the
  attachment title or post title.
  ([#2](https://github.com/kader14/pyarabic/pull/2))

### Changed

- Every SEO module is opt-out via filters
  (`astra_child_load_seo`, `astra_child_seo_module_<slug>`), and most
  per-feature behaviors expose dedicated filters. See `astra-child/README.md`
  for the full matrix.

## [1.0.0] - 2026-05-21

### Added

- New `astra-child/` child theme (`style.css`, `functions.php`,
  `README.md`) declaring `Template: astra`. Loads the parent stylesheet
  then the child stylesheet on top.
- Google AdSense loader (`adsbygoogle.js`) injected via `wp_head` instead
  of being hard-coded in the parent `header.php`. Configurable through the
  `ASTRA_CHILD_ADSENSE_CLIENT_ID` constant or the
  `astra_child_adsense_client_id` filter, and skipped in admin, feeds, and
  AMP endpoints.
  ([#1](https://github.com/kader14/pyarabic/pull/1))

### Changed

- Restored the parent `header.php` to its clean Astra defaults; the inline
  AdSense `<script>` is no longer needed there.
  ([#1](https://github.com/kader14/pyarabic/pull/1))

[Unreleased]: https://github.com/kader14/pyarabic/compare/v1.0.0...HEAD
[1.0.0]: https://github.com/kader14/pyarabic/releases/tag/v1.0.0
