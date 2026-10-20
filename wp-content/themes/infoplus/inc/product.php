<?php
/**
 * Single product page: the configurator built from the Infoplus Suite
 * components (model selector, variation dropdowns, product image, info box,
 * bulk price table) in the theme's layout.
 *
 * Templates: woocommerce/content-single-product.php and
 * template-parts/product/*.php. Styles and script: assets/css/product.css and
 * assets/js/product.js (bundle "product" in inc/assets.php).
 *
 * Division of labour: the Suite owns the shop logic, the component markup and
 * the scripts that drive them (product-state.js and friends, the bulk
 * calculator, the editor link). The theme owns layout, texts around the
 * components and design. Elements the Suite scripts look for carry data
 * attributes: data-ip-action="editor|inquiry" on the two action buttons,
 * data-ip-qty="value|minus|plus|unit-price|total-price" and data-ip-qty-row
 * on the quantity row (supported by the Suite since 0.10.26 / 1.1.1).
 *
 * What the old Elementor template (2190) did and this page keeps:
 * - No form.variations_form. The Elementor template never rendered one (checked
 *   on the staging Atlantis page, 2026-10-20). The Suite runs from its own state
 *   (window.infoplusProductData, product-state.js); every Suite use of the Woo
 *   form is guarded by "if the form exists". The filter ip_product_variations_form
 *   turns the native form on (rendered hidden).
 * - Add to cart for "buyable" models: the old page used the Suite's admin-ajax
 *   action infoplus_add_to_cart. This page renders a small WooCommerce
 *   add-to-cart form instead (template-parts/product/actions.php, form.cart with
 *   add-to-cart, variation_id, attribute_* and quantity), so the add goes through
 *   WC_Form_Handler: validation filters, cart item data filters, notices and the
 *   cart redirect setting apply. assets/js/product.js fills it from the Suite state.
 * - No woocommerce_single_product_summary. Elementor's template did not fire it
 *   either. On staging it would add Germanized price notes, PayPal / Klarna /
 *   WooPayments buttons and messaging, and Woo's JSON-LD. The filter
 *   ip_product_summary_hook turns it on.
 *
 * Everything that touches WooCommerce or the Suite is guarded, so the theme
 * still renders while either plugin is inactive.
 */

defined( 'ABSPATH' ) || exit;

/* ── Switches ───────────────────────────────────────────────────────────── */

/** True when the Infoplus Suite theme helpers (feature/view-renderer) are loaded. */
function ip_product_has_suite() {
	return function_exists( 'infoplus_suite_product_image' );
}

/**
 * Render WooCommerce's native variations form (inside a hidden wrapper)?
 * Off by default, see the file header. With it on, Woo embeds its own
 * variation JSON for products up to 200 variations, and plugins that listen to
 * the form (Germanized, Woo Variation Gallery, the Suite's Woo fallbacks) wake up.
 */
function ip_product_renders_wc_form() {
	return (bool) apply_filters( 'ip_product_variations_form', false );
}

/** Fire woocommerce_single_product_summary (with Woo's own callbacks removed)? Off by default. */
function ip_product_fires_summary_hook() {
	return (bool) apply_filters( 'ip_product_summary_hook', false );
}

/*
 * Page weight. The Suite prints the variation list of the product
 * (window.allWooVariations, one row per variation: 1380 rows, about 194 KB on
 * Atlantis, about 260 KB after LiteSpeed base64-encodes inline scripts) for
 * variation-filter.js, which only works on a form.variations_form. Without the
 * form nothing reads it, so the theme asks the Suite to skip it. Needs Suite
 * 0.10.26 (filter infoplus_suite_product_context_variations); older Suite
 * versions ignore the filter and print the list as before.
 */
add_filter( 'infoplus_suite_product_context_variations', function ( $include ) {
	return ip_product_renders_wc_form() ? $include : false;
} );

/* ── WooCommerce default output this page replaces ──────────────────────── */

