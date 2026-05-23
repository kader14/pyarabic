# Contributing to pyarabic

Thanks for your interest in improving the site. This repository contains:

- `astra-child/` — the WordPress child theme deployed at
  `wp-content/themes/astra-child/` on pyarabic.com.
- `header.php` — a clean copy of the Astra parent `header.php` (kept here
  as a reference / restore point).
- `robots.txt` — the physical robots file uploaded to the document root.

The rest of this guide explains how to set up, propose changes, and get
them reviewed and merged.

## Code of conduct

Be respectful. Assume good faith. Critique code, not people. Maintainers
may close issues or PRs that don't follow these basics.

## Ways to contribute

- **Bug reports** — open a GitHub issue. Include the WordPress version,
  the Astra parent theme version, the active version of `astra-child`
  (see `astra-child/style.css`), and any relevant browser / device
  information for front-end issues.
- **Feature requests** — open an issue first so we can agree on scope
  before any code is written. SEO modules are designed to *complement*
  Yoast SEO Free, not replace it; proposals that conflict with Yoast will
  usually be rejected.
- **Pull requests** — see the workflow below.

## Repository layout

```
.
├── astra-child/             WordPress child theme (deployable)
│   ├── style.css            Theme header + custom CSS
│   ├── functions.php        Enqueues styles, prints AdSense, loads SEO modules
│   ├── README.md            Per-theme documentation
│   ├── inc/seo/             SEO modules; one file per concern
│   └── assets/critical/     Per-template critical CSS files
├── header.php               Reference copy of the parent Astra header.php
├── robots.txt               Physical robots file for the site root
├── CHANGELOG.md             Release notes (this format is required)
└── CONTRIBUTING.md          You are here
```

## Local setup

There is no build step. The child theme is plain PHP / CSS that runs
directly in WordPress.

A typical local loop:

1. Have a local WordPress install (Local, DevKinsta, `wp-env`, Docker —
   whatever you prefer) with the **Astra** parent theme and **Yoast SEO**
   plugin installed.
2. Symlink or copy this repo's `astra-child/` directory into
   `wp-content/themes/astra-child/`.
3. Activate **Astra Child** under **Appearance -> Themes**.
4. Edit files in this repo; refresh the browser.

For changes that affect `robots.txt`, copy the file to your local document
root and request `/robots.txt` to verify.

For changes to the parent `header.php` baseline, replace
`wp-content/themes/astra/header.php` with the version in this repo's root
to confirm the theme still renders cleanly.

## Branching and commits

- Branch off `main`. Use a short, descriptive name prefixed by intent:
  `feat/...`, `fix/...`, `docs/...`, `refactor/...`, `chore/...`.
  Example: `feat/og-video-tags`, `fix/breadcrumb-empty-trail`.
- Keep commits focused. Each commit should compile and pass linting on
  its own. Avoid "WIP" or "fix typo" commits in the final history; squash
  or rebase before opening the PR.
- Write commit messages in the imperative mood (`Add`, `Fix`, `Remove`),
  keep the subject line under 72 characters, and use the body to explain
  the *why* and any context that isn't obvious from the diff.

## Pull request workflow

1. Open an issue first for anything bigger than a small fix or doc tweak.
2. Open the PR against `main`. Fill in:
   - **What changed** — bullet list.
   - **Why** — the user-visible problem or improvement.
   - **How it was tested** — manual steps, browsers, or external
     validators (Rich Results Test, PageSpeed Insights, robots.txt
     Tester) where relevant.
   - **Trade-offs / known limitations**, if any.
3. Update `CHANGELOG.md` under the `[Unreleased]` section. Use the
   appropriate `Added` / `Changed` / `Fixed` / `Removed` heading. Link
   the PR number once it's open.
4. Update `astra-child/README.md` if you added or changed a public
   filter, constant, shortcode, settings page, or default behavior.
