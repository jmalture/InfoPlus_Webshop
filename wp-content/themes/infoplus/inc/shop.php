<?php
/**
 * Shop views: product listings (shop, product categories, the Namensschilder
 * page, search) and the cart / checkout pages.
 *
 * Templates: woocommerce/archive-product.php, woocommerce/content-product.php,
 * woocommerce/loop/loop-start.php + loop-end.php, page-namensschilder.php,
 * search.php, wc-page.php, template-parts/product-card.php,
 * template-parts/shop-head.php. Styles: assets/css/archive.css and
 * assets/css/checkout.css (bundles from ip_template_bundle()).
 *
 * Everything that touches WooCommerce is guarded, so the theme still renders
 * while WooCommerce is inactive (preview, local dev).
 */

defined( 'ABSPATH' ) || exit;

/** Products per listing page. 21 models today, so one page; pagination stays for growth. */
const IP_SHOP_PER_PAGE = 24;

/*
 * Defaults for the listing texts. ip_setting() falls back to these while the
 * options page has no field for them (add fields with the same names to the
 * "Website-Einstellungen" group to make them editable).
 */
add_filter( 'ip_setting_defaults', function ( $defaults ) {
	return array_merge( $defaults, array(
		'shop_kicker'         => 'Namensschilder',
		// Old page 15902: "Namensschilder von infoplus". Split so the second half renders magenta (DESIGN.md 2).
		'shop_heading'        => 'Namensschilder von',
		'shop_heading_accent' => 'infoplus',
		'shop_intro'          => 'Alle Modelle im Überblick. Wählen Sie ein Modell und gestalten Sie Ihr Namensschild online.',
		// Old search template 15663, "nothing found" message.
		'search_empty_text'   => 'Wir können anscheinend nicht finden, wonach Sie suchen.',
	) );
} );

/* ── Conditionals and URLs ──────────────────────────────────────────────── */

/** True on the "Namensschilder" overview page (15902), which is a normal page rendered by page-namensschilder.php. */
function ip_shop_is_plates_page() {
	if ( ! is_page() ) {
		return false;
	}
	$page = get_queried_object();
	return $page instanceof WP_Post && apply_filters( 'ip_shop_plates_page_slug', 'namensschilder' ) === $page->post_name;
}

/** True on every view that lists product cards. */
function ip_shop_is_listing() {
	if ( is_search() || ip_shop_is_plates_page() ) {
		return true;
	}
	return function_exists( 'is_shop' ) && ( is_shop() || is_product_taxonomy() );
}

/** True on the cart page and on the checkout form (not on its endpoints such as order-received). */
function ip_shop_is_cart_or_checkout_form() {
	if ( ! function_exists( 'is_cart' ) ) {
		return false;
	}
	return is_cart() || ( is_checkout() && ! is_wc_endpoint_url() );
}

/**
 * URL of the product overview: the published Namensschilder page, else the
 * WooCommerce shop page, else /namensschilder/.
 */
function ip_shop_plates_url() {
	static $url = null;
	if ( null === $url ) {
		$page = get_page_by_path( apply_filters( 'ip_shop_plates_page_slug', 'namensschilder' ), OBJECT, array( 'page' ) );
		if ( $page && 'publish' === $page->post_status ) {
			$url = get_permalink( $page );
		} elseif ( function_exists( 'wc_get_page_permalink' ) && wc_get_page_id( 'shop' ) > 0 ) {
			$url = wc_get_page_permalink( 'shop' );
		} else {
			$url = home_url( '/namensschilder/' );
		}
	}
	return $url;
}

/** "21 Modelle" / "1 Modell". */
function ip_shop_count_label( $count ) {
	return sprintf( /* translators: %d: number of products */ _n( '%d Modell', '%d Modelle', (int) $count, 'infoplus' ), (int) $count );
}

/** Model name without the trailing "Namensschild(er)" (also the "Namesschilder" typo in some category names). */
function ip_shop_short_name( $name ) {
	$short = trim( preg_replace( '/\s+Name(n)?s(s)?child(er)?$/iu', '', (string) $name ) );
	return '' === $short ? (string) $name : $short;
}

/* ── Data for the templates ─────────────────────────────────────────────── */

