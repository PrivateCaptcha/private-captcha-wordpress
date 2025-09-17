<?php
/**
 * WordPress wrapper for PrivateCaptcha PHP Client.
 *
 * @package PrivateCaptchaWP
 */

declare(strict_types=1);

namespace PrivateCaptchaWP;

use PrivateCaptcha\Client as PrivateCaptchaClient;
use PrivateCaptcha\Exceptions\ApiKeyException;
use PrivateCaptcha\Exceptions\PrivateCaptchaException;

/**
 * WordPress wrapper for PrivateCaptcha PHP Client
 */
class Client {

	private const SOLUTIONS_COUNT = 16;
	private const SOLUTION_LENGTH = 8;

	/**
	 * The Private Captcha client instance.
	 *
	 * @var PrivateCaptchaClient|null
	 */
	private ?PrivateCaptchaClient $client = null;

	/**
	 * Constructor to initialize the Private Captcha client.
	 *
	 * @param string $api_key      The API key for authentication.
	 * @param string $custom_domain The custom domain to use.
	 * @param bool   $eu_isolation  Whether to use EU isolation.
	 */
	public function __construct( string $api_key, string $custom_domain, bool $eu_isolation ) {
		// Only pass domain argument if custom domain is set or EU isolation is enabled.
		if ( ! empty( $custom_domain ) ) {
			$this->client = new PrivateCaptchaClient( $api_key, $custom_domain );
		} elseif ( $eu_isolation ) {
			$this->client = new PrivateCaptchaClient( $api_key, PrivateCaptchaClient::EU_DOMAIN );
		} else {
			$this->client = new PrivateCaptchaClient( $api_key );
		}
	}

	/**
	 * Verify a captcha solution.
	 *
	 * @param string $solution The solution to verify.
	 * @return bool True if verification succeeds, false otherwise.
	 */
	public function verify_solution( string $solution ): bool {
		if ( null === $this->client ) {
			return false;
		}

		try {
			$result = $this->client->verify( $solution );

			return $result->success;
		} catch ( PrivateCaptchaException $e ) {
			wp_debug_log( 'Private Captcha verification error: ' . $e->getMessage(), 'private-captcha' );

			return false;
		}
	}

	/**
	 * Verify a captcha request.
	 *
	 * @param array<string, mixed>|null $form_data The form data to verify, or null to use $_POST.
	 * @return bool True if verification succeeds, false otherwise.
	 */
	public function verify_request( ?array $form_data = null ): bool {
		if ( null === $this->client ) {
			return false;
		}

		if ( null === $form_data ) {
			// phpcs:ignore WordPress.Security.NonceVerification.Missing -- This method is used for captcha verification, not WordPress form processing
			$form_data = $_POST;
		}

		try {
			$this->client->verifyRequest( $form_data );

			return true;
		} catch ( PrivateCaptchaException $e ) {
			wp_debug_log( 'Private Captcha request verification error: ' . $e->getMessage(), 'private-captcha' );

			return false;
		}
	}

	/**
	 * Test current settings by fetching test puzzle and verifying stub solution.
	 * NOTE: Theoretically we actually CAN "succeed" this even if settings are
	 * invalid due to "eventual" verification nature of /verify endpoint.
	 *
	 * @param string $sitekey The site key to test.
	 * @return bool True if settings are valid, false otherwise.
	 */
	public function test_current_settings( string $sitekey ): bool {
		if ( null === $this->client ) {
			return false;
		}

		try {
			// Fetch test puzzle.
			$puzzle = $this->fetch_test_puzzle( $sitekey );
			if ( null === $puzzle ) {
				return false;
			}

			// Create empty solutions (stub).
			$empty_solutions_bytes = str_repeat( "\0", self::SOLUTIONS_COUNT * self::SOLUTION_LENGTH );
			// phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.obfuscation_base64_encode -- Used for legitimate captcha verification, not code obfuscation.
			$solutions_str = base64_encode( $empty_solutions_bytes );
			$payload       = $solutions_str . '.' . $puzzle;

			$output = $this->client->verify( $payload );

			return $output->success && \PrivateCaptcha\Enums\VerifyCode::TEST_PROPERTY_ERROR === $output->code;

		} catch ( PrivateCaptchaException $e ) {
			wp_debug_log( 'Private Captcha settings test failed: ' . $e->getMessage(), 'private-captcha' );
			return false;
		}
	}

	/**
	 * Fetch test puzzle from Private Captcha API.
	 *
	 * @param string $sitekey The site key to fetch puzzle for.
	 * @return string|null The puzzle string or null on failure.
	 */
	private function fetch_test_puzzle( string $sitekey ): ?string {
		if ( null === $this->client ) {
			return null;
		}

		$domain     = $this->client->getDomain();
		$puzzle_url = "https://{$domain}/puzzle?sitekey={$sitekey}";

		$response = wp_remote_get(
			$puzzle_url,
			array(
				'timeout'     => 10,
				'redirection' => 5,
				'headers'     => array(
					'Origin' => 'not.empty',
				),
			)
		);

		if ( is_wp_error( $response ) ) {
			wp_debug_log( 'Failed to fetch test puzzle: ' . $response->get_error_message(), 'private-captcha' );
			return null;
		}

		$http_code = wp_remote_retrieve_response_code( $response );
		if ( 200 !== $http_code ) {
			wp_debug_log( "Failed to fetch test puzzle: HTTP {$http_code}", 'private-captcha' );
			return null;
		}

		$body = wp_remote_retrieve_body( $response );
		return ! empty( $body ) ? $body : null;
	}
}
