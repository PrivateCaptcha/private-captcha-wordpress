<?php
/**
 * Elementor Forms integration for Private Captcha WordPress plugin.
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

/**
 * Elementor Forms integration class.
 *
 * Integrates Private Captcha with Elementor Pro's Form widget by registering
 * a custom form field type. This uses the official Elementor Form Fields API
 * documented at https://developers.elementor.com/docs/form-fields/add-new-field/
 *
 * The approach registers a custom field type (like Friendly Captcha does) rather
 * than using global form validation hooks (like Cloudflare Turnstile does).
 * The custom field approach is more correct because:
 * - It integrates into Elementor's form builder UI natively
 * - Users can drag and drop the captcha field like any other form field
 * - Validation errors are displayed per-field rather than globally
 * - The field appears in the Elementor editor with a placeholder preview
 */
class Elementor extends AbstractIntegration {

	/**
	 * Elementor Forms settings field.
	 *
	 * @var SettingsField
	 */
	private SettingsField $elementor_field;

	/**
	 * Constructor to initialize the integration.
	 *
	 * @param \PrivateCaptchaWP\Client $client The Private Captcha client instance.
	 */
	public function __construct( \PrivateCaptchaWP\Client $client ) {
		parent::__construct( $client );

		$this->plugin_url  = 'https://elementor.com/';
		$this->plugin_name = 'Elementor';

		$this->elementor_field = new SettingsField(
			'elementor_enable',
			'Elementor Forms',
			'Protect Elementor Pro form submissions from spam.',
			'Add captcha to forms created with Elementor Pro'
		);
	}

	/**
	 * Get all settings fields for this integration.
	 *
	 * @return array<\PrivateCaptchaWP\SettingsField> Array of SettingsField instances.
	 */
	public function get_settings_fields(): array {
		return array(
			$this->elementor_field,
		);
	}

	/**
	 * Check if Elementor Pro plugin is active.
	 *
	 * @return bool True if Elementor Pro is active.
	 */
	public function is_available(): bool {
		return is_plugin_active( 'elementor-pro/elementor-pro.php' );
	}

	/**
	 * Check if Elementor integration is enabled.
	 *
	 * @return bool True if Elementor integration is enabled.
	 */
	public function is_enabled(): bool {
		return $this->elementor_field->is_enabled();
	}

	/**
	 * Initialize Elementor integration hooks.
	 */
	public function init(): void {
		$this->write_log( 'Initializing Elementor integration' );

		// Register the custom form field type.
		add_action( 'elementor_pro/forms/fields/register', array( $this, 'register_field' ) );

		// Enqueue captcha widget scripts on frontend.
		add_action( 'wp_enqueue_scripts', array( $this, 'enqueue_scripts' ) );
	}

	/**
	 * Register the Private Captcha form field type with Elementor.
	 *
	 * @param mixed $form_fields_registrar The Elementor form fields registrar.
	 */
	public function register_field( $form_fields_registrar ): void {
		if ( ! Settings::is_configured() ) {
			$this->write_log( 'Skipping Elementor field registration: plugin not configured' );
			return;
		}

		require_once __DIR__ . '/ElementorField.php';

		if ( ! class_exists( '\PrivateCaptchaWP\Integrations\ElementorField' ) ) {
			$this->write_log( 'ElementorField class not available' );
			return;
		}

		$form_fields_registrar->register( new ElementorField() );
	}

	/**
	 * Enqueue Private Captcha widget script with Elementor-specific handlers.
	 */
	public function enqueue_scripts(): void {
		$elementor_custom_js = '
                jQuery(document).on("submit_success", ".elementor-form", function(event) {
                    pcResetCaptchaWidgetWP(event.target);
                });
                jQuery(document).on("error", ".elementor-form", function(event) {
                    pcResetCaptchaWidgetWP(event.target);
                });';

		$elementor_custom_css = '
            .elementor-field-type-private-captcha .private-captcha {
                margin: 0;
                max-width: 100%;
                width: 100%;
            }
        ';

		Assets::enqueue( 'private-captcha-widget', $elementor_custom_js, $elementor_custom_css );
	}
}