/**
 * Card text: the product's short description as plain text, else the
 * description of its product category (several products have no short
 * description yet). Trimmed to about two lines; CSS clamps the rest.
 */
function ip_shop_card_text( $product_id ) {
	$text = '';
	if ( function_exists( 'wc_get_product' ) ) {
		$product = wc_get_product( $product_id );
		$text    = $product ? $product->get_short_description() : '';
	} else {
		$text = get_post_field( 'post_excerpt', $product_id );
	}
	if ( '' === trim( wp_strip_all_tags( (string) $text ) ) && taxonomy_exists( 'product_cat' ) ) {
		$default = (int) get_option( 'default_product_cat' );
		foreach ( (array) get_the_terms( $product_id, 'product_cat' ) as $term ) {
			if ( $term instanceof WP_Term && (int) $term->term_id !== $default && '' !== trim( $term->description ) ) {
				$text = $term->description;
				break;
			}
		}
	}
	$text = trim( preg_replace( '/\s+/u', ' ', wp_strip_all_tags( strip_shortcodes( (string) $text ) ) ) );
	return wp_trim_words( $text, 22, ' …' );
}

/**
 * Category pills: product categories with products, without the default
 * "Uncategorized" category, in the order set under Products > Categories.
 *
 * @return WP_Term[]
 */
function ip_shop_category_terms() {
	if ( ! taxonomy_exists( 'product_cat' ) ) {
		return array();
	}
	$terms = get_terms( array(
		'taxonomy'   => 'product_cat',
		'hide_empty' => true,
		'exclude'    => array_filter( array( (int) get_option( 'default_product_cat' ) ) ),
		'orderby'    => 'menu_order', // WooCommerce term order, then name.
	) );
	return is_wp_error( $terms ) ? array() : $terms;
}

/**
 * Texts for the section head of the current listing.
 *
 * @param string $mode 'archive' or 'search'.
 * @return array{kicker:string,title:string,accent:string,intro:string,intro_html:bool,count:int,term:int}
 */
function ip_shop_head_data( $mode, $total ) {
	$data = array(
		'kicker'     => (string) ip_setting( 'shop_kicker' ),
		'title'      => (string) ip_setting( 'shop_heading' ),
		'accent'     => (string) ip_setting( 'shop_heading_accent' ),
		'intro'      => (string) ip_setting( 'shop_intro' ),
		'intro_html' => false,
		'count'      => (int) $total,
		'term'       => 0,
	);
	if ( 'search' === $mode ) {
		$data['kicker'] = __( 'Suche', 'infoplus' );
		$data['title']  = __( 'Suchergebnisse für', 'infoplus' );
		/* translators: %s: search term */
		$data['accent'] = sprintf( __( '„%s“', 'infoplus' ), get_search_query( false ) );
		$data['intro']  = $total
			/* translators: %s: "3 Modelle" */
			? sprintf( __( '%s gefunden.', 'infoplus' ), ip_shop_count_label( $total ) )
			: '';
		return $data;
	}
	if ( function_exists( 'is_product_taxonomy' ) && is_product_taxonomy() ) {
		$term = get_queried_object();
		if ( $term instanceof WP_Term ) {
			$data['title']      = $term->name;
			$data['accent']     = '';
			$data['intro']      = wc_format_content( wp_kses_post( $term->description ) );
			$data['intro_html'] = true;
			$data['term']       = (int) $term->term_id;
		}
	}
	return $data;
}

/* ── WooCommerce default output the theme replaces ──────────────────────── */

