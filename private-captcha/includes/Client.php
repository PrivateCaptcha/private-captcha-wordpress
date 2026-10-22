<?php
/**
 * WordPress wrapper for PrivateCaptcha PHP Client.
 *
 * @package PrivateCaptchaWP
 */

declare(strict_types=1);

namespace PrivateCaptchaWP;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

use PrivateCaptcha\Client as PrivateCaptchaClient;
use PrivateCaptcha\Exceptions\ApiKeyException;
use PrivateCaptcha\Exceptions\PrivateCaptchaException;

/**
 * Stub for debugging function made for WPCS to shut up about bogus "Debug code should not normally be used in production".
 *
 * @param mixed $data logging data.
 */
function write_log( $data ): void {
	// add error_log function here for debugging.
}

/**
 * WordPress wrapper for PrivateCaptcha PHP Client
 */
class Client {

	private const SOLUTIONS_COUNT = 16;
	private const SOLUTION_LENGTH = 8;
	public const FORM_FIELD       = 'wp-private-captcha-solution';

	/**
	 * The Private Captcha client instance.
	 *
	 * @var PrivateCaptchaClient|null
	 */
	private ?PrivateCaptchaClient $client = null;

	/**
	 * Last error from testing API.
	 *
	 * @var string|null
	 */
	private ?string $last_error = null;

	/**
	 * Request-local verification results, grouped by cache scope, sitekey and solution.
	 *
	 * @var array<string, array<string, array<string, array{result: bool, last_error: string|null}>>>
	 */
	private array $verification_cache = array();

	/**
	 * Get the last error message from testing.
	 *
	 * @return string|null
	 */
	public function get_last_error(): ?string {
		return $this->last_error;
	}

	/**
	 * Constructor to initialize the Private Captcha client.
	 */
	public function __construct() {
		$this->client = null;
	}

	/**
	 * Initialize or reinitialize the internal Private Captcha client.
	 *
	 * @param string $api_key      The API key for authentication.
	 * @param string $custom_domain The custom domain to use.
	 * @param bool   $eu_isolation  Whether to use EU isolation.
	 */
	public function update( string $api_key, string $custom_domain, bool $eu_isolation ): void {
		$this->verification_cache = array();

		try {
			// Only pass domain argument if custom domain is set or EU isolation is enabled.
			if ( ! empty( $custom_domain ) ) {
				$this->client = new PrivateCaptchaClient( $api_key, $custom_domain, self::FORM_FIELD );
			} elseif ( $eu_isolation ) {
				$this->client = new PrivateCaptchaClient( $api_key, PrivateCaptchaClient::EU_DOMAIN, formField: self::FORM_FIELD );
			} else {
				$this->client = new PrivateCaptchaClient( $api_key, formField: self::FORM_FIELD );
			}
			write_log( 'Private Captcha client has been constructed' );
		} catch ( \PrivateCaptcha\Exceptions\PrivateCaptchaException $e ) {
			$this->client = null;
			return;
		}
	}

	/**
	 * Reset the client so we cannot perform any actions
	 */
	public function reset(): void {
		$this->client             = null;
		$this->last_error         = null;
		$this->verification_cache = array();
		write_log( 'Private Captcha client has been reset' );
	}

	/**
	 * Clear cached verification results at the start of a new request.
	 */
	public function clear_verification_cache(): void {
		$this->verification_cache = array();
	}

	/**
	 * Check if the client is functional
	 *
	 * @return bool True if client is functional, false otherwise.
	 */
	public function is_available(): bool {
		return null !== $this->client;
	}

