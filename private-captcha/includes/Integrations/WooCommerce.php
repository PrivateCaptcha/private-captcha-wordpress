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
use PrivateCaptchaWP\Client;
use PrivateCaptchaWP\Settings;
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
	 * WooCommerce checkout form settings field.
	 *
	 * @var SettingsField
	 */
	private SettingsField $checkout_field;

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

		$this->checkout_field = new SettingsField(
			'woocommerce_enable_checkout',
			'WooCommerce Checkout Form',
			'Add captcha to WooCommerce checkout form (supports both classic and block-based checkout).',
			'Add captcha to WooCommerce checkout form'
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
			$this->checkout_field,
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
			$this->checkout_field->is_enabled();
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

		if ( $this->checkout_field->is_enabled() ) {
			// Classic checkout: render widget before submit button.
			add_action( 'woocommerce_review_order_before_submit', array( $this, 'add_checkout_captcha' ) );

			// Classic checkout: verify captcha during checkout processing.
			add_action( 'woocommerce_checkout_process', array( $this, 'verify_checkout_captcha' ) );

			// Block-based checkout: render widget before the checkout actions block.
			add_filter( 'render_block_woocommerce/checkout-actions-block', array( $this, 'render_block_checkout_captcha' ), 10, 1 );

			// Block-based checkout: register Store API endpoint data and validate.
			add_action( 'woocommerce_loaded', array( $this, 'register_store_api_endpoint_data' ), 20 );
			add_action( 'woocommerce_store_api_checkout_update_order_from_request', array( $this, 'verify_block_checkout_captcha' ), 10, 2 );
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
		Widget::render( '--border-radius: 0.25rem;' );
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
		ob_start();
		Widget::render( '--border-radius: 0.25rem;' );
		$captcha_html = ob_get_clean();
		$captcha_html = false !== $captcha_html ? $captcha_html : '';

		return $captcha_html . $block_content;
	}

	/**
	 * Register Store API endpoint data for block-based checkout.
	 * This allows the captcha solution to be passed through the extensions
	 * property of the checkout API request.
	 */
	public function register_store_api_endpoint_data(): void {
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
	 */
	public function verify_checkout_captcha(): void {
		if ( ! $this->client->is_available() ) {
			wc_add_notice( esc_html__( 'Captcha service is currently unavailable.', 'private-captcha' ), 'error' );
			return;
		}

		if ( ! $this->verify_captcha() ) {
			wc_add_notice( esc_html__( 'Captcha verification failed. Please try again.', 'private-captcha' ), 'error' );
		}
	}

	/**
	 * Verify captcha for block-based WooCommerce checkout.
	 * Reads the solution from the extensions data sent via Store API.
	 *
	 * @param \WC_Order        $order   The order object.
	 * @param \WP_REST_Request $request The REST API request.
	 * @throws \Exception When captcha verification fails.
	 */
	public function verify_block_checkout_captcha( $order, $request ): void {
		if ( ! $this->client->is_available() ) {
			throw new \Exception( esc_html__( 'Captcha service is currently unavailable.', 'private-captcha' ) );
		}

		$extensions = $request->get_param( 'extensions' );
		$solution   = '';

		if ( is_array( $extensions ) && isset( $extensions['private-captcha']['solution'] ) ) {
			$solution = sanitize_text_field( $extensions['private-captcha']['solution'] );
		}

		if ( empty( $solution ) ) {
			throw new \Exception( esc_html__( 'Captcha verification failed. Please try again.', 'private-captcha' ) );
		}

		$sitekey = Settings::get_sitekey();
		$result  = $this->client->verify_solution( $solution, $sitekey );

		$this->write_log( 'Private Captcha block checkout verification finished. result=' . $result );

		if ( ! $result ) {
			throw new \Exception( esc_html__( 'Captcha verification failed. Please try again.', 'private-captcha' ) );
		}
	}

	/**
	 * Enqueue Private Captcha scripts with WooCommerce-specific handlers.
	 * Handles:
	 * - Classic checkout: reset widget after AJAX fragment replacement (updated_checkout)
	 *   and after checkout errors (checkout_error).
	 * - Block-based checkout: use MutationObserver to detect when the React-rendered
	 *   form is ready, then initialize the captcha widget and send the solution via
	 *   Store API extensions data using wp.data dispatch to wc/store/checkout.
	 */
	public function enqueue_scripts(): void {
		$woo_custom_js = '
                // Classic checkout: reset captcha widget after AJAX fragment replacement.
                if (typeof jQuery !== "undefined") {
                    jQuery(document.body).on("updated_checkout", function() {
                        var paymentDiv = document.querySelector(".woocommerce-checkout-payment");
                        if (paymentDiv) {
                            var widgets = paymentDiv.querySelectorAll(".private-captcha");
                            widgets.forEach(function(widget) {
                                pcSetFormButtonEnabledWP(widget, false);
                                widget.addEventListener("privatecaptcha:init", function(e) { pcSetFormButtonEnabledWP(e.detail.element, false); });
                                widget.addEventListener("privatecaptcha:finish", function(e) { pcSetFormButtonEnabledWP(e.detail.element, true); });
                            });
                        }
                    });
                    // Classic checkout: reset captcha on checkout error.
                    jQuery(document.body).on("checkout_error", function() {
                        var form = document.querySelector("form.checkout");
                        if (form) { pcResetCaptchaWidgetWP(form); }
                    });
                }

                // Block-based checkout: handle widget initialization timing and button state.
                (function() {
                    var pcWooBlockInitialized = false;

                    function pcWooBlockSetButtonEnabled(enabled) {
                        var btn = document.querySelector(".wc-block-components-checkout-place-order-button");
                        if (btn) {
                            btn.disabled = !enabled;
                        }
                    }

                    function pcWooBlockSetExtensionData(solution) {
                        if (typeof wp === "undefined" || !wp.data) { return; }
                        var dispatch = wp.data.dispatch("wc/store/checkout");
                        if (dispatch && typeof dispatch.setExtensionData === "function") {
                            dispatch.setExtensionData("private-captcha", { solution: solution });
                        } else if (dispatch && typeof dispatch.__internalSetExtensionData === "function") {
                            dispatch.__internalSetExtensionData("private-captcha", { solution: solution });
                        }
                    }

                    function pcWooBlockInitWidget() {
                        var widget = document.querySelector(".wc-block-checkout__form .private-captcha, .wp-block-woocommerce-checkout .private-captcha");
                        if (!widget) { return false; }
                        if (pcWooBlockInitialized && widget.hasOwnProperty("_privateCaptcha") && widget._privateCaptcha) { return true; }

                        // Trigger privatecaptcha.js to discover and initialize the widget.
                        if (window.privateCaptcha && typeof window.privateCaptcha.setup === "function") {
                            window.privateCaptcha.setup();
                        }

                        // Disable button until captcha is solved.
                        pcWooBlockSetButtonEnabled(false);
                        pcWooBlockSetExtensionData("");

                        // Listen for widget events.
                        widget.addEventListener("privatecaptcha:init", function() {
                            pcWooBlockSetButtonEnabled(false);
                            pcWooBlockSetExtensionData("");
                        });
                        widget.addEventListener("privatecaptcha:finish", function() {
                            var solutionField = widget.querySelector("input[name=\"wp-private-captcha-solution\"]");
                            if (!solutionField) {
                                var parent = widget.closest("form") || widget.parentElement;
                                solutionField = parent ? parent.querySelector("input[name=\"wp-private-captcha-solution\"]") : null;
                            }
                            var solution = solutionField ? solutionField.value : "";
                            pcWooBlockSetExtensionData(solution);
                            pcWooBlockSetButtonEnabled(true);
                        });

                        pcWooBlockInitialized = true;
                        return true;
                    }

                    // Use MutationObserver to detect when the block checkout form renders our widget.
                    var pcWooBlockCheckoutContainer = document.querySelector(".wp-block-woocommerce-checkout");
                    if (pcWooBlockCheckoutContainer) {
                        // Try immediately in case React already rendered.
                        if (!pcWooBlockInitWidget()) {
                            var pcWooObserver = new MutationObserver(function() {
                                if (pcWooBlockInitWidget()) {
                                    pcWooObserver.disconnect();
                                }
                            });
                            pcWooObserver.observe(pcWooBlockCheckoutContainer, { childList: true, subtree: true });
                        }
                    }
                })();';

		$woo_custom_css = '
            .woocommerce-checkout-payment .private-captcha {
                margin: 1rem 0;
            }
            .wc-block-components-checkout-place-order-button:disabled {
                opacity: 0.7;
                cursor: not-allowed;
            }
        ';

		Assets::enqueue( 'private-captcha-widget', $woo_custom_js, $woo_custom_css );
	}
}
