<?php

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

	private ?PrivateCaptchaClient $client = null;

	public function __construct( string $api_key, string $custom_domain, bool $eu_isolation ) {
		// Only pass domain argument if custom domain is set or EU isolation is enabled
		if ( ! empty( $custom_domain ) ) {
			$this->client = new PrivateCaptchaClient( $api_key, $custom_domain );
		} elseif ( $eu_isolation ) {
			$this->client = new PrivateCaptchaClient( $api_key, PrivateCaptchaClient::EU_DOMAIN );
		} else {
			$this->client = new PrivateCaptchaClient( $api_key );
		}
	}

	public function verify_solution( string $solution ): bool {
		if ( ! isset( $this->client ) ) {
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
	 * @param array<string, mixed>|null $form_data
	 */
	public function verify_request( ?array $form_data = null ): bool {
		if ( ! isset( $this->client ) ) {
			return false;
		}

		if ( $form_data === null ) {
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
	 * Test current settings by fetching test puzzle and verifying stub solution
	 * NOTE: Theoretically we actually CAN "succeed" this even if settings are
	 * invalid due to "eventual" verification nature of /verify endpoint.
	 */
	public function test_current_settings( string $sitekey ): bool {
		if ( ! isset( $this->client ) ) {
			return false;
		}

		try {
			// Fetch test puzzle
			$puzzle = $this->fetch_test_puzzle( $sitekey );
			if ( $puzzle === null ) {
				return false;
			}

			// Create empty solutions (stub)
			$empty_solutions_bytes = str_repeat( "\0", self::SOLUTIONS_COUNT * self::SOLUTION_LENGTH );
			$solutions_str         = base64_encode( $empty_solutions_bytes );
			$payload               = $solutions_str . '.' . $puzzle;

			$output = $this->client->verify( $payload );

			return $output->success && $output->code === \PrivateCaptcha\Enums\VerifyCode::TEST_PROPERTY_ERROR;

		} catch ( PrivateCaptchaException $e ) {
			wp_debug_log( 'Private Captcha settings test failed: ' . $e->getMessage(), 'private-captcha' );
			return false;
		}
	}

	/**
	 * Fetch test puzzle from Private Captcha API
	 */
	private function fetch_test_puzzle( string $sitekey ): ?string {
		if ( ! isset( $this->client ) ) {
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
		if ( $http_code !== 200 ) {
			wp_debug_log( "Failed to fetch test puzzle: HTTP {$http_code}", 'private-captcha' );
			return null;
		}

		$body = wp_remote_retrieve_body( $response );
		return ! empty( $body ) ? $body : null;
	}
}
