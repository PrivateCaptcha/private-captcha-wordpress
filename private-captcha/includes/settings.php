<?php

declare(strict_types=1);

namespace PrivateCaptchaWP;

/**
 * Settings management class
 */
class Settings {

	private static string $option_name = 'private_captcha_settings';

	public static function get_option( string $key, mixed $default = null ): mixed {
		$settings = get_option( self::$option_name, array() );

		return $settings[ $key ] ?? $default;
	}

	public static function update_option( string $key, mixed $value ): bool {
		$settings         = get_option( self::$option_name, array() );
		$settings[ $key ] = $value;

		return update_option( self::$option_name, $settings );
	}

	/**
	 * @return array<string, mixed>
	 */
	public static function get_all_settings(): array {
		return get_option( self::$option_name, array() );
	}

	/**
	 * @param array<string, mixed> $new_settings
	 */
	public static function update_all_settings( array $new_settings ): bool {
		return update_option( self::$option_name, $new_settings );
	}

	public static function get_api_key(): string {
		// Get API key securely
		return (string) self::get_option( 'api_key', '' );
	}

	public static function get_sitekey(): string {
		return (string) self::get_option( 'sitekey', '' );
	}

	public static function get_theme(): string {
		return (string) self::get_option( 'theme', 'light' );
	}

	public static function is_debug_enabled(): bool {
		return (bool) self::get_option( 'debug_mode', false );
	}

	public static function is_eu_isolation_enabled(): bool {
		return (bool) self::get_option( 'eu_isolation', false );
	}

	public static function get_custom_domain(): string {
		return (string) self::get_option( 'custom_domain', '' );
	}

	public static function get_custom_styles(): string {
		return (string) self::get_option( 'custom_styles', '' );
	}

	public static function get_language(): string {
		return (string) self::get_option( 'language', 'en' );
	}

	public static function get_start_mode(): string {
		return (string) self::get_option( 'start_mode', 'auto' );
	}

	public static function is_login_enabled(): bool {
		return (bool) self::get_option( 'enable_login', false );
	}

	public static function is_registration_enabled(): bool {
		return (bool) self::get_option( 'enable_registration', false );
	}

	public static function is_reset_password_enabled(): bool {
		return (bool) self::get_option( 'enable_reset_password', false );
	}

	public static function is_comments_logged_in_enabled(): bool {
		return (bool) self::get_option( 'enable_comments_logged_in', false );
	}

	public static function is_comments_guest_enabled(): bool {
		return (bool) self::get_option( 'enable_comments_guest', false );
	}

	public static function is_configured(): bool {
		$api_key = self::get_api_key();
		$sitekey = self::get_sitekey();

		return ! empty( $api_key ) && ! empty( $sitekey );
	}

	/**
	 * Get default settings values
	 *
	 * @return array<string, mixed>
	 */
	public static function get_default_settings(): array {
		return array(
			'api_key'                   => '',
			'sitekey'                   => '',
			'theme'                     => 'light',
			'language'                  => 'en',
			'start_mode'                => 'auto',
			'debug_mode'                => false,
			'eu_isolation'              => false,
			'custom_domain'             => '',
			'custom_styles'             => '',
			'enable_login'              => false,
			'enable_registration'       => false,
			'enable_reset_password'     => false,
			'enable_comments_logged_in' => false,
			'enable_comments_guest'     => false,
		);
	}
}
