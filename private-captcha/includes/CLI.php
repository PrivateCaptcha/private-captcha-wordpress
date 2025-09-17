<?php

declare(strict_types=1);

namespace PrivateCaptchaWP;

use WP_CLI;

/**
 * WP-CLI commands for Private Captcha emergency management
 */
class CLI {

	/**
	 * Update API key
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
	 * @param array<int, string>   $args
	 * @param array<string, mixed> $assoc_args
	 * @when after_wp_load
	 */
	public function update_api_key( $args, $assoc_args ): void {
		if ( empty( $args[0] ) ) {
			WP_CLI::error( 'Please provide an API key: wp private-captcha update-api-key "your-api-key"' );
		}

		$api_key = $args[0];
		Settings::update_option( 'api_key', $api_key );
		WP_CLI::success( 'API key updated successfully.' );
	}

	/**
	 * Disable captcha verification on login form (emergency use)
	 *
	 * ## EXAMPLES
	 *
	 *     wp private-captcha disable-login
	 *
	 * @param array<int, string>   $args
	 * @param array<string, mixed> $assoc_args
	 * @when after_wp_load
	 */
	public function disable_login( $args, $assoc_args ): void {
		Settings::update_option( 'enable_login', false );
		WP_CLI::success( 'Login form captcha disabled. You can now login without captcha verification.' );
		WP_CLI::warning( 'Remember to fix your API key and re-enable login captcha in admin settings.' );
	}
}

// Register WP-CLI commands
if ( defined( 'WP_CLI' ) && WP_CLI ) {
	WP_CLI::add_command( 'private-captcha', 'PrivateCaptchaWP\CLI' );
}
