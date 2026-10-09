<?php
/**
 * Gravity Forms integration for Private Captcha WordPress plugin.
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

/**
 * Gravity Forms integration class
 */
class GravityForms extends AbstractIntegration {

	/**
	 * Gravity Forms settings field.
	 *
	 * @var SettingsField
	 */
	private SettingsField $gravity_forms_field;

	/**
	 * Constructor to initialize the integration.
	 *
	 * @param \PrivateCaptchaWP\Client $client The Private Captcha client instance.
	 */
	public function __construct( \PrivateCaptchaWP\Client $client ) {
		parent::__construct( $client );

		$this->plugin_url  = 'https://www.gravityforms.com/';
		$this->plugin_name = 'Gravity Forms';

		$this->gravity_forms_field = new SettingsField(
			'gravityforms_enable',
			'Gravity Forms plugin',
			'Protect Gravity Forms submissions from spam.',
			'Add captcha to forms created with Gravity Forms plugin'
		);
	}

	/**
	 * Get all settings fields for this integration.
	 *
	 * @return array<\PrivateCaptchaWP\SettingsField> Array of SettingsField instances.
	 */
	public function get_settings_fields(): array {
		return array(
			$this->gravity_forms_field,
		);
	}

	/**
	 * Check if Gravity Forms plugin is active.
	 *
	 * @return bool True if Gravity Forms is active.
	 */
	public function is_available(): bool {
		return is_plugin_active( 'gravityforms/gravityforms.php' );
	}

	/**
	 * Check if Gravity Forms integration is enabled.
	 *
	 * @return bool True if Gravity Forms integration is enabled.
	 */
	public function is_enabled(): bool {
		return $this->gravity_forms_field->is_enabled();
	}

	/**
	 * Initialize Gravity Forms integration hooks.
	 */
	public function init(): void {
		$this->write_log( 'Initializing Gravity Forms integration' );

		// Add captcha widget before multi-page next and submit buttons.
		add_filter( 'gform_next_button', array( $this, 'add_captcha_widget' ), 10, 2 );
		add_filter( 'gform_submit_button', array( $this, 'add_captcha_widget' ), 10, 2 );

		// Verify captcha solution during form validation. Accept the $context
		// argument (form-submit / api-submit / api-validate, since GF 2.6.3.2) so
		// REST API submissions — where the widget is never rendered — can be skipped.
		add_filter( 'gform_validation', array( $this, 'verify_captcha_gravity_forms' ), 10, 2 );

		// Enqueue scripts for Gravity Forms pages.
		add_action( 'wp_enqueue_scripts', array( $this, 'enqueue_scripts' ) );
	}

	/**
	 * Add Private Captcha widget before a next or submit button.
	 *
	 * @param string              $button_input The submit button HTML string.
	 * @param array<string,mixed> $form         The current form object.
	 * @return string Modified button HTML with captcha widget prepended.
	 */
	public function add_captcha_widget( string $button_input, array $form ): string {
		ob_start();
		Widget::render( '--border-radius: 0.25rem; font-size: 1rem !important;' );
		$widget = ob_get_clean();
		$widget = false !== $widget ? $widget : '';
		// The most important part: "no-reset" trick due to gforms applying very aggressive CSS reset.
		return '<div class="gform_private_captcha_container gform-theme__no-reset--el gform-theme__no-reset--children">' . $widget . '</div>' . $button_input;
	}

