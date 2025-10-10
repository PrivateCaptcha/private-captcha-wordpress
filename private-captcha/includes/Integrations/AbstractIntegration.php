<?php
/**
 * Abstract base class for integrations in Private Captcha WordPress plugin.
 *
 * @package PrivateCaptchaWP
 */

declare(strict_types=1);

namespace PrivateCaptchaWP\Integrations;

use PrivateCaptchaWP\Client;
use PrivateCaptchaWP\Assets;

/**
 * Abstract base class for form integrations
 */
abstract class AbstractIntegration implements IntegrationInterface {

	/**
	 * The Private Captcha client instance.
	 *
	 * @var Client
	 */
	protected Client $client;

	/**
	 * Constructor to initialize the integration.
	 *
	 * @param Client $client The Private Captcha client instance.
	 */
	public function __construct( Client $client ) {
		$this->client = $client;
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
		$solution = sanitize_text_field( wp_unslash( $_POST[ Client::FORM_FIELD ] ?? '' ) );
		$result   = $this->client->verify_solution( $solution );

		$this->write_log( 'Private Captcha verification finished. result=' . $result );

		return $result;
	}

	/**
	 * Enqueue Private Captcha widget script.
	 *
	 * @param string $handle WordPress script handle.
	 */
	public function enqueue_scripts( string $handle = 'private-captcha-widget' ): void {
		Assets::enqueue( $handle );
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
