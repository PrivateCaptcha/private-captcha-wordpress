<?php
/**
 * WP-CLI commands for Private Captcha emergency management.
 *
 * @package PrivateCaptchaWP
 */

declare(strict_types=1);

namespace PrivateCaptchaWP;

use WP_CLI;

/**
 * WP-CLI commands for Private Captcha emergency management
 */
class CLI {

	/**
	 * Update API key.
	 *
	 * ## OPTIONS
	 *
	 * <api_key>
	 * : New API key to set
	 *
	 * ## EXAMPLES
	 *
	 *     wp private-captcha update-api-key "your-new-api-key"
	 *
	 * @param array<int, string>   $args       Command arguments containing the API key.
	 * @param array<string, mixed> $assoc_args Associative arguments (unused).
	 * @return void
	 * @when after_wp_load
	 */
	public function update_api_key( $args, $assoc_args ): void {
		unset( $assoc_args );

		// Check if API key argument is provided.
		if ( empty( $args[0] ) ) {
			// @phpstan-ignore-next-line
			WP_CLI::error( 'Please provide an API key: wp private-captcha update-api-key "your-api-key"' );
		}

		// Update the API key setting.
		$api_key = $args[0];
		Settings::update_option( 'api_key', $api_key );
		// @phpstan-ignore-next-line
		WP_CLI::success( 'API key updated successfully.' );
	}

	/**
	 * Disable captcha verification on login form (emergency use).
	 *
	 * ## EXAMPLES
	 *
	 *     wp private-captcha disable-login
	 *
	 * @param array<int, string>   $args       Command arguments (unused).
	 * @param array<string, mixed> $assoc_args Associative arguments (unused).
	 * @return void
	 * @when after_wp_load
	 */
	public function disable_login( $args, $assoc_args ): void {
		unset( $args );
		unset( $assoc_args );

		// Disable login form captcha verification.
		Settings::update_option( 'enable_login', false );
		// @phpstan-ignore-next-line
		WP_CLI::success( 'Login form captcha disabled. You can now login without captcha verification.' );
		// @phpstan-ignore-next-line
		WP_CLI::warning( 'Remember to fix your API key and re-enable login captcha in admin settings.' );
	}
}

/**
 * Register WP-CLI commands with WP-CLI.
 */
if ( defined( 'WP_CLI' ) && WP_CLI ) {
	// @phpstan-ignore-next-line
	WP_CLI::add_command( 'private-captcha', 'PrivateCaptchaWP\CLI' );
}
