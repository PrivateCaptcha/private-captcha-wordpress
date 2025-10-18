<?php
/**
 * Abstract base class for integrations in Private Captcha WordPress plugin.
 *
 * @package PrivateCaptchaWP
 */

declare(strict_types=1);

namespace PrivateCaptchaWP\Integrations;

use PrivateCaptchaWP\Assets;
use PrivateCaptchaWP\Settings;

/**
 * Abstract base class for form integrations
 */
abstract class AbstractIntegration implements IntegrationInterface {

	/**
	 * The Private Captcha client instance.
	 *
	 * @var \PrivateCaptchaWP\Client
	 */
	protected \PrivateCaptchaWP\Client $client;

	/**
	 * The plugin URL for this integration.
	 *
	 * @var string
	 */
	protected string $plugin_url = '';

	/**
	 * The plugin name for this integration.
	 *
	 * @var string
	 */
	protected string $plugin_name = '';

	/**
	 * Constructor to initialize the integration.
	 *
	 * @param \PrivateCaptchaWP\Client $client The Private Captcha client instance.
	 */
	public function __construct( \PrivateCaptchaWP\Client $client ) {
		$this->client = $client;
	}

	/**
	 * Check if any of the integration's settings are enabled.
	 *
	 * @return bool True if any setting is enabled.
	 */
	public function has_enabled_settings(): bool {
		$fields = $this->get_settings_fields();
		foreach ( $fields as $field ) {
			if ( $field->is_enabled() ) {
				return true;
			}
		}
		return false;
	}

	/**
	 * Check if the integration is enabled in settings.
	 * Default implementation checks if any settings field is enabled.
	 *
	 * @return bool True if the integration is enabled.
	 */
	public function is_enabled(): bool {
		return $this->has_enabled_settings();
	}

	/**
	 * Get the plugin URL for this integration.
	 *
	 * @return string The plugin URL or empty string if not applicable.
	 */
	public function get_plugin_url(): string {
		return $this->plugin_url;
	}

	/**
	 * Get the plugin name for this integration.
	 *
	 * @return string The plugin name or empty string if not applicable.
	 */
	public function get_plugin_name(): string {
		return $this->plugin_name;
	}

	/**
	 * Verify captcha solution from form submission.
	 *
	 * @return bool True if captcha verification succeeds.
	 */
	protected function verify_captcha(): bool {
		if ( ! $this->client->is_available() ) {
			return false;
		}

		// phpcs:ignore WordPress.Security.NonceVerification.Missing -- This method is used for captcha verification, not WordPress form processing
		$solution = sanitize_text_field( wp_unslash( $_POST[ \PrivateCaptchaWP\Client::FORM_FIELD ] ?? '' ) );
		$result   = $this->client->verify_solution( $solution );

		$this->write_log( 'Private Captcha verification finished. result=' . $result );

		return $result;
	}

	/**
	 * Debugging helper.
	 *
	 * @param mixed $data Anything you want to log.
	 */
	protected function write_log( $data ): void {
		// add error_log call here for debugging.
	}
}
