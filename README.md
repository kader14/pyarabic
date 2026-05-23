# pyarabic

Source code for the **[pyarabic.com](https://pyarabic.com/)** WordPress site:
an Arabic-language WordPress installation built on the [Astra](https://wordpress.org/themes/astra/)
parent theme and [Yoast SEO Free](https://wordpress.org/plugins/wordpress-seo/),
with a custom child theme that adds Google AdSense, performance work, and a
suite of technical-SEO modules tailored to Arabic content.

> هذا المستودع يحوي **القالب الفرعي (`astra-child`)** المستخدم في موقع
> pyarabic.com، إضافةً إلى ملف `robots.txt` ونسخة نظيفة من `header.php` للقالب
> الأب. الهدف: إبقاء كل التخصيصات في مكان واحد، خارج القالب الأب، حتى لا
> تُمحى عند تحديثه.

## Repository layout

```
.
├── astra-child/          WordPress child theme (the deployable artifact)
│   ├── style.css         Theme header + custom CSS
│   ├── functions.php     Enqueues styles, prints AdSense, loads SEO modules
│   ├── README.md         Theme features and full filter reference
│   ├── inc/seo/          One PHP file per SEO concern (loader pattern)
│   └── assets/critical/  Per-template critical CSS files
├── header.php            Reference copy of the parent Astra header.php
├── robots.txt            Physical robots file for the document root
├── phpcs.xml.dist        WordPress Coding Standards ruleset
├── CHANGELOG.md          Release notes (Keep a Changelog)
├── CONTRIBUTING.md       Workflow, coding standards, release process
├── CODE_OF_CONDUCT.md    Contributor Covenant 2.1
├── SECURITY.md           Responsible disclosure policy
├── LICENSE               GPL-2.0-or-later
└── .github/              Issue and PR templates, CODEOWNERS, CI workflow
```

## Quick start

End-to-end installation steps live in
[`astra-child/README.md`](astra-child/README.md). The short version:

1. Install the parent **Astra** theme on the WordPress site.
2. Copy the `astra-child/` directory into `wp-content/themes/astra-child/`.
3. Activate **Astra Child** under **Appearance → Themes**.
4. (Optional) Upload the root `robots.txt` to the document root, and replace
   the parent `wp-content/themes/astra/header.php` with the clean copy at
   the repository root.

## What lives where

| File / Directory                                       | Purpose                                                          |
| ------------------------------------------------------ | ---------------------------------------------------------------- |
| [`astra-child/README.md`](astra-child/README.md)       | Theme features, every SEO module, and the full filter reference  |
| [`CHANGELOG.md`](CHANGELOG.md)                         | Release notes                                                    |
| [`CONTRIBUTING.md`](CONTRIBUTING.md)                   | Branching, coding standards, testing, release flow               |
| [`CODE_OF_CONDUCT.md`](CODE_OF_CONDUCT.md)             | Community standards                                              |
| [`SECURITY.md`](SECURITY.md)                           | How to report a security issue                                   |
| [`LICENSE`](LICENSE)                                   | GPL-2.0-or-later (inherited from the Astra parent theme)         |
| [`.github/`](.github/)                                 | PR template, issue forms, CODEOWNERS, CI lint workflow           |
| [`phpcs.xml.dist`](phpcs.xml.dist)                     | PHP_CodeSniffer config (WordPress standard) used by CI and locally |

## SEO modules at a glance

The child theme ships fourteen toggleable SEO modules under
`astra-child/inc/seo/`. Each one is enabled by default and can be turned off
via a `astra_child_seo_module_<slug>` filter without editing the theme.
See [`astra-child/README.md`](astra-child/README.md) for the full list and
filter reference.

| Module             | What it does                                                                  |
| ------------------ | ----------------------------------------------------------------------------- |
| `performance`      | `preconnect` / `dns-prefetch`, disables emojis, defers non-critical JS        |
| `arabic`           | Forces `ar_AR` Open Graph locale, RTL breadcrumb separator, hreflang fallback |
| `yoast-tweaks`     | Tightens Yoast metabox priority, default OG image, default Twitter handle     |
| `schema-extras`    | `[faq]` / `[faq_item]` shortcodes that emit FAQPage JSON-LD                   |
| `images`           | Auto alt-text fallback                                                        |
| `robots`           | `robots.txt` filter (sitemap, scraper blocks, low-value endpoint disallows)   |
| `critical-css`     | Inline above-the-fold CSS, defer the rest                                     |
| `meta-description` | Cleans Yoast meta descriptions, smart Arabic-aware fallback chain             |
| `breadcrumbs`      | Repairs Yoast BreadcrumbList, suppresses duplicate Astra microdata            |
| `article-schema`   | Article → NewsArticle, multi-res images, `speakable`, `wordCount`             |
| `internal-linking` | Keyword auto-link admin page + auto-appended related posts block              |
| `early-hints`      | `<link rel=preload>` + `Link:` headers (Cloudflare promotes to HTTP 103)      |
| `query-strings`    | Strips `?ver=` cache-buster query strings from local CSS / JS                 |
| `toc`              | Auto Table of Contents on long single posts (collapsible, no JS required)    |

## Requirements

- WordPress 6.0 or newer
- PHP 7.4 or newer (8.1+ recommended)
- [Astra](https://wordpress.org/themes/astra/) parent theme
- [Yoast SEO Free](https://wordpress.org/plugins/wordpress-seo/) — the SEO
  modules complement Yoast, they don't replace it

## Development

There is no build step. Edit PHP / CSS, refresh the browser. For the full
contribution workflow (branching, coding standards, testing,
`CHANGELOG.md` discipline, release process) read
[`CONTRIBUTING.md`](CONTRIBUTING.md).

A WordPress Coding Standards lint runs on every pull request. To run it
locally before opening the PR:

```bash
composer global require --dev wp-coding-standards/wpcs:^3
phpcs                       # uses phpcs.xml.dist at the repo root
```

## Reporting issues

- **Bug** → open a [Bug report](.github/ISSUE_TEMPLATE/bug_report.yml) issue.
- **Feature** → open a [Feature request](.github/ISSUE_TEMPLATE/feature_request.yml)
  issue first, before writing code, so we can agree on scope.
- **Security** → see [`SECURITY.md`](SECURITY.md). **Do not** open a public
  issue for security problems.

## License

Distributed under the **GNU General Public License v2.0 or later**,
inherited from the Astra parent theme. See [`LICENSE`](LICENSE) for the
full text.