	/**
	 * Verify a captcha solution.
	 *
	 * @param string      $solution Solution to verify.
	 * @param string|null $sitekey  An optional sitekey to verify solution against.
	 * @param string|null $cache_scope Optional request-local cache scope.
	 * @param string|null $parent_cache_scope Optional parent cache scope shared with related verifiers.
	 * @return bool True if verification succeeds, false otherwise.
	 */
	public function verify_solution( string $solution, ?string $sitekey = null, ?string $cache_scope = null, ?string $parent_cache_scope = null ): bool {
		$this->last_error = null;

		if ( null === $this->client ) {
			$this->last_error = 'Client not initialized';
			return false;
		}

		$cache_scopes = array();
		$sitekey_key  = $sitekey ?? '';

		if ( null !== $cache_scope ) {
			$cache_scopes[] = $cache_scope;
			if ( null !== $parent_cache_scope && $parent_cache_scope !== $cache_scope ) {
				$cache_scopes[] = $parent_cache_scope;
			}

			foreach ( $cache_scopes as $scope ) {
				if ( isset( $this->verification_cache[ $scope ][ $sitekey_key ][ $solution ] ) ) {
					$cached           = $this->verification_cache[ $scope ][ $sitekey_key ][ $solution ];
					$this->last_error = $cached['last_error'];

					// Promote a parent hit into the local scope for subsequent lookups.
					$this->verification_cache[ $cache_scope ][ $sitekey_key ][ $solution ] = $cached;

					return $cached['result'];
				}
			}
		}

		$success = false;

		try {
			$result = $this->client->verify( $solution, sitekey: $sitekey );

			$success = $result->isOK();
			if ( ! $success ) {
				write_log( 'Private Captcha verification failed. result=' . $result );
				$this->last_error = (string) $result;
			}
		} catch ( PrivateCaptchaException $e ) {
			$this->last_error = $e->getMessage();
			write_log( 'Private Captcha verification error: ' . $e->getMessage() );
		}

		if ( ! empty( $cache_scopes ) ) {
			$cache_entry = array(
				'result'     => $success,
				'last_error' => $this->last_error,
			);

			foreach ( $cache_scopes as $scope ) {
				$this->verification_cache[ $scope ][ $sitekey_key ][ $solution ] = $cache_entry;
			}
		}

		return $success;
	}

	/**
	 * Test current settings by validating the user's site key and verifying a stub solution.
	 *
	 * First fetches a puzzle for the user's site key (the /puzzle endpoint rejects
	 * unknown site keys, e.g. HTTP 400 for malformed keys) to catch a mistyped or
	 * nonexistent site key at save time. Then confirms the API key by verifying a
	 * stub solution against the public demo property. The demo site key is used for
	 * the verify step because the stub solution deterministically yields
	 * TEST_PROPERTY_ERROR only for the public demo property.
	 *
	 * NOTE: Theoretically we actually CAN "succeed" this even if settings are
	 * invalid due to "eventual" verification nature of /verify endpoint.
	 *
	 * @param string $sitekey The user's site key to validate.
	 * @return bool True if settings are valid, false otherwise.
	 */
	public function test_current_settings( string $sitekey ): bool {
		$this->last_error = null;

		if ( null === $this->client ) {
			$this->last_error = 'Client not initialized';
			return false;
		}

		try {
			// Validate the user's site key first: /puzzle rejects unknown site keys.
			// The CDN edge may serve an error body (e.g. "Forbidden") with HTTP 200,
			// so the response body is also verified to be a real puzzle, not just a 200.
			// The puzzle API requires the Origin to match the property's configured
			// domain (only the public demo key bypasses this), so the site's real
			// origin is sent for the user-sitekey request.
			$user_puzzle = $this->fetch_test_puzzle( $sitekey, $this->get_site_origin() );
			if ( null === $user_puzzle ) {
				return false; // Error already set in fetch_test_puzzle.
			}

			if ( ! $this->is_valid_puzzle( $user_puzzle ) ) {
				$this->last_error = 'Invalid site key: puzzle endpoint returned an unexpected response';
				write_log( 'Private Captcha settings test failed: invalid puzzle for user site key' );
				return false;
			}

			// The stub verify only produces TEST_PROPERTY_ERROR for the public demo
			// property, so the API-key validity check always uses the demo site key.
			$test_sitekey = 'aaaaaaaabbbbccccddddeeeeeeeeeeee';
			$puzzle       = $this->fetch_test_puzzle( $test_sitekey );
			if ( null === $puzzle ) {
				return false; // Error already set in fetch_test_puzzle.
			}

			// Create empty solutions (stub).
			$empty_solutions_bytes = str_repeat( "\0", self::SOLUTIONS_COUNT * self::SOLUTION_LENGTH );
			// phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.obfuscation_base64_encode -- Used for legitimate captcha verification, not code obfuscation.
			$solutions_str = base64_encode( $empty_solutions_bytes );
			$payload       = $solutions_str . '.' . $puzzle;

			$output = $this->client->verify( $payload, sitekey: $test_sitekey );

			if ( $output->success && \PrivateCaptcha\Enums\VerifyCode::TEST_PROPERTY_ERROR === $output->code ) {
				return true;
			}

			$this->last_error = 'Verification failed or incorrect code';
			return false;

		} catch ( PrivateCaptchaException $e ) {
			$this->last_error = $e->getMessage();
			write_log( 'Private Captcha settings test failed: ' . $e->getMessage() );
			return false;
		}
	}

