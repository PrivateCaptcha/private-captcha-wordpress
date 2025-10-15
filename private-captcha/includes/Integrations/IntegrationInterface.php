<?php
/**
 * Integration interface for Private Captcha WordPress plugin.
 *
 * @package PrivateCaptchaWP
 */

declare(strict_types=1);

namespace PrivateCaptchaWP\Integrations;

/**
 * Interface for form integrations
 */
interface IntegrationInterface {

	/**
	 * Initialize the integration hooks and functionality.
	 */
	public function init(): void;

	/**
	 * Check if the integration is available (e.g., plugin is active).
	 *
	 * @return bool True if the integration is available.
	 */
	public function is_available(): bool;

	/**
	 * Check if the integration is enabled in settings.
	 *
	 * @return bool True if the integration is enabled.
	 */
	public function is_enabled(): bool;

	/**
	 * Get all settings fields for this integration.
	 *
	 * @return array<\PrivateCaptchaWP\SettingsField> Array of SettingsField instances.
	 */
	public function get_settings_fields(): array;

	/**
	 * Check if any of the integration's settings are enabled.
	 *
	 * @return bool True if any setting is enabled.
	 */
	public function has_enabled_settings(): bool;

	/**
	 * Get the plugin URL for this integration.
	 *
	 * @return string The plugin URL or empty string if not applicable.
	 */
	public function get_plugin_url(): string;

	/**
	 * Get the plugin name for this integration.
	 *
	 * @return string The plugin name or empty string if not applicable.
	 */
	public function get_plugin_name(): string;
}