add_action( 'wp', function () {
	if ( ! function_exists( 'WC' ) ) {
		return;
	}
	// Woo wraps archives in its own #primary/#main; header.php already opens <main id="main">.
	remove_action( 'woocommerce_before_main_content', 'woocommerce_output_content_wrapper', 10 );
	remove_action( 'woocommerce_after_main_content', 'woocommerce_output_content_wrapper_end', 10 );
	// Listings show the theme's section head; category pages get ip_breadcrumb() there.
	remove_action( 'woocommerce_before_main_content', 'woocommerce_breadcrumb', 20 );
	remove_action( 'woocommerce_shop_loop_header', 'woocommerce_product_taxonomy_archive_header', 10 );
	remove_action( 'woocommerce_archive_description', 'woocommerce_taxonomy_archive_description', 10 );
	// 21 models: a result count and a sort dropdown add noise, not help.
	remove_action( 'woocommerce_before_shop_loop', 'woocommerce_result_count', 20 );
	remove_action( 'woocommerce_before_shop_loop', 'woocommerce_catalog_ordering', 30 );
	// The theme has no sidebar; Woo would load the deprecated theme-compat sidebar.php.
	remove_action( 'woocommerce_sidebar', 'woocommerce_get_sidebar', 10 );
	// Own empty state (archive-product.php / search.php).
	remove_action( 'woocommerce_no_products_found', 'wc_no_products_found', 10 );
	// Loop items render template-parts/product-card.php; Woo's own pieces would duplicate it.
	// Prices stay out on purpose (owner decision; variable products with up to 1,380 variations).
	remove_action( 'woocommerce_before_shop_loop_item', 'woocommerce_template_loop_product_link_open', 10 );
	remove_action( 'woocommerce_before_shop_loop_item_title', 'woocommerce_show_product_loop_sale_flash', 10 );
	remove_action( 'woocommerce_before_shop_loop_item_title', 'woocommerce_template_loop_product_thumbnail', 10 );
	remove_action( 'woocommerce_shop_loop_item_title', 'woocommerce_template_loop_product_title', 10 );
	remove_action( 'woocommerce_after_shop_loop_item_title', 'woocommerce_template_loop_rating', 5 );
	remove_action( 'woocommerce_after_shop_loop_item_title', 'woocommerce_template_loop_price', 10 );
	remove_action( 'woocommerce_after_shop_loop_item', 'woocommerce_template_loop_product_link_close', 5 );
	remove_action( 'woocommerce_after_shop_loop_item', 'woocommerce_template_loop_add_to_cart', 10 );
} );

add_filter( 'loop_shop_per_page', fn() => IP_SHOP_PER_PAGE, 20 );

/** Pagination arrows with a spoken label; fewer page numbers on phones. */
add_filter( 'woocommerce_pagination_args', function ( $args ) {
	$args['prev_text'] = '<span class="ip-sr">' . esc_html__( 'Vorherige Seite', 'infoplus' ) . '</span>' . ip_shop_arrow( 'prev' );
	$args['next_text'] = '<span class="ip-sr">' . esc_html__( 'Nächste Seite', 'infoplus' ) . '</span>' . ip_shop_arrow( 'next' );
	$args['end_size']  = 1;
	$args['mid_size']  = 1;
	return $args;
} );

/** Arrow SVG (24 px grid, 1.75 stroke like inc/icons.php). Decorative. */
function ip_shop_arrow( $dir = 'next' ) {
	$path = 'prev' === $dir ? 'M19 12H5M11 6l-6 6 6 6' : 'M5 12h14M13 6l6 6-6 6';
	return '<svg class="ip-icon ip-icon--arrow" viewBox="0 0 24 24" width="24" height="24" fill="none" stroke="currentColor" stroke-width="1.75" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" focusable="false"><path d="' . esc_attr( $path ) . '"/></svg>';
}

/*
 * WooCommerce's classic shop CSS floats li.product into fixed-width columns
 * and restyles notices; the listing uses its own grid (archive.css). Cart and
 * checkout are blocks with their own block CSS. Order-received and order-pay
 * still render classic templates, so the classic CSS stays there.
 */
add_filter( 'woocommerce_enqueue_styles', function ( $styles ) {
	if ( ip_shop_is_listing() || ip_shop_is_cart_or_checkout_form() ) {
		unset( $styles['woocommerce-layout'], $styles['woocommerce-smallscreen'], $styles['woocommerce-general'] );
	}
	return $styles;
} );

/* ── Search: products only (as the old child theme did) ─────────────────── */