	/**
	 * Check whether a puzzle response body looks like a real puzzle token.
	 *
	 * A puzzle is a base64-encoded payload and signature separated by a dot
	 * (e.g. "data.signature"). Error bodies such as "Forbidden" or "Bad Request",
	 * which the CDN edge may serve with HTTP 200, do not match this shape.
	 *
	 * @param string $body The puzzle response body to validate.
	 * @return bool True if the body looks like a puzzle token, false otherwise.
	 */
	private function is_valid_puzzle( string $body ): bool {
		$body = trim( $body );

		if ( '' === $body ) {
			return false;
		}

		$parts = explode( '.', $body );

		// A puzzle is exactly "data.signature": two non-empty base64 segments.
		if ( 2 !== count( $parts ) ) {
			return false;
		}

		foreach ( $parts as $segment ) {
			if ( '' === $segment ) {
				return false;
			}

			// Standard or URL-safe base64 alphabet with optional trailing padding.
			if ( 1 !== preg_match( '#^[A-Za-z0-9+/_-]+={0,2}$#', $segment ) ) {
				return false;
			}
		}

		return true;
	}

	/**
	 * Get the site's origin (scheme + host) for the Origin header.
	 *
	 * The puzzle API requires the Origin to match the property's configured
	 * domain, so the site's actual origin is used when validating the user's
	 * site key. Falls back to 'not.empty' for localhost/loopback addresses or
	 * when the home URL cannot be determined, since those origins never match
	 * a configured property domain.
	 *
	 * @return string The site origin, e.g. "https://example.com".
	 */
	private function get_site_origin(): string {
		$home = home_url();
		if ( ! is_string( $home ) || '' === $home ) {
			return 'not.empty';
		}

		$host = wp_parse_url( $home, PHP_URL_HOST );
		if ( ! is_string( $host ) || '' === $host ) {
			return 'not.empty';
		}

		$host_lower = strtolower( $host );
		if (
			'localhost' === $host_lower
			|| str_ends_with( $host_lower, '.localhost' )
			|| str_starts_with( $host_lower, '127.' )
			|| '::1' === $host_lower
			|| '0.0.0.0' === $host_lower
		) {
			return 'not.empty';
		}

		$origin = preg_replace( '#^(https?://[^/]+).*#i', '$1', $home );
		if ( ! is_string( $origin ) ) {
			return 'not.empty';
		}

		return $origin;
	}

	/**
	 * Fetch test puzzle from Private Captcha API.
	 *
	 * @param string $sitekey The site key to fetch puzzle for.
	 * @param string $origin  The Origin header value to send. Defaults to
	 *                        'not.empty', which only the public demo key accepts.
	 * @return string|null The puzzle string or null on failure.
	 */
	private function fetch_test_puzzle( string $sitekey, string $origin = 'not.empty' ): ?string {
		if ( null === $this->client ) {
			$this->last_error = 'Client not initialized';
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
					'Origin' => $origin,
				),
			)
		);

		if ( is_wp_error( $response ) ) {
			$this->last_error = $response->get_error_message();
			write_log( 'Failed to fetch test puzzle: ' . $response->get_error_message() );
			return null;
		}

		$http_code = wp_remote_retrieve_response_code( $response );
		if ( 200 !== $http_code ) {
			$this->last_error = get_status_header_desc( (int) $http_code );
			if ( empty( $this->last_error ) ) {
				$this->last_error = "HTTP {$http_code}";
			}
			write_log( "Failed to fetch test puzzle: HTTP {$http_code}" );
			return null;
		}

		$body = wp_remote_retrieve_body( $response );
		if ( empty( $body ) ) {
			$this->last_error = 'Empty response body from puzzle endpoint';
			write_log( 'Failed to fetch test puzzle: empty response body' );
			return null;
		}
		return $body;
	}
}
