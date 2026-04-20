<?php
/**
 * WooCommerce integration for Private Captcha WordPress plugin.
 *
 * @package PrivateCaptchaWP
 */

declare(strict_types=1);

namespace PrivateCaptchaWP\Integrations;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

use PrivateCaptchaWP\Assets;
use PrivateCaptchaWP\SettingsField;
use PrivateCaptchaWP\Widget;
use WP_Error;

/**
 * WooCommerce integration class
 */
class WooCommerce extends AbstractIntegration {

	/**
	 * WooCommerce login form settings field.
	 *
	 * @var SettingsField
	 */
	private SettingsField $login_field;

	/**
	 * WooCommerce registration form settings field.
	 *
	 * @var SettingsField
	 */
	private SettingsField $register_field;

	/**
	 * WooCommerce lost password form settings field.
	 *
	 * @var SettingsField
	 */
	private SettingsField $lost_password_field;

	/**
	 * WooCommerce checkout form (guest) settings field.
	 *
	 * @var SettingsField
	 */
	private SettingsField $checkout_guest_field;

	/**
	 * WooCommerce checkout form (logged-in) settings field.
	 *
	 * @var SettingsField
	 */
	private SettingsField $checkout_logged_in_field;

	/**
	 * Constructor to initialize the integration.
	 *
	 * @param \PrivateCaptchaWP\Client $client The Private Captcha client instance.
	 */
	public function __construct( \PrivateCaptchaWP\Client $client ) {
		parent::__construct( $client );

		$this->plugin_url  = 'https://wordpress.org/plugins/woocommerce/';
		$this->plugin_name = 'WooCommerce';

		$this->login_field = new SettingsField(
			'woocommerce_enable_login',
			'WooCommerce Login Form',
			'Add captcha to WooCommerce My Account login form.',
			'Add captcha to WooCommerce login form'
		);

		$this->register_field = new SettingsField(
			'woocommerce_enable_register',
			'WooCommerce Registration Form',
			'Add captcha to WooCommerce My Account registration form.',
			'Add captcha to WooCommerce registration form'
		);

		$this->lost_password_field = new SettingsField(
			'woocommerce_enable_lost_password',
			'WooCommerce Lost Password Form',
			'Add captcha to WooCommerce lost password form.',
			'Add captcha to WooCommerce lost password form'
		);

		$this->checkout_guest_field = new SettingsField(
			'woocommerce_enable_checkout_guest',
			'WooCommerce Checkout (Guest)',
			'Add captcha to WooCommerce classic checkout form for guest users.',
			'Add captcha to WooCommerce checkout for guests'
		);

		$this->checkout_logged_in_field = new SettingsField(
			'woocommerce_enable_checkout_logged_in',
			'WooCommerce Checkout (Logged-in)',
			'Add captcha to WooCommerce classic checkout form for logged-in users.',
			'Add captcha to WooCommerce checkout for logged-in users'
		);
	}

	/**
	 * Get all settings fields for this integration.
	 *
	 * @return array<\PrivateCaptchaWP\SettingsField> Array of SettingsField instances.
	 */
	public function get_settings_fields(): array {
		return array(
			$this->login_field,
			$this->register_field,
			$this->lost_password_field,
			$this->checkout_guest_field,
			$this->checkout_logged_in_field,
		);
	}

	/**
	 * Check if WooCommerce plugin is active.
	 *
	 * @return bool True if WooCommerce is active.
	 */
	public function is_available(): bool {
		return is_plugin_active( 'woocommerce/woocommerce.php' );
	}

	/**
	 * Check if WooCommerce integration is enabled.
	 *
	 * @return bool True if any WooCommerce integration setting is enabled.
	 */
	public function is_enabled(): bool {
		return $this->login_field->is_enabled() ||
			$this->register_field->is_enabled() ||
			$this->lost_password_field->is_enabled() ||
			$this->checkout_guest_field->is_enabled() ||
			$this->checkout_logged_in_field->is_enabled();
	}

	/**
	 * Check if checkout captcha is active for the current user.
	 *
	 * @return bool True if checkout captcha should be shown/verified for the current user.
	 */
	private function is_checkout_captcha_enabled(): bool {
		if ( is_user_logged_in() ) {
			return $this->checkout_logged_in_field->is_enabled();
		}

		return $this->checkout_guest_field->is_enabled();
	}

	/**
	 * Check if any checkout setting is enabled (used during init to register hooks).
	 *
	 * @return bool True if either checkout guest or logged-in setting is enabled.
	 */
	private function is_any_checkout_enabled(): bool {
		return $this->checkout_guest_field->is_enabled() || $this->checkout_logged_in_field->is_enabled();
	}

	/**
	 * Initialize WooCommerce integration hooks.
	 */
	public function init(): void {
		$this->write_log( 'Initializing WooCommerce integration.' );

		if ( $this->login_field->is_enabled() ) {
			add_action( 'woocommerce_login_form', array( $this, 'add_login_captcha' ) );
			add_filter( 'woocommerce_process_login_errors', array( $this, 'verify_login_captcha' ), 20, 3 );
		}

		if ( $this->register_field->is_enabled() ) {
			add_action( 'woocommerce_register_form', array( $this, 'add_register_captcha' ) );
			add_action( 'woocommerce_register_post', array( $this, 'verify_register_captcha' ), 10, 3 );
		}

		if ( $this->lost_password_field->is_enabled() ) {
			add_action( 'woocommerce_lostpassword_form', array( $this, 'add_lost_password_captcha' ) );
			add_action( 'lostpassword_post', array( $this, 'verify_lost_password_captcha' ), 10, 1 );
		}

		if ( $this->is_any_checkout_enabled() ) {
			// Classic checkout: render widget before submit button.
			add_action( 'woocommerce_review_order_before_submit', array( $this, 'add_checkout_captcha' ) );

			// Classic checkout: verify captcha during checkout processing.
			add_action( 'woocommerce_checkout_process', array( $this, 'verify_checkout_captcha' ) );
		}

		add_action( 'wp_enqueue_scripts', array( $this, 'enqueue_scripts' ) );
	}

