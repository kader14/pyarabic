<?php
/**
 * Early Hints / preload of critical resources.
 *
 * Speeds up Largest Contentful Paint by telling the browser which
 * resources it should start fetching before the full HTML body has
 * been parsed.
 *
 * Three levels of effect, all from the same configuration:
 *
 *   1. <link rel="preload"> tags injected at the very top of <head>.
 *      Works in every browser and on every host - no infrastructure
 *      required.
 *
 *   2. HTTP "Link:" response headers carrying the same hints. Many
 *      origin servers and CDNs (Apache/mod_http2, Cloudflare, Fastly)
 *      use these to either preload eagerly or to materialize a real
 *      103 Early Hints interim response.
 *
 *   3. When the host already supports 103 Early Hints (e.g. Cloudflare
 *      with the feature enabled), the same Link: header is automatically
 *      promoted to a 103 sent before the 200, giving the browser a head
 *      start while PHP is still building the page.
 *
 * The module never sends more than a small, configurable number of
 * hints to avoid over-pushing.
 *
 * @package Astra Child
 * @since   1.7.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Decide whether the current request should emit early hints.
 *
 * Skipped on admin, REST, AJAX, feeds, AMP, and the Customizer
 * preview - either because they are not user-facing pages or because
 * the host strips response headers in those contexts.
 *
 * @return bool
 */
function astra_child_seo_eh_should_run() {
	if ( is_admin() || is_feed() || wp_doing_ajax() ) {
		return false;
	}

	if ( defined( 'REST_REQUEST' ) && REST_REQUEST ) {
		return false;
	}

	if ( function_exists( 'is_amp_endpoint' ) && is_amp_endpoint() ) {
		return false;
	}

	if ( function_exists( 'is_customize_preview' ) && is_customize_preview() ) {
		return false;
	}

	/**
	 * Master toggle for the early-hints module.
	 *
	 * @param bool $enabled
	 */
	return (bool) apply_filters( 'astra_child_seo_early_hints_enabled', true );
}

/**
 * Resolve the URL of the parent theme stylesheet.
 *
 * Astra ships a minified stylesheet on most installs; fall back to the
 * unminified one when it is missing.
 *
 * @return string
 */
function astra_child_seo_eh_parent_stylesheet_url() {
	$dir = trailingslashit( get_template_directory() );
	$uri = trailingslashit( get_template_directory_uri() );

	if ( file_exists( $dir . 'style.min.css' ) ) {
		return $uri . 'style.min.css';
	}

	return $uri . 'style.css';
}

/**
 * Build the list of resources to advertise.
 *
 * Each entry is an associative array with at minimum href + as keys
 * and may include type / imagesrcset / imagesizes / crossorigin.
 *
 * @return array<int,array<string,string>>
 */
function astra_child_seo_eh_resources() {
	$resources = array();

	$resources[] = array(
		'href' => astra_child_seo_eh_parent_stylesheet_url(),
		'as'   => 'style',
	);

	$child_url = get_stylesheet_directory_uri() . '/style.css';
	if ( $child_url !== astra_child_seo_eh_parent_stylesheet_url() ) {
		$resources[] = array(
			'href' => $child_url,
			'as'   => 'style',
		);
	}

	if ( is_singular() && has_post_thumbnail() ) {
		$thumb_id = get_post_thumbnail_id();
		$src      = wp_get_attachment_image_src( $thumb_id, 'large' );

		if ( is_array( $src ) && ! empty( $src[0] ) ) {
			$resource = array(
				'href' => $src[0],
				'as'   => 'image',
			);

			$srcset = wp_get_attachment_image_srcset( $thumb_id, 'large' );
			if ( $srcset ) {
				$resource['imagesrcset'] = $srcset;
				$resource['imagesizes']  = '(max-width: 1200px) 100vw, 1200px';
			}

			$resources[] = $resource;
		}
	}

	$max = (int) apply_filters( 'astra_child_seo_early_hints_max', 5 );

	/**
	 * Filter the resource list before headers are sent / tags are
	 * rendered. Use this hook to add fonts, hero images for archive
	 * pages, or remove entries.
	 *
	 * @param array<int,array<string,string>> $resources
	 */
	$resources = (array) apply_filters( 'astra_child_seo_early_hints_resources', $resources );

	if ( count( $resources ) > $max && $max > 0 ) {
		$resources = array_slice( $resources, 0, $max );
	}

	return $resources;
}

