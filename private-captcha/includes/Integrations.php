<?php
/**
 * Integration manager for Private Captcha WordPress plugin.
 *
 * @package PrivateCaptchaWP
 */

declare(strict_types=1);

namespace PrivateCaptchaWP;

use PrivateCaptchaWP\Integrations\IntegrationInterface;
use PrivateCaptchaWP\Integrations\WordPressCore;
use PrivateCaptchaWP\Integrations\WPForms;

/**
 * Integration manager class
 */
class Integrations {
	/**
	 * Array of integration instances.
	 *
	 * @var IntegrationInterface[]
	 */
	private array $integrations = array();

	/**
	 * Constructor to initialize integrations manager.
	 *
	 * @param Client $client The Private Captcha client instance.
	 */
	public function __construct( Client $client ) {
		$this->integrations = array(
			new WordPressCore( $client ),
			new WPForms( $client ),
		);

		$this->init();
	}

	/**
	 * Initialize all available and enabled integrations.
	 */
	public function init(): void {
		foreach ( $this->integrations as $integration ) {
			if ( $integration->is_available() && $integration->is_enabled() ) {
				$integration->init();
			}
		}
	}

	/**
	 * Get all integrations.
	 *
	 * @return IntegrationInterface[] Array of integration instances.
	 */
	public function get_all_integrations(): array {
		return $this->integrations;
	}
}