add_action( 'wp', function () {
	if ( ! function_exists( 'is_product' ) || ! is_product() ) {
		return;
	}
	// Gallery: the Suite's product image replaces Woo's gallery and sale flash.
	remove_action( 'woocommerce_before_single_product_summary', 'woocommerce_show_product_sale_flash', 10 );
	remove_action( 'woocommerce_before_single_product_summary', 'woocommerce_show_product_images', 20 );
	// Summary (only fired when ip_product_summary_hook is on): the theme prints title,
	// short description and the configurator itself; no price (owner decision for
	// variable products with up to 1,380 variations), no meta, no sharing.
	remove_action( 'woocommerce_single_product_summary', 'woocommerce_template_single_title', 5 );
	remove_action( 'woocommerce_single_product_summary', 'woocommerce_template_single_rating', 10 );
	remove_action( 'woocommerce_single_product_summary', 'woocommerce_template_single_price', 10 );
	remove_action( 'woocommerce_single_product_summary', 'woocommerce_template_single_excerpt', 20 );
	remove_action( 'woocommerce_single_product_summary', 'woocommerce_template_single_add_to_cart', 30 );
	remove_action( 'woocommerce_single_product_summary', 'woocommerce_template_single_meta', 40 );
	remove_action( 'woocommerce_single_product_summary', 'woocommerce_template_single_sharing', 50 );
	// Below the summary: the old page showed the description only (no tabs, upsells, related).
	remove_action( 'woocommerce_after_single_product_summary', 'woocommerce_output_product_data_tabs', 10 );
	remove_action( 'woocommerce_after_single_product_summary', 'woocommerce_upsell_display', 15 );
	remove_action( 'woocommerce_after_single_product_summary', 'woocommerce_output_related_products', 20 );
} );

/* ── Assets ─────────────────────────────────────────────────────────────── */

/*
 * Woo's classic CSS styles div.product, its gallery and form.cart, none of
 * which this page renders. Notices are styled in product.css.
 */
add_filter( 'woocommerce_enqueue_styles', function ( $styles ) {
	if ( function_exists( 'is_product' ) && is_product() && ! ip_product_renders_wc_form() ) {
		unset( $styles['woocommerce-layout'], $styles['woocommerce-smallscreen'], $styles['woocommerce-general'] );
	}
	return $styles;
} );

add_action( 'wp_enqueue_scripts', function () {
	if ( ! function_exists( 'is_product' ) || ! is_product() ) {
		return;
	}
	// Suite component styles into the head (the Suite also enqueues them on
	// is_product(); View::enqueue() is safe to call twice).
	if ( class_exists( '\Infoplus\Suite\View' ) ) {
		foreach ( array( 'product-image', 'model-selector', 'variation-dropdowns', 'product-info-box', 'bulk-price-table' ) as $component ) {
			\Infoplus\Suite\View::enqueue( $component );
		}
	}
	if ( ip_product_renders_wc_form() ) {
		return;
	}
	/*
	 * Scripts that only work on Woo's variations form or Woo's gallery, which
	 * this page does not render (staging loads all of them on product pages).
	 * Woo Variation Gallery swaps the Woo gallery, Germanized updates p.price and
	 * unit prices inside .product from the form, wc-add-to-cart-variation is the
	 * form itself. Suite scripts stay: the Elementor page shares them.
	 */
	$handles = apply_filters( 'ip_product_dequeue_handles', array(
		'woo-variation-gallery',
		'woo-variation-gallery-slider',
		'wc-gzd-add-to-cart-variation',
		'wc-gzd-unit-price-observer',
		'wc-gzd-unit-price-observer-queue',
		'wc-add-to-cart-variation',
		'wc-single-product',
		'wc-zoom',
		'wc-flexslider',
		'wc-photoswipe',
		'wc-photoswipe-ui-default',
	) );
	foreach ( $handles as $handle ) {
		wp_dequeue_script( $handle );
	}
	wp_dequeue_style( 'woo-variation-gallery' );
	wp_dequeue_style( 'photoswipe' );
	wp_dequeue_style( 'photoswipe-default-skin' );
}, 100 );

/** Body class for the product view (sticky action bar spacing). */
add_filter( 'body_class', function ( $classes ) {
	if ( function_exists( 'is_product' ) && is_product() ) {
		$classes[] = 'ip-view-product';
	}
	return $classes;
} );

/* ── Texts ──────────────────────────────────────────────────────────────── */

/**
 * Texts around the components, ported 1:1 from the Elementor template 2190
 * (German, as on the live page). Filter ip_product_texts to change them.
 *
 * @return array<string,mixed>
 */
