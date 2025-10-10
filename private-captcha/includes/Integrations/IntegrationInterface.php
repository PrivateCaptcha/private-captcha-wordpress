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
}