	/**
	 * Add captcha widget to WooCommerce login form.
	 */
	public function add_login_captcha(): void {
		Widget::render( '--border-radius: 0.25rem;' );
	}

	/**
	 * Add captcha widget to WooCommerce registration form.
	 */
	public function add_register_captcha(): void {
		Widget::render( '--border-radius: 0.25rem;' );
	}

	/**
	 * Add captcha widget to WooCommerce lost password form.
	 */
	public function add_lost_password_captcha(): void {
		Widget::render( '--border-radius: 0.25rem;' );
	}

	/**
	 * Add captcha widget to classic WooCommerce checkout form.
	 * Hooked to woocommerce_review_order_before_submit which fires inside
	 * the .woocommerce-checkout-payment div, ensuring the widget is re-rendered
	 * on AJAX fragment replacements (update_order_review).
	 */
	public function add_checkout_captcha(): void {
		if ( ! $this->is_checkout_captcha_enabled() ) {
			return;
		}

		Widget::render( '--border-radius: 0.25rem;' );
	}

	/**
	 * Verify captcha for WooCommerce login form.
	 *
	 * @param WP_Error $validation_error Validation errors object.
	 * @param string   $username The username (required by hook signature, unused).
	 * @param string   $password The password (required by hook signature, unused).
	 * @return WP_Error The validation errors object.
	 */
	public function verify_login_captcha( WP_Error $validation_error, string $username, string $password ): WP_Error {
		unset( $username, $password );
		if ( ! $this->client->is_available() ) {
			$validation_error->add(
				'private_captcha_unavailable',
				esc_html__( 'Captcha service is currently unavailable.', 'private-captcha' )
			);
			return $validation_error;
		}

		if ( ! $this->verify_captcha() ) {
			$validation_error->add(
				'private_captcha_failed',
				esc_html__( 'Captcha verification failed. Please try again.', 'private-captcha' )
			);
		}

		return $validation_error;
	}

	/**
	 * Verify captcha for WooCommerce registration form.
	 *
	 * @param string   $username The username (required by hook signature, unused).
	 * @param string   $email The email address (required by hook signature, unused).
	 * @param WP_Error $errors The errors object.
	 */
	public function verify_register_captcha( string $username, string $email, WP_Error $errors ): void {
		unset( $username, $email );
		if ( ! $this->client->is_available() ) {
			$errors->add(
				'private_captcha_unavailable',
				esc_html__( 'Captcha service is currently unavailable.', 'private-captcha' )
			);
			return;
		}

		if ( ! $this->verify_captcha() ) {
			$errors->add(
				'private_captcha_failed',
				esc_html__( 'Captcha verification failed. Please try again.', 'private-captcha' )
			);
		}
	}

	/**
	 * Verify captcha for WooCommerce lost password form.
	 * Only verifies when the WooCommerce lost password nonce is present.
	 *
	 * @param WP_Error $errors The errors object.
	 */
	public function verify_lost_password_captcha( WP_Error $errors ): void {
		// phpcs:ignore WordPress.Security.NonceVerification.Missing -- Nonce is verified by WooCommerce, we only check its presence to identify the form.
		if ( ! isset( $_POST['woocommerce-lost-password-nonce'] ) ) {
			return;
		}

		if ( ! $this->client->is_available() ) {
			$errors->add(
				'private_captcha_unavailable',
				esc_html__( 'Captcha service is currently unavailable.', 'private-captcha' )
			);
			return;
		}

		if ( ! $this->verify_captcha() ) {
			$errors->add(
				'private_captcha_failed',
				esc_html__( 'Captcha verification failed. Please try again.', 'private-captcha' )
			);
		}
	}

	/**
	 * Verify captcha for classic WooCommerce checkout form.
	 * Uses wc_add_notice() to add error notices that WooCommerce displays.
	 * Reads the solution from $_POST which is populated by the form submission.
	 */
	public function verify_checkout_captcha(): void {
		if ( ! $this->is_checkout_captcha_enabled() ) {
			return;
		}

		if ( ! $this->client->is_available() ) {
			wc_add_notice( esc_html__( 'Captcha service is currently unavailable.', 'private-captcha' ), 'error' );
			return;
		}

		if ( ! $this->verify_captcha() ) {
			wc_add_notice( esc_html__( 'Captcha verification failed. Please try again.', 'private-captcha' ), 'error' );
		}
	}

	/**
	 * Enqueue Private Captcha scripts with WooCommerce-specific handlers.
	 * Classic checkout: reset widget after AJAX fragment replacement (updated_checkout)
	 * and after checkout errors (checkout_error).
	 */
	public function enqueue_scripts(): void {
		$woo_custom_js = '
                if (typeof jQuery !== "undefined") {
                    jQuery(document.body).on("updated_checkout", function() {
                        var paymentDiv = document.querySelector(".woocommerce-checkout-payment");
                        if (paymentDiv) { pcResetCaptchaWidgetWP(paymentDiv); }
                    });
                    jQuery(document.body).on("checkout_error", function() {
                        var form = document.querySelector("form.checkout");
                        if (form) { pcResetCaptchaWidgetWP(form); }
                    });
                }';

		$woo_custom_css = '
            .woocommerce-checkout-payment .private-captcha {
                margin: 1rem 0;
            }
        ';

		Assets::enqueue( 'private-captcha-widget', $woo_custom_js, $woo_custom_css );
	}
}
