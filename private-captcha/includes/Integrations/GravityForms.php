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

		// Add captcha widget before the submit button.
		add_filter( 'gform_submit_button', array( $this, 'add_captcha_widget' ), 10, 2 );

		// Verify captcha solution during form validation.
		add_filter( 'gform_validation', array( $this, 'verify_captcha_gravity_forms' ), 10, 1 );

		// Enqueue scripts for Gravity Forms pages.
		add_action( 'wp_enqueue_scripts', array( $this, 'enqueue_scripts' ) );
	}

	/**
	 * Add Private Captcha widget before the submit button.
	 *
	 * @param string              $button_input The submit button HTML string.
	 * @param array<string,mixed> $form         The current form object.
	 * @return string Modified button HTML with captcha widget prepended.
	 */
	public function add_captcha_widget( string $button_input, array $form ): string {
		ob_start();
		Widget::render( '--border-radius: 0.25rem; font-size: 1rem !important;', 'gform_private_captcha' );
		$widget = ob_get_clean();
		$widget = false !== $widget ? $widget : '';

		return '<div class="gform_private_captcha_container" style="width: 100%;">' . $widget . '</div>' . $button_input;
	}

	/**
	 * Verify captcha solution during Gravity Forms validation.
	 *
	 * @param array<string,mixed> $validation_result The validation result array containing 'is_valid' and 'form'.
	 * @return array<string,mixed> Modified validation result.
	 */
	public function verify_captcha_gravity_forms( array $validation_result ): array {
		if ( ! $this->is_enabled() ) {
			$this->write_log( 'Skipping captcha verification as Gravity Forms integration is not enabled' );
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
			. esc_html__( 'Captcha verification failed. Please try again.', 'private-captcha' )
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
                    var widgets = formElement.querySelectorAll(".private-captcha");
                    widgets.forEach(function(widget) {
                        if (widget && widget.hasOwnProperty("_privateCaptcha") && widget._privateCaptcha) {
                            widget._privateCaptcha.reset();
                        }
                        pcSetFormButtonEnabledWP(widget, false);
                        widget.addEventListener("privatecaptcha:init", function(e) { pcSetFormButtonEnabledWP(e.detail.element, false); });
                        widget.addEventListener("privatecaptcha:finish", function(e) { pcSetFormButtonEnabledWP(e.detail.element, true); });
                    });
                });';

		$gf_custom_css = '
            .gform_private_captcha_container {
                width: 100%;
            }
            .gform_private_captcha_container .private-captcha {
                margin-bottom: 1rem;
            }
        ';

		Assets::enqueue( 'private-captcha-widget', $gf_custom_js, $gf_custom_css );
	}
}