/**
 * Format a resource entry as a single Link: header value.
 *
 * @param array<string,string> $resource Resource definition.
 * @return string Empty string when the entry is invalid.
 */
function astra_child_seo_eh_format_link_header( $resource ) {
	if ( empty( $resource['href'] ) || empty( $resource['as'] ) ) {
		return '';
	}

	$parts = array(
		sprintf( '<%s>', esc_url_raw( $resource['href'] ) ),
		'rel=preload',
		'as=' . preg_replace( '/[^a-z]/', '', strtolower( $resource['as'] ) ),
	);

	if ( ! empty( $resource['type'] ) ) {
		$parts[] = sprintf( 'type="%s"', str_replace( '"', '', $resource['type'] ) );
	}

	if ( ! empty( $resource['imagesrcset'] ) ) {
		$parts[] = sprintf( 'imagesrcset="%s"', str_replace( '"', '', $resource['imagesrcset'] ) );
	}

	if ( ! empty( $resource['imagesizes'] ) ) {
		$parts[] = sprintf( 'imagesizes="%s"', str_replace( '"', '', $resource['imagesizes'] ) );
	}

	if ( 'font' === $resource['as'] || ! empty( $resource['crossorigin'] ) ) {
		$parts[] = 'crossorigin';
	}

	return implode( '; ', $parts );
}

/**
 * Send Link: HTTP headers as early as possible.
 *
 * Hooks on template_redirect so the response headers go out before any
 * output is produced. Cloudflare with Early Hints enabled will promote
 * these into a 103 interim response automatically.
 *
 * @return void
 */
function astra_child_seo_eh_send_headers() {
	if ( ! astra_child_seo_eh_should_run() ) {
		return;
	}

	if ( headers_sent() ) {
		return;
	}

	$resources = astra_child_seo_eh_resources();
	if ( empty( $resources ) ) {
		return;
	}

	$values = array();
	foreach ( $resources as $resource ) {
		$value = astra_child_seo_eh_format_link_header( $resource );
		if ( '' !== $value ) {
			$values[] = $value;
		}
	}

	if ( empty( $values ) ) {
		return;
	}

	// Single combined header, comma-separated, per RFC 8288.
	header( 'Link: ' . implode( ', ', $values ), false );
}
add_action( 'template_redirect', 'astra_child_seo_eh_send_headers', 1 );

/**
 * Print <link rel="preload"> tags at the very top of <head>.
 *
 * Hooks on wp_head at priority 1 so the tags appear before everything
 * else, maximizing how early the browser can act on them. Works on every
 * host even when HTTP-level hints are stripped or unsupported.
 *
 * @return void
 */
function astra_child_seo_eh_print_preload_tags() {
	if ( ! astra_child_seo_eh_should_run() ) {
		return;
	}

	$resources = astra_child_seo_eh_resources();
	if ( empty( $resources ) ) {
		return;
	}

	foreach ( $resources as $resource ) {
		if ( empty( $resource['href'] ) || empty( $resource['as'] ) ) {
			continue;
		}

		$attrs = array(
			'rel'  => 'preload',
			'href' => $resource['href'],
			'as'   => $resource['as'],
		);

		if ( ! empty( $resource['type'] ) ) {
			$attrs['type'] = $resource['type'];
		}
		if ( ! empty( $resource['imagesrcset'] ) ) {
			$attrs['imagesrcset'] = $resource['imagesrcset'];
		}
		if ( ! empty( $resource['imagesizes'] ) ) {
			$attrs['imagesizes'] = $resource['imagesizes'];
		}
		if ( 'font' === $resource['as'] || ! empty( $resource['crossorigin'] ) ) {
			$attrs['crossorigin'] = 'anonymous';
		}

		$html = '<link';
		foreach ( $attrs as $name => $value ) {
			if ( 'href' === $name ) {
				$html .= sprintf( ' %s="%s"', esc_attr( $name ), esc_url( $value ) );
			} else {
				$html .= sprintf( ' %s="%s"', esc_attr( $name ), esc_attr( $value ) );
			}
		}
		$html .= " />\n";

		echo $html; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- attributes already escaped above.
	}
}
add_action( 'wp_head', 'astra_child_seo_eh_print_preload_tags', 1 );
