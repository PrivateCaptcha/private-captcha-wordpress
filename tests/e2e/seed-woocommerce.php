<?php
/**
 * Provision WooCommerce guest checkouts with an offline payment method.
 */

foreach (
	array(
		'woocommerce_default_country'                       => 'US:CA',
		'woocommerce_default_customer_address'              => 'base',
		'woocommerce_currency'                              => 'USD',
		'woocommerce_calc_taxes'                            => 'no',
		'woocommerce_enable_guest_checkout'                 => 'yes',
		'woocommerce_enable_signup_and_login_from_checkout' => 'no',
		'woocommerce_coming_soon'                           => 'no',
		'woocommerce_checkout_phone_field'                  => 'hidden',
	) as $option => $value
) {
	update_option( $option, $value );
}

// BACS accepts orders without collecting payment or contacting another service.
// Source: https://woocommerce.com/document/bacs/
update_option(
	'woocommerce_bacs_settings',
	array(
		'enabled'      => 'yes',
		'title'        => 'Direct bank transfer',
		'description'  => 'Pay later by bank transfer.',
		'instructions' => 'Test order: no payment is collected.',
	)
);

// Order email delivery is outside the checkout test; no SMTP setup is required.
foreach ( WC()->mailer()->get_emails() as $email ) {
	$settings            = get_option( $email->get_option_key(), array() );
	$settings['enabled'] = 'no';
	update_option( $email->get_option_key(), $settings );
}

// WooCommerce supplies its complete default Checkout block and required store pages.
WC_Install::create_pages();
$block_checkout_id = wc_get_page_id( 'checkout' );
if ( $block_checkout_id <= 0 || ! has_block( 'woocommerce/checkout', $block_checkout_id ) ) {
	WP_CLI::error( 'The WooCommerce checkout page must contain the Checkout block.' );
}

$legacy_page = get_page_by_path( 'woocommerce-legacy-checkout-e2e' );
$legacy_id   = wp_insert_post(
	array(
		'ID'           => $legacy_page ? $legacy_page->ID : 0,
		'post_type'    => 'page',
		'post_status'  => 'publish',
		'post_name'    => 'woocommerce-legacy-checkout-e2e',
		'post_title'   => 'WooCommerce legacy checkout e2e',
		'post_content' => '[woocommerce_checkout]',
	),
	true
);
if ( is_wp_error( $legacy_id ) ) {
	WP_CLI::error( $legacy_id->get_error_message() );
}

$product = new WC_Product_Simple( wc_get_product_id_by_sku( 'private-captcha-e2e' ) );
$product->set_name( 'E2E virtual product' );
$product->set_slug( 'woocommerce-e2e-product' );
$product->set_sku( 'private-captcha-e2e' );
$product->set_status( 'publish' );
$product->set_regular_price( '10.00' );
$product->set_virtual( true );
$product->set_tax_status( 'none' );
$product->set_stock_status( 'instock' );
$product->set_sold_individually( true );
if ( ! $product->save() ) {
	WP_CLI::error( 'Could not save the WooCommerce fixture product.' );
}

flush_rewrite_rules();
WP_CLI::success( 'WooCommerce fixtures ready: Checkout block, legacy checkout, and E2E virtual product with bank transfer.' );