add_action( 'pre_get_posts', function ( $query ) {
	if ( is_admin() || ! $query->is_main_query() || ! $query->is_search() ) {
		return;
	}
	if ( ! post_type_exists( 'product' ) ) {
		return;
	}
	$query->set( 'post_type', array( 'product' ) );
	$query->set( 'posts_per_page', IP_SHOP_PER_PAGE );
	// "/?s=" (without post_type=product) is not a Woo query, so Woo does not hide
	// products set to "hidden"/"search: no" itself; apply its visibility rules here.
	if ( function_exists( 'WC' ) && WC()->query && ! $query->get( 'wc_query' ) ) {
		$tax_query = $query->get( 'tax_query' );
		$query->set( 'tax_query', WC()->query->get_tax_query( is_array( $tax_query ) ? $tax_query : array(), true ) );
	}
} );

/* ── Body classes ───────────────────────────────────────────────────────── */

add_filter( 'body_class', function ( $classes ) {
	if ( ip_shop_is_listing() ) {
		$classes[] = 'ip-view-listing';
	}
	if ( function_exists( 'is_cart' ) && ( is_cart() || is_checkout() ) ) {
		$classes[] = 'ip-view-checkout';
	}
	return $classes;
} );

/* ── Assets ─────────────────────────────────────────────────────────────── */

add_action( 'wp_enqueue_scripts', function () {
	// The Namensschilder page is a normal page, so ip_template_bundle() gives it no bundle.
	if ( ip_shop_is_plates_page() && function_exists( 'ip_enqueue' ) ) {
		ip_enqueue( 'ip-archive', 'assets/css/archive.css', array( 'ip-main' ) );
	}
	// Port of the child theme: the variation gallery plugin is not used on cart/checkout.
	if ( function_exists( 'is_cart' ) && ( is_cart() || is_checkout() ) ) {
		wp_dequeue_script( 'woo-variation-gallery' );
		wp_dequeue_script( 'woo-variation-gallery-slick' );
		wp_dequeue_style( 'woo-variation-gallery' );
	}
}, 99 );

/*
 * The Infoplus Suite plugin (UserOrders module) styles cart and checkout
 * itself: woo-pages.css plus two <style>/<script> blocks in wp_head with
 * !important colours (#e91e8c), a system font and a JS min-height fix. The
 * theme owns that styling now (checkout.css), so those are switched off on
 * the cart and on the checkout form. The plugin's markup and behaviour
 * (empty-cart panel, quantity badge, thank-you content) stay. Return false
 * from the filter to keep the plugin's styles.
 */
add_action( 'wp', function () {
	if ( ! ip_shop_is_cart_or_checkout_form() || ! apply_filters( 'ip_shop_replace_suite_styles', true ) ) {
		return;
	}
	global $wp_filter;
	if ( empty( $wp_filter['wp_head'] ) ) {
		return;
	}
	foreach ( $wp_filter['wp_head']->callbacks as $priority => $callbacks ) {
		foreach ( $callbacks as $callback ) {
			$fn = $callback['function'];
			if ( is_array( $fn ) && is_object( $fn[0] ) && in_array( $fn[1], array( 'enqueueCartStyles', 'enqueueCheckoutStyles' ), true )
				&& str_starts_with( get_class( $fn[0] ), 'Infoplus\\Suite\\' ) ) {
				remove_action( 'wp_head', $fn, $priority );
			}
		}
	}
	add_action( 'wp_enqueue_scripts', function () {
		wp_dequeue_style( 'infoplus-woo-pages' );
	}, 100 );
} );

/* ── Cart and checkout pages ────────────────────────────────────────────── */

/*
 * Cart and checkout render through wc-page.php, found by WooCommerce page
 * setting rather than by slug. Priority 20 runs after Elementor's page
 * template filter (11), so the checkout page, saved with Elementor's
 * "elementor_header_footer" template, gets the theme file as well.
 */
add_filter( 'template_include', function ( $template ) {
	if ( function_exists( 'is_cart' ) && ( is_cart() || is_checkout() ) && is_page() ) {
		$ours = locate_template( array( 'wc-page.php' ) );
		if ( $ours ) {
			return $ours;
		}
	}
	return $template;
}, 20 );

/** True when the page is built with Elementor (then the_content() would print Elementor's HTML). */
function ip_shop_is_elementor_page( $post_id ) {
	return 'builder' === get_post_meta( $post_id, '_elementor_edit_mode', true );
}

