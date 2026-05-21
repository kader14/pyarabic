# Astra Child Theme

A minimal child theme for [Astra](https://wordpress.org/themes/astra/) used by **pyarabic.com**.

It exists for one main reason: keep site-specific customizations (like the Google AdSense loader script) outside of the parent theme so that updating Astra never wipes them out.

## What it does

- Loads the parent Astra stylesheet, then the child stylesheet on top.
- Injects the Google AdSense loader (`adsbygoogle.js`) into `<head>` via the `wp_head` action instead of editing `header.php`.
- Skips the AdSense script in admin, feeds, and AMP endpoints.

## Installation

1. Copy the `astra-child/` folder into your WordPress installation under:
   ```
   wp-content/themes/astra-child/
   ```
2. Make sure the parent theme **Astra** is also installed under `wp-content/themes/astra/`.
3. Log in to WordPress admin, go to **Appearance -> Themes**, find *Astra Child*, and click **Activate**.
4. Restore the parent `header.php` to its original Astra contents (the inline AdSense `<script>` is no longer needed there). A clean copy is provided at the repository root as `header.php`.

## Changing the AdSense client ID

Edit `functions.php` and update the constant:

```php
define( 'ASTRA_CHILD_ADSENSE_CLIENT_ID', 'ca-pub-XXXXXXXXXXXXXXXX' );
```

Or override it at runtime from another plugin without modifying the theme:

```php
add_filter( 'astra_child_adsense_client_id', function () {
    return 'ca-pub-XXXXXXXXXXXXXXXX';
} );
```

To disable the AdSense script entirely, return an empty string from that filter.

## Adding a screenshot

WordPress shows `screenshot.png` on the Themes page. Drop a 1200x900 PNG named `screenshot.png` into this folder when you have one ready.

## File overview

| File           | Purpose                                                         |
| -------------- | --------------------------------------------------------------- |
| `style.css`    | Child theme header (declares `Template: astra`) and custom CSS. |
| `functions.php`| Enqueues stylesheets and prints the AdSense loader.             |
| `README.md`    | This file.                                                      |