function ip_product_texts() {
	static $texts = null;
	if ( null === $texts ) {
		$texts = apply_filters( 'ip_product_texts', array(
			// Quantity row (HTML widget ea7913e, headings c31afda, 5e58bb4, text 8d6d274).
			'qty_label'      => 'Menge',
			'unit_label'     => 'Einzelpreis',
			'total_label'    => 'Gesamtpreis*',
			'price_zero'     => '0,00 €',
			'price_note'     => 'Alle Preise inkl. MwSt., zzgl. Versandkosten',
			// Buttons #editor (c0deeb0) and #anfrage (3d00462).
			'editor_label'   => 'Produkt gestalten',
			'editor_sr'      => '(öffnet den Online-Editor in einem neuen Tab)',
			'inquiry_label'  => 'Individuelle Anfrage',
			// Buy form for "buyable" models (actions.php). The label is the one the Suite used.
			'cart_label'     => 'In den Warenkorb',
			'cart_busy'      => 'Wird hinzugefügt …',
			'choose_options' => 'Bitte wählen Sie zuerst alle Optionen aus.',
			// One line under the buttons per order type (data-ip-order-hint).
			'hint_editable'    => 'Gestalten Sie Ihr Schild im Online-Editor. Von dort legen Sie es in den Warenkorb.',
			'hint_buyable'     => 'Direkt bestellbar: Menge wählen und in den Warenkorb legen.',
			'hint_requestable' => 'Dieses Modell fertigen wir auf Anfrage. Sie erhalten ein unverbindliches Angebot.',
			// Section below the description (containers fcd96be, d809b0f, aa678ab).
			'request_title'  => 'Individuelle Anfrage',
			'request_text'   => 'Ihr Logo wird zum dreidimensionalen Hingucker – wir fertigen Ihre Wunschkontur präzise nach Ihren Vorgaben.',
			'request_items'  => array(
				array( 'icon' => 'gift', 'title' => 'KOSTENLOSE MUSTER', 'text' => 'Auf Anfrage verfügbar' ),
				array( 'icon' => 'clipboard', 'title' => 'UNVERBINDLICHE ANFRAGE', 'text' => 'Keine Verpflichtung' ),
				array( 'icon' => 'user-check', 'title' => 'PERSÖNLICHE BERATUNG', 'text' => 'Wir beraten Sie individuell' ),
			),
			'request_cta'    => 'Jetzt Sonderkontur anfragen',
			// Suite component args as set in template 2190.
			'placeholder'    => 'Wähle eine Option',
			'info_heading'   => 'Informationen:',
			'sku_label'      => 'SKU:',
			'unit'           => 'Stück',
			'delivery_pre'   => 'max.',
			'delivery_post'  => 'Arbeitstage',
			'ab_label'       => 'ab',
		) );
	}
	return $texts;
}

/** One text from ip_product_texts(). */
function ip_product_text( $key ) {
	$texts = ip_product_texts();
	return $texts[ $key ] ?? '';
}

/* ── Data helpers ───────────────────────────────────────────────────────── */

/**
 * Attributes the variation dropdowns show, as set in template 2190 (widget
 * fc20ec9). Attributes a product does not use are skipped by the Suite.
 */
function ip_product_dropdown_attributes() {
	return apply_filters( 'ip_product_dropdown_attributes', array(
		'pa_befestigung', 'pa_farbe', 'pa_fenster', 'pa_flachenfarbe', 'pa_format',
		'pa_frontscheibe', 'pa_holzart', 'pa_oberflache', 'pa_tragerfarbe',
	) );
}

/**
 * Category for the kicker and the breadcrumb: the Yoast primary category,
 * else the first category that is not the default "Unkategorisiert".
 */
function ip_product_category( $product ) {
	if ( ! $product || ! taxonomy_exists( 'product_cat' ) ) {
		return null;
	}
	$id = $product->get_id();
	if ( class_exists( 'WPSEO_Primary_Term' ) ) {
		$primary = (int) ( new WPSEO_Primary_Term( 'product_cat', $id ) )->get_primary_term();
		$term    = $primary ? get_term( $primary, 'product_cat' ) : null;
		if ( $term instanceof WP_Term ) {
			return $term;
		}
	}
	$default = (int) get_option( 'default_product_cat' );
	foreach ( (array) get_the_terms( $id, 'product_cat' ) as $term ) {
		if ( $term instanceof WP_Term && (int) $term->term_id !== $default ) {
			return $term;
		}
	}
	return null;
}

/** Breadcrumb pairs: Home / Namensschilder / category / product. */
function ip_product_breadcrumb_items( $product ) {
	$plates = function_exists( 'ip_shop_plates_url' ) ? ip_shop_plates_url() : home_url( '/namensschilder/' );
	$items  = array(
		array( __( 'Home', 'infoplus' ), home_url( '/' ) ),
		array( __( 'Namensschilder', 'infoplus' ), $plates ),
	);
	$term = ip_product_category( $product );
	if ( $term ) {
		$link = get_term_link( $term );
		if ( ! is_wp_error( $link ) ) {
			$items[] = array( $term->name, $link );
		}
	}
	$items[] = array( $product->get_name(), '' );
	return $items;
}