/**
 * HTML of the cart or checkout area for wc-page.php.
 *
 * - A page edited with the block editor whose content has the Woo block is
 *   rendered through the_content() as written (cart page 27 today).
 * - A page built with Elementor (checkout page 28 today) cannot go through
 *   the_content(): Elementor's the_content filter swaps the content for its
 *   own builder HTML, and the post_content copy Elementor keeps has the block
 *   comments without their wrapper <div>s, so the block cannot mount. Then the
 *   block markup the old Elementor page held (woocommerce/checkout-block.html,
 *   verbatim from the text-editor widget of page 28) is rendered directly,
 *   after the [infoplus_order_received] shortcode as on the old page.
 * - Without a block: the classic shortcode.
 *
 * do_shortcode() runs after do_blocks() because the Checkout block returns the
 * literal "[woocommerce_checkout]" on endpoints (order-received, order-pay) in
 * classic themes and relies on the_content's shortcode pass.
 *
 * @param string $type 'cart' or 'checkout'.
 */
function ip_shop_wc_page_html( $type ) {
	$post  = get_post();
	$block = 'woocommerce/' . $type;
	if ( $post && ! ip_shop_is_elementor_page( $post->ID ) && has_block( $block, $post ) ) {
		ob_start();
		the_content();
		return ob_get_clean();
	}
	$file   = locate_template( array( "woocommerce/{$type}-block.html" ) );
	$markup = $file ? (string) file_get_contents( $file ) : ''; // phpcs:ignore WordPress.WP.AlternativeFunctions -- local theme file.
	$markup = (string) preg_replace( '/^\s*<!--(?!\s*\/?wp:).*?-->\s*/s', '', $markup ); // The file's own note, not a block.
	if ( '' === trim( $markup ) ) {
		$markup = 'cart' === $type ? '[woocommerce_cart]' : '[woocommerce_checkout]';
	}
	if ( 'checkout' === $type && shortcode_exists( 'infoplus_order_received' ) ) {
		$markup = '[infoplus_order_received]' . $markup;
	}
	return do_shortcode( do_blocks( $markup ) );
}

/** Heading of the cart / checkout view (endpoint titles come from WooCommerce, translated). */
function ip_shop_wc_page_title() {
	if ( function_exists( 'is_wc_endpoint_url' ) && is_wc_endpoint_url() && WC()->query ) {
		$title = WC()->query->get_endpoint_title( WC()->query->get_current_endpoint() );
		if ( $title ) {
			return $title;
		}
	}
	return is_cart() ? __( 'Warenkorb', 'infoplus' ) : __( 'Kasse', 'infoplus' );
}

/*
 * German strings. The WooCommerce de_DE language pack is installed and its
 * block translations load on staging (checked 2026-10-20: the cart prints
 * wc-cart-checkout-base / wc-cart-block-frontend translations, "Add coupons"
 * -> "Gutscheine hinzufügen"), so the old MutationObserver translator is not
 * ported. What stays English is text SAVED in the cart page content when the
 * block was inserted on an English admin: the headings of the cross-sell and
 * empty-cart inner blocks. Translated here until the page is edited once in
 * the block editor (then delete this filter).
 */
add_filter( 'render_block_woocommerce/cart', function ( $html ) {
	return strtr( $html, array(
		'You may be interested in…'       => 'Das könnte Sie auch interessieren',
		'You may be interested in&hellip;' => 'Das könnte Sie auch interessieren',
		'Your cart is currently empty!'   => 'Ihr Warenkorb ist leer.',
		'>Browse store<'                  => '>Zu den Namensschildern<',
		'>New in store<'                  => '>Neu im Shop<',
	) );
} );

/* ── B2B: order reference / PO number at checkout ───────────────────────── */

/** Field ID of the optional "Ihre Bestellreferenz / PO-Nummer" (WooCommerce additional checkout field). */
const IP_SHOP_PO_FIELD = 'infoplus/po-number';

/** Maximum length of the order reference. */
const IP_SHOP_PO_MAX = 50;

