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
use PrivateCaptchaWP\Settings;
use PrivateCaptchaWP\SettingsField;
use PrivateCaptchaWP\Widget;
use WP_Error;

/**
 * WooCommerce integration class
 */
class WooCommerce extends AbstractIntegration {

	/**
	 * Default widget styles for WooCommerce forms.
	 */
	private const WIDGET_STYLES = 'max-width: 100%; --border-radius: 0.25rem;';

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
	private SettingsField $registration_field;

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
			'Add captcha to WooCommerce login form on My Account page.',
			'Add captcha to WooCommerce login form'
		);

		$this->registration_field = new SettingsField(
			'woocommerce_enable_registration',
			'WooCommerce Registration Form',
			'Add captcha to WooCommerce registration form on My Account page.',
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
			'Add captcha to WooCommerce checkout form.',
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
			$this->registration_field,
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
	 * @return bool True if any WooCommerce form integration is enabled.
	 */
	public function is_enabled(): bool {
		return $this->login_field->is_enabled() ||
			$this->registration_field->is_enabled() ||
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
			add_filter( 'woocommerce_process_login_errors', array( $this, 'verify_login_captcha' ), 20, 1 );
		}

		if ( $this->registration_field->is_enabled() ) {
			add_action( 'woocommerce_register_form', array( $this, 'add_registration_captcha' ) );
			add_filter( 'woocommerce_process_registration_errors', array( $this, 'verify_registration_captcha' ), 20, 1 );
		}

		if ( $this->lost_password_field->is_enabled() ) {
			add_action( 'woocommerce_lostpassword_form', array( $this, 'add_lost_password_captcha' ) );
			add_action( 'lostpassword_post', array( $this, 'verify_lost_password_captcha' ), 10, 1 );
		}

		if ( $this->checkout_field->is_enabled() ) {
			// Classic checkout hooks.
			add_action( 'woocommerce_review_order_before_submit', array( $this, 'add_checkout_captcha' ) );
			add_action( 'woocommerce_checkout_process', array( $this, 'verify_checkout_captcha' ) );

			// Blocks checkout hooks.
			add_filter( 'render_block_woocommerce/checkout', array( $this, 'add_checkout_captcha_block' ) );
			add_action( 'woocommerce_blocks_loaded', array( $this, 'register_checkout_block_extension' ) );
			add_action( 'woocommerce_store_api_checkout_update_order_from_request', array( $this, 'verify_checkout_captcha_block' ), 10, 2 );
		}

		add_action( 'wp_enqueue_scripts', array( $this, 'enqueue_scripts' ) );
	}

	/**
	 * Add captcha widget to WooCommerce login form.
	 */
	public function add_login_captcha(): void {
		Widget::render( self::WIDGET_STYLES );
	}

	/**
	 * Add captcha widget to WooCommerce registration form.
	 */
	public function add_registration_captcha(): void {
		Widget::render( self::WIDGET_STYLES );
	}

	/**
	 * Add captcha widget to WooCommerce lost password form.
	 */
	public function add_lost_password_captcha(): void {
		Widget::render( self::WIDGET_STYLES );
	}

	/**
	 * Add captcha widget to WooCommerce checkout form.
	 */
	public function add_checkout_captcha(): void {
		Widget::render( self::WIDGET_STYLES );
	}

	/**
	 * Add captcha widget to WooCommerce blocks checkout.
	 *
	 * Appends the captcha widget HTML after the checkout block content.
	 * JavaScript repositions it inside the form before the Place Order button.
	 *
	 * @param string $content The block content.
	 * @return string Modified block content with captcha widget appended.
	 */
	public function add_checkout_captcha_block( string $content ): string {
		ob_start();
		Widget::render( self::WIDGET_STYLES );
		$captcha_html = ob_get_clean();
		return $content . $captcha_html;
	}

	/**
	 * Register the captcha extension namespace with the WooCommerce Store API.
	 *
	 * This allows the blocks checkout to include captcha solution data
	 * in the checkout request extensions.
	 */
	public function register_checkout_block_extension(): void {
		if ( function_exists( 'woocommerce_store_api_register_endpoint_data' ) ) {
			woocommerce_store_api_register_endpoint_data(
				array(
					'endpoint'        => 'checkout',
					'namespace'       => 'private-captcha',
					'data_callback'   => array( $this, 'get_checkout_extension_data' ),
					'schema_callback' => array( $this, 'get_checkout_extension_schema' ),
					'schema_type'     => ARRAY_A,
				)
			);
		}
	}

	/**
	 * Get default extension data for blocks checkout.
	 *
	 * @return array<string, string> Default extension data.
	 */
	public function get_checkout_extension_data(): array {
		return array( 'solution' => '' );
	}

	/**
	 * Get the extension schema for blocks checkout.
	 *
	 * @return array<string, array<string, mixed>> Extension schema definition.
	 */
	public function get_checkout_extension_schema(): array {
		return array(
			'solution' => array(
				'description' => 'Captcha solution',
				'type'        => 'string',
				'context'     => array( 'view', 'edit' ),
			),
		);
	}

	/**
	 * Verify captcha for WooCommerce blocks checkout via Store API.
	 *
	 * @param \WC_Order         $order   The order being placed.
	 * @param \WP_REST_Request  $request The Store API request.
	 *
	 * @throws \Exception If captcha verification fails.
	 */
	public function verify_checkout_captcha_block( \WC_Order $order, \WP_REST_Request $request ): void {
		$extensions = $request->get_param( 'extensions' );
		$solution   = sanitize_text_field( $extensions['private-captcha']['solution'] ?? '' );
		$sitekey    = Settings::get_sitekey();

		if ( ! $this->client->is_available() ) {
			$this->throw_checkout_block_error(
				'private_captcha_unavailable',
				esc_html__( 'Captcha service is currently unavailable.', 'private-captcha' )
			);
			return;
		}

		$result = $this->client->verify_solution( $solution, $sitekey );
		$this->write_log( 'Private Captcha blocks checkout verification finished. result=' . $result );

		if ( ! $result ) {
			$this->throw_checkout_block_error(
				'private_captcha_failed',
				esc_html__( 'Captcha verification failed. Please try again.', 'private-captcha' )
			);
		}
	}

	/**
	 * Throw an appropriate exception for blocks checkout errors.
	 *
	 * Uses WooCommerce RouteException if available, falls back to generic Exception.
	 *
	 * @param string $code    Error code.
	 * @param string $message Error message.
	 *
	 * @throws \Automattic\WooCommerce\StoreApi\Exceptions\RouteException When RouteException class exists.
	 * @throws \Exception When RouteException class does not exist.
	 */
	private function throw_checkout_block_error( string $code, string $message ): void {
		if ( class_exists( '\Automattic\WooCommerce\StoreApi\Exceptions\RouteException' ) ) {
			throw new \Automattic\WooCommerce\StoreApi\Exceptions\RouteException( $code, $message, 400 );
		}

		throw new \Exception( $message ); // phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped -- Message is already escaped by callers.
	}

	/**
	 * Verify captcha for WooCommerce login form.
	 *
	 * @param WP_Error $validation_error Validation errors object.
	 * @return WP_Error The validation errors object.
	 */
	public function verify_login_captcha( WP_Error $validation_error ): WP_Error {
		if ( $validation_error->has_errors() ) {
			$this->write_log( 'Skipping WooCommerce login captcha verification due to existing errors.' );
			return $validation_error;
		}

		$this->add_captcha_errors( $validation_error );

		return $validation_error;
	}

	/**
	 * Verify captcha for WooCommerce registration form.
	 *
	 * @param WP_Error $validation_error Validation errors object.
	 * @return WP_Error The validation errors object.
	 */
	public function verify_registration_captcha( WP_Error $validation_error ): WP_Error {
		if ( $validation_error->has_errors() ) {
			$this->write_log( 'Skipping WooCommerce registration captcha verification due to existing errors.' );
			return $validation_error;
		}

		$this->add_captcha_errors( $validation_error );

		return $validation_error;
	}

	/**
	 * Verify captcha for WooCommerce lost password form.
	 *
	 * Hooks into WordPress core lostpassword_post action but only processes
	 * when the WooCommerce lost password nonce is present, to avoid
	 * conflicting with the WordPress Core integration.
	 *
	 * @param WP_Error $errors The errors object to add errors to.
	 */
	public function verify_lost_password_captcha( WP_Error $errors ): void {
		// phpcs:ignore WordPress.Security.NonceVerification.Missing -- WooCommerce handles nonce verification.
		if ( ! isset( $_POST['woocommerce-lost-password-nonce'] ) ) {
			return;
		}

		$this->add_captcha_errors( $errors );
	}

	/**
	 * Verify captcha for WooCommerce checkout form.
	 */
	public function verify_checkout_captcha(): void {
		if ( ! $this->client->is_available() ) {
			if ( function_exists( 'wc_add_notice' ) ) {
				wc_add_notice(
					esc_html__( 'Captcha service is currently unavailable.', 'private-captcha' ),
					'error'
				);
			}
			return;
		}

		if ( ! $this->verify_captcha() ) {
			if ( function_exists( 'wc_add_notice' ) ) {
				wc_add_notice(
					esc_html__( 'Captcha verification failed. Please try again.', 'private-captcha' ),
					'error'
				);
			}
		}
	}

	/**
	 * Verify captcha and add errors to a WP_Error object.
	 *
	 * @param WP_Error $errors The errors object to add errors to.
	 */
	private function add_captcha_errors( WP_Error $errors ): void {
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
	 * Enqueue Private Captcha scripts for WooCommerce pages.
	 */
	public function enqueue_scripts(): void {
		$wc_custom_js = '
                if (typeof jQuery !== "undefined") {
                    jQuery( function( $ ) {
                        $( document.body ).on( "updated_checkout", function() {
                            var paymentDiv = document.querySelector(".woocommerce-checkout-payment");
                            if (!paymentDiv) return;
                            pcResetCaptchaWidgetWP(paymentDiv);
                            var widgets = paymentDiv.querySelectorAll(".private-captcha");
                            widgets.forEach( function( widget ) {
                                pcSetFormButtonEnabledWP( widget, false );
                                widget.addEventListener( "privatecaptcha:init", function( event ) {
                                    pcSetFormButtonEnabledWP( event.detail.element, false );
                                });
                                widget.addEventListener( "privatecaptcha:finish", function( event ) {
                                    pcSetFormButtonEnabledWP( event.detail.element, true );
                                });
                            });
                        });
                    });
                }

                var blocksForm = document.querySelector(".wc-block-checkout__form");
                if (blocksForm) {
                    var actionsBlock = blocksForm.querySelector(".wc-block-checkout__actions");
                    var captchaWidget = document.querySelector(".wp-block-woocommerce-checkout ~ .private-captcha");
                    if (captchaWidget && actionsBlock) {
                        actionsBlock.parentNode.insertBefore(captchaWidget, actionsBlock);
                    }

                    if (typeof wp !== "undefined" && wp.apiFetch) {
                        wp.apiFetch.use(function(options, next) {
                            if (options.path && options.path.indexOf("/wc/store/v1/checkout") !== -1 && options.method === "POST") {
                                var input = document.querySelector("input[name=\"wp-private-captcha-solution\"]");
                                if (input && input.value) {
                                    options.data = options.data || {};
                                    options.data.extensions = options.data.extensions || {};
                                    options.data.extensions["private-captcha"] = { solution: input.value };
                                }
                            }
                            return next(options);
                        });
                    }
                }';

		Assets::enqueue( 'private-captcha-widget', $wc_custom_js );
	}
}