	/**
	 * Verify captcha solution during Gravity Forms validation.
	 *
	 * The captcha widget is only rendered on the HTML form path (via the
	 * gform_submit_button / gform_next_button filters). REST API v2 submissions
	 * (api-submit / api-validate contexts, since Gravity Forms 2.6.3.2) deliver a
	 * JSON body that WordPress parses into WP_REST_Request, not $_POST, so the
	 * widget can neither be rendered nor solved there. Verification is therefore
	 * skipped for any context other than form-submit to avoid blocking REST API
	 * submissions the integration has no mechanism to protect.
	 *
	 * @param array<string,mixed> $validation_result The validation result array containing 'is_valid' and 'form'.
	 * @param string              $context           The submission context. Possible values: form-submit, api-submit, api-validate.
	 * @return array<string,mixed> Modified validation result.
	 */
	public function verify_captcha_gravity_forms( array $validation_result, string $context = 'form-submit' ): array {
		if ( ! $this->is_enabled() ) {
			$this->write_log( 'Skipping captcha verification as Gravity Forms integration is not enabled' );
			return $validation_result;
		}

		if ( 'form-submit' !== $context ) {
			$this->write_log( "Skipping captcha verification for Gravity Forms context: {$context}" );
			return $validation_result;
		}

		if ( ! $this->client->is_available() ) {
			$this->write_log( 'Captcha service is currently unavailable for Gravity Forms' );
			$validation_result['is_valid'] = false;
			$form_id                       = absint( $validation_result['form']['id'] ?? 0 );
			if ( $form_id ) {
				add_filter(
					'gform_validation_message_' . $form_id,
					array( $this, 'get_validation_message_unavailable' ),
					10,
					2
				);
			}
			return $validation_result;
		}

		if ( ! parent::verify_captcha() ) {
			$this->write_log( 'Private Captcha verification failed for Gravity Forms' );
			$validation_result['is_valid'] = false;
			$form_id                       = absint( $validation_result['form']['id'] ?? 0 );
			if ( $form_id ) {
				add_filter(
					'gform_validation_message_' . $form_id,
					array( $this, 'get_validation_message_failed' ),
					10,
					2
				);
			}
			return $validation_result;
		}

		$this->write_log( 'Private Captcha verification succeeded for Gravity Forms' );
		return $validation_result;
	}

	/**
	 * Get validation message when captcha service is unavailable.
	 *
	 * @param string              $message The default validation message.
	 * @param array<string,mixed> $form    The current form object.
	 * @return string The modified validation message.
	 */
	public function get_validation_message_unavailable( string $message, array $form ): string {
		return '<div class="gform_validation_errors"><h2 class="gform_submission_error hide_summary"><span class="gform-icon gform-icon--close"></span>'
			. esc_html__( 'Captcha service is currently unavailable. Please try again later.', 'private-captcha' )
			. '</h2></div>';
	}

	/**
	 * Get validation message when captcha verification fails.
	 *
	 * @param string              $message The default validation message.
	 * @param array<string,mixed> $form    The current form object.
	 * @return string The modified validation message.
	 */
	public function get_validation_message_failed( string $message, array $form ): string {
		return '<div class="gform_validation_errors"><h2 class="gform_submission_error hide_summary"><span class="gform-icon gform-icon--close"></span>'
			. parent::verification_error_html()
			. '</h2></div>';
	}

	/**
	 * Enqueue Private Captcha widget script with Gravity Forms-specific handlers.
	 */
	public function enqueue_scripts(): void {
		$gf_custom_js = '
                document.addEventListener("gform/post_render", function(event) {
                    var formElement = document.getElementById("gform_" + event.detail.formId);
                    if (!formElement) return;

                    // Initialize any freshly-inserted .private-captcha divs (idempotent: skips
                    // elements already carrying data-attached). mandatory for AJAX-rendered pages.
                    var newWidgets = [];
                    if (typeof window.privateCaptcha !== "undefined" && typeof window.privateCaptcha.setup === "function") {
                        newWidgets = window.privateCaptcha.setup() || [];
                    }

                    // Bind init/reset/finish + disable submit for the new widgets only.
                    if (newWidgets.length > 0) {
                        pcSetupPrivateCaptchaWidgets(newWidgets, defaultSubmitBtnSelector);
                    }

                    // Reset already-initialized widgets (e.g. the initial page widget on a
                    // non-AJAX re-render of the same page) — skipped for fresh divs, which
                    // pcSetupPrivateCaptchaWidgets just handled.
                    var widgets = formElement.querySelectorAll(".private-captcha");
                    widgets.forEach(function(widget) {
                        if (widget && widget.hasOwnProperty("_privateCaptcha") && widget._privateCaptcha) {
                            widget._privateCaptcha.reset();
                        }
                    });
                });';

		$gf_custom_css = '
            .gform_private_captcha_container {
                flex: 1 1 100%;
            }
            .gform_private_captcha_container .private-captcha {
                margin-bottom: 1rem;
            }
        ';

		Assets::enqueue( 'private-captcha-widget', $gf_custom_js, $gf_custom_css );
	}
}