/*
 * DESIGN.md, "Cart and checkout": an optional "Ihre Bestellreferenz /
 * PO-Nummer" field, shown on invoice and order e-mail.
 *
 * Registered through WooCommerce's additional checkout fields API
 * (woocommerce_register_additional_checkout_field, WooCommerce 8.9+, Checkout
 * block only). Location "order": the field renders in the block's "Zusätzliche
 * Bestellinformationen" section (checkout-additional-information-block, which
 * the checkout markup in woocommerce/checkout-block.html contains). WooCommerce
 * itself saves it as order meta "_wc_other/infoplus/po-number" and shows it
 * with its label in the admin order screen, on the order confirmation and in
 * the order e-mails (WC_Emails::order_meta / customer details). The PDF
 * invoice gets it from the hook below. A no-op where the function does not
 * exist (older WooCommerce, WooCommerce inactive).
 */
add_action( 'woocommerce_init', function () {
	if ( ! function_exists( 'woocommerce_register_additional_checkout_field' ) ) {
		return;
	}
	try {
		woocommerce_register_additional_checkout_field( array(
			'id'                => IP_SHOP_PO_FIELD,
			'label'             => 'Ihre Bestellreferenz / PO-Nummer',
			'optionalLabel'     => 'Ihre Bestellreferenz / PO-Nummer (optional)',
			'location'          => 'order',
			'type'              => 'text',
			'required'          => false,
			'attributes'        => array(
				'maxLength'    => IP_SHOP_PO_MAX,
				'autocomplete' => 'off',
			),
			'sanitize_callback' => function ( $value ) {
				$value = trim( sanitize_text_field( (string) $value ) );
				return function_exists( 'mb_substr' ) ? mb_substr( $value, 0, IP_SHOP_PO_MAX ) : substr( $value, 0, IP_SHOP_PO_MAX );
			},
			'validate_callback' => function ( $value ) {
				$length = function_exists( 'mb_strlen' ) ? mb_strlen( (string) $value ) : strlen( (string) $value );
				if ( $length > IP_SHOP_PO_MAX ) {
					/* translators: %d: maximum number of characters */
					return new WP_Error( 'infoplus_po_number_length', sprintf( __( 'Die Bestellreferenz darf höchstens %d Zeichen lang sein.', 'infoplus' ), IP_SHOP_PO_MAX ) );
				}
			},
		) );
	} catch ( \Throwable $e ) {
		// Registration refused (e.g. the ID exists already): the checkout works without the field.
		if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
			error_log( 'Infoplus theme: PO number field not registered: ' . $e->getMessage() ); // phpcs:ignore WordPress.PHP.DevelopmentFunctions
		}
	}
} );

/** The order reference of an order, '' when none was entered. */
function ip_shop_po_number( $order ) {
	if ( ! $order instanceof WC_Order ) {
		return '';
	}
	$value = '';
	$class = '\Automattic\WooCommerce\Blocks\Domain\Services\CheckoutFields';
	if ( class_exists( '\Automattic\WooCommerce\Blocks\Package' ) && class_exists( $class ) ) {
		try {
			$value = \Automattic\WooCommerce\Blocks\Package::container()->get( $class )->get_field_from_object( IP_SHOP_PO_FIELD, $order, 'other' );
		} catch ( \Throwable $e ) {
			$value = '';
		}
	}
	if ( '' === (string) $value ) {
		$value = $order->get_meta( '_wc_other/' . IP_SHOP_PO_FIELD );
	}
	return trim( (string) $value );
}

/*
 * PDF Invoices & Packing Slips for WooCommerce (WP Overnight, 5.16.3 on
 * staging): its Simple template fires wpo_wcpdf_after_order_data inside the
 * order data table (invoice and packing slip), the same place the plugin adds
 * its own due date row. One more row with the reference. Filter
 * ip_shop_po_pdf_documents to choose the document types.
 */
add_action( 'wpo_wcpdf_after_order_data', function ( $document_type = null, $order = null ) {
	if ( ! in_array( $document_type, (array) apply_filters( 'ip_shop_po_pdf_documents', array( 'invoice', 'packing-slip' ) ), true ) ) {
		return;
	}
	$po = ip_shop_po_number( $order );
	if ( '' === $po ) {
		return;
	}
	printf(
		'<tr class="po-number"><th>%s</th><td>%s</td></tr>',
		esc_html__( 'Bestellreferenz:', 'infoplus' ),
		esc_html( $po )
	);
}, 10, 2 );
