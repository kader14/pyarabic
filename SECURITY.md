# Security Policy

## Reporting a vulnerability

If you discover a security issue in this repository or in the deployed
[pyarabic.com](https://pyarabic.com/) site, **please do not open a public
GitHub issue or pull request**. Public disclosure before a fix is shipped
puts every site running this theme at risk.

Instead, send a private report to the maintainer:

- **Email:** [dep2g2@gmail.com](mailto:dep2g2@gmail.com)
- **Subject prefix:** `[security] pyarabic - <short description>`

Please include, where possible:

- A clear description of the issue and the impact you observed.
- Steps to reproduce, including the affected URL or file path.
- Proof-of-concept output (logs, screenshots, request/response pairs).
- The version of `astra-child` from `astra-child/style.css`, the
  WordPress version, and the Astra parent-theme version.
- Your preferred name and contact for the credit line, if you would like
  one.

You should receive an acknowledgement within **5 business days**. We will
work with you to validate the report, develop a fix, and coordinate
disclosure.

## Disclosure timeline

We aim to ship fixes on the following schedule, measured from
acknowledgement:

| Severity                                                              | Target fix window |
| --------------------------------------------------------------------- | ----------------- |
| Critical (RCE, authentication bypass, mass data exposure)             | within 7 days     |
| High (privilege escalation, stored XSS, SSRF)                         | within 14 days    |
| Medium (reflected XSS, CSRF on non-state-changing endpoints, low-risk info disclosure) | within 30 days    |
| Low (theoretical issues, hardening suggestions)                       | best-effort       |

Please give the maintainer a reasonable window before publishing details.
We will credit researchers in the relevant `CHANGELOG.md` entry and
release notes unless asked to keep the report anonymous.

## Supported versions

Only the most recent release of the `astra-child` theme is supported with
fixes. See [`CHANGELOG.md`](CHANGELOG.md) for the current version
(declared in `astra-child/style.css` and the `ASTRA_CHILD_THEME_VERSION`
constant in `astra-child/functions.php`).

## Out of scope

The following are **not** in scope for this repository's security policy.
Report them to the upstream project instead:

- Vulnerabilities in WordPress core → <https://wordpress.org/support/security/>.
- Vulnerabilities in the Astra parent theme → <https://wpastra.com/support/>.
- Vulnerabilities in Yoast SEO Free → <https://yoast.com/security/>.
- Configuration issues on a third-party site that copied this theme
  without adapting it to their environment.
