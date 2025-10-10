<?php
/**
 * Settings management functionality for Private Captcha WordPress plugin.
 *
 * @package PrivateCaptchaWP
 */

declare(strict_types=1);

namespace PrivateCaptchaWP;

/**
 * Settings management class
 */
class Settings {

	/**
	 * The option name used to store settings in the WordPress database.
	 *
	 * @var string
	 */
	private static string $option_name = 'private_captcha_settings';

	/**
	 * Get a specific setting option value.
	 *
	 * @param string $key The setting key to retrieve.
	 * @param mixed  $default_value The default value if the key doesn't exist.
	 * @return mixed The setting value or default.
	 */
	public static function get_option( string $key, mixed $default_value = null ): mixed {
		$settings = get_option( self::$option_name, array() );

		return $settings[ $key ] ?? $default_value;
	}

	/**
	 * Update a specific setting option value.
	 *
	 * @param string $key The setting key to update.
	 * @param mixed  $value The new value to set.
	 * @return bool True on successful update, false on failure.
	 */
	public static function update_option( string $key, mixed $value ): bool {
		$settings         = get_option( self::$option_name, array() );
		$settings[ $key ] = $value;

		return update_option( self::$option_name, $settings );
	}

	/**
	 * Get all settings as an associative array.
	 *
	 * @return array<string, mixed> All settings values.
	 */
	public static function get_all_settings(): array {
		return get_option( self::$option_name, array() );
	}

	/**
	 * Update all settings with new values.
	 *
	 * @param array<string, mixed> $new_settings The new settings to save.
	 * @return bool True on successful update, false on failure.
	 */
	public static function update_all_settings( array $new_settings ): bool {
		return update_option( self::$option_name, $new_settings );
	}

	/**
	 * Get the Private Captcha API key.
	 *
	 * @return string The API key.
	 */
	public static function get_api_key(): string {
		// Get API key securely.
		return (string) self::get_option( 'api_key', '' );
	}

	/**
	 * Get the Private Captcha site key.
	 *
	 * @return string The site key.
	 */
	public static function get_sitekey(): string {
		return (string) self::get_option( 'sitekey', '' );
	}

	/**
	 * Get the widget theme setting.
	 *
	 * @return string The theme (light or dark).
	 */
	public static function get_theme(): string {
		return (string) self::get_option( 'theme', 'light' );
	}

	/**
	 * Check if debug mode is enabled.
	 *
	 * @return bool True if debug mode is enabled.
	 */
	public static function is_debug_enabled(): bool {
		return (bool) self::get_option( 'debug_mode', false );
	}

	/**
	 * Check if EU isolation is enabled.
	 *
	 * @return bool True if EU isolation is enabled.
	 */
	public static function is_eu_isolation_enabled(): bool {
		return (bool) self::get_option( 'eu_isolation', false );
	}

	/**
	 * Get the custom domain setting.
	 *
	 * @return string The custom domain.
	 */
	public static function get_custom_domain(): string {
		return (string) self::get_option( 'custom_domain', '' );
	}

	/**
	 * Get the custom styles setting.
	 *
	 * @return string The custom CSS styles.
	 */
	public static function get_custom_styles(): string {
		return (string) self::get_option( 'custom_styles', '' );
	}

	/**
	 * Get the widget language setting.
	 *
	 * @return string The language code.
	 */
	public static function get_language(): string {
		return (string) self::get_option( 'language', 'en' );
	}

	/**
	 * Get the widget start mode setting.
	 *
	 * @return string The start mode (auto or click).
	 */
	public static function get_start_mode(): string {
		return (string) self::get_option( 'start_mode', 'auto' );
	}

	/**
	 * Check if login form captcha is enabled.
	 *
	 * @return bool True if login captcha is enabled.
	 */
	public static function is_login_enabled(): bool {
		return (bool) self::get_option( 'enable_login', false );
	}

	/**
	 * Check if registration form captcha is enabled.
	 *
	 * @return bool True if registration captcha is enabled.
	 */
	public static function is_registration_enabled(): bool {
		return (bool) self::get_option( 'enable_registration', false );
	}

	/**
	 * Check if reset password form captcha is enabled.
	 *
	 * @return bool True if reset password captcha is enabled.
	 */
	public static function is_reset_password_enabled(): bool {
		return (bool) self::get_option( 'enable_reset_password', false );
	}

	/**
	 * Check if comments form captcha is enabled for logged-in users.
	 *
	 * @return bool True if comments captcha for logged-in users is enabled.
	 */
	public static function is_comments_logged_in_enabled(): bool {
		return (bool) self::get_option( 'enable_comments_logged_in', false );
	}

	/**
	 * Check if comments form captcha is enabled for guests.
	 *
	 * @return bool True if comments captcha for guests is enabled.
	 */
	public static function is_comments_guest_enabled(): bool {
		return (bool) self::get_option( 'enable_comments_guest', false );
	}

	/**
	 * Check if WPForms captcha is enabled.
	 *
	 * @return bool True if WPForms captcha is enabled.
	 */
	public static function is_wpforms_enabled(): bool {
		return (bool) self::get_option( 'enable_wpforms', false );
	}

	/**
	 * Check if the plugin is properly configured.
	 *
	 * @return bool True if API key and site key are both set.
	 */
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
			'enable_wpforms'            => false,
		);
	}
}