/**
 * Inquiry target. The old page opened the Elementor popup 14241
 * ("Special Wishes" form). Until the theme has its own inquiry modal, the
 * button goes to the Kontakt page with the product name as ?produkt=.
 * Filter ip_product_inquiry_url to change it.
 */
function ip_product_inquiry_url( $product ) {
	$page = get_page_by_path( 'kontakt', OBJECT, array( 'page' ) );
	$base = ( $page && 'publish' === $page->post_status ) ? get_permalink( $page ) : home_url( '/kontakt/' );
	$url  = add_query_arg( 'produkt', rawurlencode( $product->get_name() ), $base );
	return (string) apply_filters( 'ip_product_inquiry_url', $url, $product );
}

/**
 * The selection of a buy form POST for this product, so product.js can restore
 * it after Woo renders the page again (error notice, or success without the
 * cart redirect). Null for every other request. Values are only echoed back
 * into a data attribute, never trusted: Woo validates the POST itself.
 *
 * @return array{model:string,attributes:array<string,string>,variation:int,qty:int}|null
 */
function ip_product_buy_form_posted( $product ) {
	// phpcs:disable WordPress.Security.NonceVerification.Missing -- read-only echo of Woo's own (nonce-less) add-to-cart POST.
	if ( empty( $_POST['infoplus_buy_form'] ) || ! isset( $_POST['add-to-cart'] ) || absint( $_POST['add-to-cart'] ) !== $product->get_id() ) {
		return null;
	}
	$attributes = array();
	foreach ( $_POST as $key => $value ) {
		if ( is_string( $key ) && 0 === strpos( $key, 'attribute_' ) && is_scalar( $value ) ) {
			$attributes[ sanitize_title( substr( $key, 10 ) ) ] = sanitize_title( wp_unslash( (string) $value ) );
		}
	}
	$posted = array(
		'model'      => $attributes['pa_model'] ?? '',
		'attributes' => $attributes,
		'variation'  => isset( $_POST['variation_id'] ) ? absint( $_POST['variation_id'] ) : 0,
		'qty'        => isset( $_POST['quantity'] ) ? max( 1, absint( $_POST['quantity'] ) ) : 1,
	);
	// phpcs:enable
	return $posted;
}

/**
 * Outline icons used only on this page (24 px grid, 1.75 stroke, like
 * inc/icons.php). Falls back to ip_icon() for the shared set. Decorative.
 */
function ip_product_icon( $name ) {
	$paths = array(
		'minus'      => '<path d="M5 12h14"/>',
		'plus'       => '<path d="M12 5v14M5 12h14"/>',
		'arrow'      => '<path d="M5 12h14M13 6l6 6-6 6"/>',
		'cart'       => '<path d="M3 4h2l2.4 11.2a2 2 0 0 0 2 1.6h7.7a2 2 0 0 0 2-1.5L21 8H6.2"/><circle cx="10" cy="20" r="1.3"/><circle cx="17" cy="20" r="1.3"/>',
		'mail'       => '<rect x="3" y="5" width="18" height="14" rx="2"/><path d="M3 7l9 6 9-6"/>',
		'box'        => '<path d="M21 8l-9-5-9 5v8l9 5 9-5z"/><path d="M3 8l9 5 9-5M12 13v8"/>',
		'tag'        => '<path d="M3 12V4a1 1 0 0 1 1-1h8l9 9-9 9z"/><circle cx="7.5" cy="7.5" r="1.3"/>',
		'gift'       => '<rect x="3" y="8" width="18" height="4" rx="1"/><path d="M5 12v8h14v-8M12 8v12M12 8S10.5 3.5 8 4.5 9 8 12 8zM12 8s1.5-4.5 4-3.5S15 8 12 8z"/>',
		'clipboard'  => '<rect x="5" y="4" width="14" height="17" rx="2"/><path d="M9 4V3h6v1M9 10h6M9 14h6M9 18h3"/>',
		'user-check' => '<circle cx="10" cy="8" r="4"/><path d="M3 21c1.3-3.6 3.9-5.5 7-5.5 1.3 0 2.5.3 3.5.9M15.5 19l2 2 4-4"/>',
	);
	if ( ! isset( $paths[ $name ] ) ) {
		return function_exists( 'ip_icon' ) ? ip_icon( $name ) : '';
	}
	return '<svg class="ip-icon ip-icon--' . esc_attr( $name ) . '" viewBox="0 0 24 24" width="24" height="24" fill="none" stroke="currentColor" stroke-width="1.75" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" focusable="false">' . $paths[ $name ] . '</svg>';
}
