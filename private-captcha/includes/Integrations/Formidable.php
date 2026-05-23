<?php
/**
 * Formidable integration for Private Captcha WordPress plugin.
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

/**
 * Formidable integration class
 */
class Formidable extends AbstractIntegration {

	/**
	 * Formidable settings field.
	 *
	 * @var SettingsField
	 */
	private SettingsField $formidable_field;

	/**
	 * Constructor to initialize the integration.
	 *
	 * @param \PrivateCaptchaWP\Client $client The Private Captcha client instance.
	 */
	public function __construct( \PrivateCaptchaWP\Client $client ) {
		parent::__construct( $client );

		$this->plugin_url  = 'https://wordpress.org/plugins/formidable/';
		$this->plugin_name = 'Formidable';

		$this->formidable_field = new SettingsField(
			'formidable_enable',
			'Formidable plugin',
			'Protect Formidable forms submissions from spam.',
			'Add captcha to forms created with Formidable plugin'
		);
	}

	/**
	 * Get all settings fields for this integration.
	 *
	 * @return array<\PrivateCaptchaWP\SettingsField> Array of SettingsField instances.
	 */
	public function get_settings_fields(): array {
		return array(
			$this->formidable_field,
		);
	}

	/**
	 * Check if Formidable plugin is active.
	 *
	 * @return bool True if Formidable is active.
	 */
	public function is_available(): bool {
		return is_plugin_active( 'formidable/formidable.php' );
	}

	/**
	 * Check if Formidable integration is enabled.
	 *
	 * @return bool True if Formidable integration is enabled.
	 */
	public function is_enabled(): bool {
		return $this->formidable_field->is_enabled();
	}

	/**
	 * Initialize Formidable integration hooks.
	 */
	public function init(): void {
		$this->write_log( 'Initializing Formidable integration' );

		// Add captcha widget before submit button.
		add_filter( 'frm_submit_button_html', array( $this, 'add_captcha_widget' ), 10, 2 );

		// Verify captcha solution during form processing.
		add_filter( 'frm_validate_entry', array( $this, 'verify_captcha_formidable' ), 10, 2 );

		// Enqueue scripts.
		add_action( 'wp_enqueue_scripts', array( $this, 'enqueue_scripts' ) );
	}

	/**
	 * Add Private Captcha widget before the submit button.
	 *
	 * @param string               $button The submit button HTML.
	 * @param array<string, mixed> $args   Additional arguments (contains 'form').
	 * @return string Modified HTML.
	 */
	public function add_captcha_widget( string $button, array $args ): string {
		if ( ! $this->is_enabled() ) {
			return $button;
		}

		ob_start();
		Widget::render( '--border-radius: 0.25rem; font-size: 1rem !important;', 'formidable-field' );
		$widget_html = ob_get_clean();
		$widget_html = false !== $widget_html ? $widget_html : '';

		return $widget_html . $button;
	}

	/**
	 * Verify captcha solution during form processing.
	 *
	 * @param array<string, mixed> $errors Form validation errors.
	 * @param array<string, mixed> $values Form values.
	 * @return array<string, mixed> Modified errors.
	 */
	public function verify_captcha_formidable( array $errors, array $values ): array {
		if ( ! $this->is_enabled() ) {
			return $errors;
		}

		if ( ! $this->client->is_available() ) {
			$errors['private_captcha'] = __( 'Captcha service is currently unavailable.', 'private-captcha' );
			return $errors;
		}

		if ( ! parent::verify_captcha() ) {
			$errors['private_captcha'] = __( 'Captcha verification failed. Please try again.', 'private-captcha' );
		}

		return $errors;
	}

	/**
	 * Enqueue Private Captcha widget script with Formidable-specific handlers.
	 */
	public function enqueue_scripts(): void {
		$custom_js = '
                jQuery(document).on("frmFormErrors", function(event, form, response) {
                    if (form) {
                        pcResetCaptchaWidgetWP(form);
                    }
                });
                jQuery(document).on("frmPageChanged", function(event, form, response) {
                    if (form) {
                        pcResetCaptchaWidgetWP(form);
                    }
                });';

		Assets::enqueue( 'private-captcha-widget', $custom_js, '', '.frm_button_submit' );
	}
}