5. Bump `Version:` in `astra-child/style.css` and the
   `ASTRA_CHILD_THEME_VERSION` constant in `astra-child/functions.php`
   only when preparing a release. Patch / minor / major follows
   [SemVer](https://semver.org/) — see "Releases" below.

## Coding standards

The project follows the
[WordPress PHP coding standards](https://developer.wordpress.org/coding-standards/wordpress-coding-standards/php/).
Highlights enforced in code review:

- Always guard module entry points with
  `if ( ! defined( 'ABSPATH' ) ) { exit; }`.
- Prefix every public symbol with `astra_child_` (functions, constants,
  filters, actions, shortcodes, transient keys, post meta keys, option
  names). This avoids collisions with Astra, Yoast, and other plugins.
- Use Yoda conditions (`if ( 'foo' === $bar )`).
- Escape on output: `esc_html`, `esc_attr`, `esc_url`, `wp_kses_post`,
  `esc_textarea`, `esc_js`. Never trust translatable strings either —
  wrap them in `esc_html__` / `esc_attr__`.
- Use `mb_strlen` / `mb_substr` / `mb_strpos` whenever you slice strings
  that may contain Arabic. Byte-based functions corrupt multibyte
  characters.
- Every new behavior should be filterable. Sites should be able to
  disable a module entirely via
  `astra_child_seo_module_<slug>` and tune individual behaviors via
  more specific filters. Document each filter in
  `astra-child/README.md`.
- Skip front-end work in the wrong contexts: admin (`is_admin()`),
  REST (`defined( 'REST_REQUEST' )`), AJAX
  (`wp_doing_ajax()`), feeds (`is_feed()`), AMP
  (`function_exists( 'is_amp_endpoint' ) && is_amp_endpoint()`), and the
  Customizer preview (`is_customize_preview()`).

### Running the linter locally

The repository ships a `phpcs.xml.dist` configured for the WordPress
Coding Standards. The `Lint` GitHub Actions workflow
(`.github/workflows/lint.yml`) runs the same configuration on every
pull request that touches PHP, and posts the findings as inline review
comments via `cs2pr`.

To run it locally before opening a PR:

```bash
# One-time install of phpcs + WPCS into your global Composer setup.
composer global config --no-plugins allow-plugins.dealerdirect/phpcodesniffer-composer-installer true
composer global require --dev \
    "squizlabs/php_codesniffer:^3.10" \
    "wp-coding-standards/wpcs:^3.1" \
    "phpcompatibility/phpcompatibility-wp:^2.1" \
    "dealerdirect/phpcodesniffer-composer-installer:^1.0"

# From the repo root - no flags needed; phpcs.xml.dist drives everything.
phpcs
```

The workflow also runs `php -l` on every PHP file across PHP 7.4, 8.1,
8.2, and 8.3 - that job is **blocking** (a syntax error fails the PR),
while the WPCS job posts inline annotations without blocking so style
violations don't gate hot fixes.

## Adding a new SEO module

The `inc/seo/` directory follows a consistent pattern. To add a new
module called `foo`:

1. Create `astra-child/inc/seo/foo.php` with a top-level guard:

   ```php
   <?php
   /**
    * Foo SEO module.
    *
    * @package Astra Child
    */

   if ( ! defined( 'ABSPATH' ) ) {
       exit;
   }

   // Hooks and helpers go here.
   ```

2. Register the module in `astra-child/inc/seo/loader.php` so it is
   loaded only when its toggle returns true:

   ```php
   if ( apply_filters( 'astra_child_seo_module_foo', true ) ) {
       require_once __DIR__ . '/foo.php';
   }
   ```

3. Document the module in `astra-child/README.md`: what it does, the
   filters it exposes, defaults, and how to verify the behavior on a
   live site.

4. Add a `CHANGELOG.md` entry under `[Unreleased] -> Added`.

## Testing

There is no PHP runtime in this sandbox / CI, so tests are manual:

- **Activate the theme on a staging site** before promoting changes to
  `main`.
- For schema changes, run the affected URLs through
  [Rich Results Test](https://search.google.com/test/rich-results) and
  confirm the JSON-LD validates.
- For performance changes, run [PageSpeed Insights](https://pagespeed.web.dev/)
  before and after and capture the LCP / TBT delta in the PR description.
- For `robots.txt` changes, confirm `curl -sA Googlebot https://pyarabic.com/robots.txt`
  returns the expected output and check Google Search Console's
  robots.txt Tester.
- Run `php -l path/to/changed-file.php` locally to catch syntax errors.

If your change requires a specific verification command, list it
explicitly in the PR description.

## Releases

A release is a new tag plus a `CHANGELOG.md` cut. The flow:

1. Decide on the new version using SemVer:
   - **Patch** (`1.0.0 -> 1.0.1`): bug fixes, doc-only changes,
     internal refactors.
   - **Minor** (`1.0.0 -> 1.1.0`): new modules, new filters, additive
     changes that don't break existing sites.
   - **Major** (`1.0.0 -> 2.0.0`): renamed or removed filters, changed
     defaults that affect rendered output, anything sites must adapt to.
2. Move entries in `CHANGELOG.md` from `[Unreleased]` to a new
   `[X.Y.Z] - YYYY-MM-DD` section.
3. Update the `Version:` header in `astra-child/style.css` and the
   `ASTRA_CHILD_THEME_VERSION` constant in `astra-child/functions.php`.
4. Open a release PR with these changes, merge it, then tag the merge
   commit `vX.Y.Z` and push the tag.

## License

This project inherits the GPLv2-or-later license from the Astra parent
theme. By contributing you agree your contributions are licensed under
the same terms.
