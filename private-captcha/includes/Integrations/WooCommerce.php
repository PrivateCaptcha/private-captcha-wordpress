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
use WP_REST_Request;
use WP_REST_Server;

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
			'Add captcha to WooCommerce checkout form for guest users.',
			'Add captcha to WooCommerce checkout for guests'
		);

		$this->checkout_logged_in_field = new SettingsField(
			'woocommerce_enable_checkout_logged_in',
			'WooCommerce Checkout (Logged-in)',
			'Add captcha to WooCommerce checkout form for logged-in users.',
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
		$this->write_log( 'Initializing WooCommerce integration' );

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

			// Block-based checkout: render widget before the checkout actions block.
			add_filter( 'render_block_woocommerce/checkout-actions-block', array( $this, 'render_block_checkout_captcha' ), 10, 1 );
			add_filter( 'rest_authentication_errors', array( $this, 'verify_block_checkout_captcha' ) );
			add_action( 'woocommerce_loaded', array( $this, 'register_endpoint_data' ), 20 );
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

		static $already_verified = false;
		if ( $already_verified ) {
			return $validation_error;
		}

		if ( ! $this->verify_captcha() ) {
			$validation_error->add(
				'private_captcha_failed',
				parent::verification_error_html()
			);
		}

		$already_verified = true;

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

		static $already_verified = false;
		if ( $already_verified ) {
			return;
		}

		if ( ! $this->verify_captcha() ) {
			$errors->add(
				'private_captcha_failed',
				parent::verification_error_html()
			);
		}

		$already_verified = true;
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

		static $already_verified = false;
		if ( $already_verified ) {
			return;
		}

		if ( ! $this->verify_captcha() ) {
			$errors->add(
				'private_captcha_failed',
				parent::verification_error_html()
			);
		}

		$already_verified = true;
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

		static $already_verified = false;
		if ( $already_verified ) {
			return;
		}

		if ( ! $this->client->is_available() ) {
			wc_add_notice( esc_html__( 'Captcha service is currently unavailable.', 'private-captcha' ), 'error' );
			return;
		}

		if ( ! $this->verify_captcha() ) {
			$already_verified = true;
			wc_add_notice( parent::verification_error_html(), 'error' );
		}

		$already_verified = true;
	}

	/**
	 * Render captcha widget before the block-based checkout actions block.
	 * Uses the render_block_woocommerce/checkout-actions-block filter to inject
	 * the widget HTML before the Place Order button in block-based checkout.
	 *
	 * @param string $block_content The block content HTML.
	 * @return string Modified block content with captcha widget prepended.
	 */
	public function render_block_checkout_captcha( string $block_content ): string {
		if ( ! $this->is_checkout_captcha_enabled() ) {
			return $block_content;
		}

		ob_start();
		Widget::render( '--border-radius: 0.25rem;' );
		$captcha_html = ob_get_clean();
		$captcha_html = false !== $captcha_html ? $captcha_html : '';

		return $captcha_html . $block_content;
	}

	/**
	 * Verify captcha for WooCommerce block checkout.
	 *
	 * @param mixed $result Validation result object.
	 * @return mixed Modified validation result object.
	 */
	public function verify_block_checkout_captcha( $result ) {
		// Skip if this is not a POST request.
		if ( isset( $_SERVER['REQUEST_METHOD'] ) && 'POST' !== $_SERVER['REQUEST_METHOD'] ) {
			// Always return the result or an error, never a boolean. This ensures other checks aren't thrown away like rate limiting or authentication.
			return $result;
		}

		// Skip if this is not the checkout endpoint.
		if ( ! preg_match( '#/wc/store(?:/v\d+)?/checkout#', $GLOBALS['wp']->query_vars['rest_route'] ) ) {
			return $result;
		}

		$request_body = json_decode( \WP_REST_Server::get_raw_data(), true );

		if ( isset( $request_body['payment_method'] ) ) {
			$chosen_payment_method = sanitize_text_field( $request_body['payment_method'] );

			// Provide ability to short circuit the check to allow express payments or hosted checkouts to bypass the check.
			$selected_payment_methods = apply_filters( 'private_captcha_payment_methods_to_skip', array( 'woocommerce_payments' ) );
			if ( is_array( $selected_payment_methods ) ) {
				if ( in_array( $chosen_payment_method, $selected_payment_methods, true ) ) {
					return $result;
				}
			}
		}

		static $already_verified = false;
		if ( $already_verified ) {
			return $result;
		}

		if ( ! $this->client->is_available() ) {
			return new WP_Error(
				'private_captcha_unavailable',
				esc_html__( 'Captcha service is currently unavailable.', 'private-captcha' )
			);
		}

		$extensions = $request_body['extensions'];
		if ( empty( $extensions ) || ! isset( $extensions['private-captcha'] ) ) {
			// phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped -- we escape inside
			return new WP_Error( 'private_captcha_failed', parent::verification_error_html() );
		}
		$solution = $extensions['private-captcha']['solution'];

		if ( empty( $solution ) || ! $this->verify_solution( $solution ) ) {
			$already_verified = true;
			return new WP_Error(
				'private_captcha_failed',
				// phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped -- we escape inside
				parent::verification_error_html()
			);
		}

		$already_verified = true;

		return $result;
	}

	/**
	 * Verify captcha solution directly.
	 *
	 * @param string $solution The captcha solution.
	 * @return bool True if verification succeeds.
	 */
	protected function verify_solution( string $solution ): bool {
		$sitekey = \PrivateCaptchaWP\Settings::get_sitekey();
		$result  = $this->client->verify_solution( $solution, $sitekey );

		$this->write_log( 'Private Captcha block checkout verification finished. result=' . $result );

		return $result;
	}

	/**
	 * Register endpoint data for WooCommerce Store API.
	 */
	public function register_endpoint_data(): void {
		if ( ! function_exists( 'woocommerce_store_api_register_endpoint_data' ) ) {
			return;
		}

		woocommerce_store_api_register_endpoint_data(
			array(
				'endpoint'        => 'checkout',
				'namespace'       => 'private-captcha',
				'schema_callback' => function () {
					return array(
						'solution' => array(
							'description'       => __( 'Private Captcha solution.', 'private-captcha' ),
							'type'              => 'string',
							'context'           => array( 'view', 'edit' ),
							'sanitize_callback' => 'sanitize_text_field',
						),
					);
				},
			)
		);
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
                        const paymentDiv = document.querySelector(".woocommerce-checkout-payment");
                        if (paymentDiv) { pcResetCaptchaWidgetWP(paymentDiv); }
                    });
                    jQuery(document.body).on("checkout_error", function() {
                        const form = document.querySelector("form.checkout");
                        if (form) { pcResetCaptchaWidgetWP(form); }
                    });
                }
                if (typeof wp !== "undefined" && wp.data) {
                    function setPrivateCaptchaExtensionData(solution) {
                        const dispatch = wp.data.dispatch("wc/store/checkout");
                        if (!dispatch) return;
                        if (typeof dispatch.setExtensionData === "function") {
                            dispatch.setExtensionData("private-captcha", { solution: solution });
                        } else if (typeof dispatch.__internalSetExtensionData === "function") {
                            dispatch.__internalSetExtensionData("private-captcha", { solution: solution });
                        }
                    }

                    // For the initially loaded widgets (if any exist before wp.data.subscribe fires setup)
                    document.querySelectorAll(".private-captcha").forEach(function(widgetEl) {
                        widgetEl.addEventListener("privatecaptcha:init", function(event) { setPrivateCaptchaExtensionData(""); });
                        widgetEl.addEventListener("privatecaptcha:finish", function(event) { setPrivateCaptchaExtensionData(event.detail.widget.solution()); });
                        widgetEl.addEventListener("privatecaptcha:reset", function(event) { setPrivateCaptchaExtensionData(""); });
                    });

                    const unsubscribe = wp.data.subscribe(function() {
                        if (typeof window.privateCaptcha !== "undefined" && typeof window.privateCaptcha.setup === "function") {
                            // default state (on load) will be handled by parent setupPrivateCaptchaWP() function
                            // here we only handle "changes" (for the lack of better API in WordPress/WooCommerce)
                            const newWidgets = window.privateCaptcha.setup();
                            if (newWidgets && (newWidgets.length > 0)) {
                                newWidgets.forEach(function(widget) {
                                    pcSetFormButtonEnabledWP(widget.element(), false, submitBtnSelector);

                                    widget.element().addEventListener("privatecaptcha:init", function(event) {
                                        pcSetFormButtonEnabledWP(event.detail.element, false, submitBtnSelector);
                                        setPrivateCaptchaExtensionData("");
                                    });
                                    widget.element().addEventListener("privatecaptcha:finish", function(event) { 
                                        pcSetFormButtonEnabledWP(event.detail.element, true, submitBtnSelector);
                                        setPrivateCaptchaExtensionData(event.detail.widget.solution());
                                    });
                                    widget.element().addEventListener("privatecaptcha:reset", function(event) {
                                        pcSetFormButtonEnabledWP(event.detail.element, false, submitBtnSelector);
                                        setPrivateCaptchaExtensionData("");
                                    });
                                });
                            }
                        }
                    }, "wc/store/cart");
                    if (typeof jQuery !== "undefined") {
                        jQuery(document.body).on("click", ".wc-block-components-checkout-place-order-button", function() {
                            setTimeout(function() {
                                const checkoutBlock = document.querySelector(".wc-block-checkout");
                                if (checkoutBlock) {
                                    pcResetCaptchaWidgetWP(checkoutBlock);
                                }
                                setPrivateCaptchaExtensionData("");
                            }, 2000);
                        });
                    }
                }';

		$woo_custom_css = '
            .woocommerce-checkout-payment .private-captcha {
                margin: 1rem 0;
            }
        ';

		Assets::enqueue( 'private-captcha-widget', $woo_custom_js, $woo_custom_css, '.wc-block-components-checkout-place-order-button, input[type=\"submit\"], button[type=\"submit\"]' );
	}
}
