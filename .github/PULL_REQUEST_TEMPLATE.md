<!--
Thanks for contributing! Fill out each section so the reviewer doesn't have
to dig. Delete sections that don't apply.
See CONTRIBUTING.md for the full workflow and coding standards.
-->

## What changed

<!-- One bullet per concrete change. Group by file or feature. -->

-

## Why

<!-- The user-visible problem this fixes or the improvement it delivers. -->

## How it was tested

<!--
List the manual steps you ran. SEO / schema / performance changes need
external validation; CI does not catch those.
-->

- [ ] `php -l` clean on every changed PHP file
- [ ] Manually tested on a staging WordPress with Astra parent and Yoast SEO Free active
- [ ] *(if schema)* validated in [Rich Results Test](https://search.google.com/test/rich-results)
- [ ] *(if perf)* measured with [PageSpeed Insights](https://pagespeed.web.dev/) before and after
- [ ] *(if `robots.txt`)* checked in Search Console robots.txt Tester
- [ ] *(if a new SEO module)* registered in `astra-child/inc/seo/loader.php`

## Trade-offs and known limitations

<!--
Optional, but call out anything reviewers should weigh: deferred work,
edge cases, third-party plugin compatibility, browser quirks, etc.
-->

## Checklist

- [ ] `CHANGELOG.md` updated under `[Unreleased]`
- [ ] `astra-child/README.md` updated if a public filter, constant, shortcode, or settings page changed
- [ ] All new behavior is filterable (`astra_child_*` prefix) and documented
- [ ] Output is escaped (`esc_html`, `esc_attr`, `esc_url`, etc.) and skips admin / REST / AJAX / feed / AMP / Customizer when appropriate
- [ ] String operations on user content use `mb_*` functions so Arabic counts correctly
- [ ] Branch name follows `feat/...`, `fix/...`, `docs/...`, `chore/...`, or `refactor/...`
- [ ] Commit messages are imperative and explain the *why*

## Related issues

<!-- Closes #123, refs #456 -->
