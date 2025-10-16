<?php
/**
 * WPForms integration for Private Captcha WordPress plugin.
 *
 * @package PrivateCaptchaWP
 */

declare(strict_types=1);

namespace PrivateCaptchaWP\Integrations;

use PrivateCaptchaWP\Assets;
use PrivateCaptchaWP\Settings;
use PrivateCaptchaWP\SettingsField;
use PrivateCaptchaWP\Widget;

/**
 * WPForms integration class
 */
class WPForms extends AbstractIntegration {

	/**
	 * WPForms settings field.
	 *
	 * @var SettingsField
	 */
	private SettingsField $wpforms_field;

	/**
	 * Constructor to initialize the integration.
	 *
	 * @param \PrivateCaptchaWP\Client $client The Private Captcha client instance.
	 */
	public function __construct( \PrivateCaptchaWP\Client $client ) {
		parent::__construct( $client );

		$this->plugin_url  = 'https://wordpress.org/plugins/wpforms-lite/';
		$this->plugin_name = 'WPForms Lite';

		$this->wpforms_field = new SettingsField(
			'wpforms_enable_wpforms',
			'WPForms plugin',
			'Protect WPForms submissions from spam.',
			'Add captcha to forms created with WPForms plugin'
		);
	}

	/**
	 * Get all settings fields for this integration.
	 *
	 * @return array<\PrivateCaptchaWP\SettingsField> Array of SettingsField instances.
	 */
	public function get_settings_fields(): array {
		return array(
			$this->wpforms_field,
		);
	}

	/**
	 * Check if WPForms plugin is active.
	 *
	 * @return bool True if WPForms is active.
	 */
	public function is_available(): bool {
		return is_plugin_active( 'wpforms/wpforms.php' ) || is_plugin_active( 'wpforms-lite/wpforms.php' );
	}

	/**
	 * Check if WPForms integration is enabled.
	 *
	 * @return bool True if WPForms integration is enabled.
	 */
	public function is_enabled(): bool {
		return $this->wpforms_field->is_enabled();
	}

	/**
	 * Initialize WPForms integration hooks.
	 */
	public function init(): void {
		$this->write_log( 'Initializing WPForms integration' );

		// Add captcha widget before submit button.
		add_action( 'wpforms_display_submit_before', array( $this, 'add_captcha_widget' ), 10, 1 );

		// Verify captcha solution during form processing.
		add_action( 'wpforms_process', array( $this, 'verify_captcha_wpforms' ), 5, 3 );

		// Enqueue scripts on WPForms footer.
		add_action( 'wp_enqueue_scripts', array( $this, 'enqueue_scripts' ) );
	}

	/**
	 * Add Private Captcha widget before the submit button.
	 *
	 * @param array<string, mixed> $form_data Form data and settings.
	 */
	public function add_captcha_widget( array $form_data ): void {
		Widget::render( '--border-radius: 0.25rem; font-size: 1rem !important;', 'wpforms-field' );
	}

	/**
	 * Verify captcha solution during form processing.
	 *
	 * @param array<string, mixed> $fields    Form fields data.
	 * @param array<string, mixed> $entry     Form submission raw data.
	 * @param array<string, mixed> $form_data Form data and settings.
	 */
	public function verify_captcha_wpforms( array $fields, array $entry, array $form_data ): void {
		if ( ! $this->is_enabled() ) {
			$this->write_log( 'Skipping captcha verification as WPForms integration is not enabled' );
			return;
		}

		if ( ! $this->client->is_available() ) {
			$this->add_form_error( $form_data, __( 'Captcha service is currently unavailable.', 'private-captcha' ) );
			return;
		}

		if ( ! parent::verify_captcha() ) {
			$this->add_form_error( $form_data, __( 'Captcha verification failed. Please try again.', 'private-captcha' ) );
			return;
		}
	}

	/**
	 * Add form error to WPForms processing.
	 *
	 * @param array<string, mixed> $form_data Form data and settings.
	 * @param string               $message   Error message.
	 */
	private function add_form_error( array $form_data, string $message ): void {
		$form_id = absint( $form_data['id'] ?? 0 );
		if ( ! $form_id ) {
			$this->write_log( 'Form ID is missing from form_data' );
			return;
		}

		if ( function_exists( 'wpforms' ) ) {
			$process = wpforms()->obj( 'process' );
			if ( $process && isset( $process->errors ) ) {
				$process->errors[ $form_id ]['header'] = $message;
			}
		} else {
			$this->write_log( 'wpforms() function is missing.' );
		}
	}
}
