<?php
/**
 * Plugin Name: Private Captcha
 * Description: Private Captcha protects your WordPress website from spam and abuse with a privacy-first, independent CAPTCHA solution made in EU.
 * Version: 1.0.6
 * Author: Intmaker OÜ
 * Author URI: https://privatecaptcha.com
 * License: MIT
 * Requires at least: 5.6
 * Requires PHP: 8.2
 * Text Domain: private-captcha
 *
 * @package PrivateCaptchaWP
 */

declare(strict_types=1);

// Prevent direct access.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

// Define plugin constants.
define( 'PRIVATE_CAPTCHA_VERSION', '1.0.6' );
define( 'PRIVATE_CAPTCHA_PLUGIN_DIR', plugin_dir_path( __FILE__ ) );
define( 'PRIVATE_CAPTCHA_PLUGIN_URL', plugin_dir_url( __FILE__ ) );
define( 'PRIVATE_CAPTCHA_PLUGIN_FILE', __FILE__ );

// Load Composer autoloader.
require_once PRIVATE_CAPTCHA_PLUGIN_DIR . 'vendor/autoload.php';

/**
 * Main plugin class.
 */
class PrivateCaptchaWordPress {

	/**
	 * Singleton instance.
	 *
	 * @var PrivateCaptchaWordPress|null
	 */
	private static ?PrivateCaptchaWordPress $instance = null;

	/**
	 * The Private Captcha client instance.
	 *
	 * @var PrivateCaptchaWP\Client
	 */
	private PrivateCaptchaWP\Client $client;

	/**
	 * The Integrations instance.
	 *
	 * @var PrivateCaptchaWP\Integrations
	 */
	private PrivateCaptchaWP\Integrations $integrations;

	/**
	 * Get singleton instance.
	 *
	 * @return PrivateCaptchaWordPress
	 */
	public static function get_instance(): PrivateCaptchaWordPress {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}

		return self::$instance;
	}

	/**
	 * Private constructor to prevent direct instantiation.
	 */
	private function __construct() {
		add_action( 'init', array( $this, 'init' ) );
		register_activation_hook( __FILE__, array( $this, 'activate' ) );
		register_deactivation_hook( __FILE__, array( $this, 'deactivate' ) );
	}

	/**
	 * Initialize plugin functionality.
	 */
	public function init(): void {
		// Add plugin action links.
		add_filter( 'plugin_action_links_' . plugin_basename( PRIVATE_CAPTCHA_PLUGIN_FILE ), array( $this, 'plugin_action_links' ) );

		// Initialize the client.
		$this->init_client();

		// Initialize integrations manager.
		$this->integrations = new PrivateCaptchaWP\Integrations( $this->client );

		// Initialize admin interface.
		if ( is_admin() ) {
			new PrivateCaptchaWP\Admin( $this->client, $this->integrations );
		}

		// Initialize WP-CLI commands.
		if ( defined( 'WP_CLI' ) && WP_CLI ) {
			new PrivateCaptchaWP\CLI();
		}
	}

	/**
	 * Initialize the Private Captcha client.
	 */
	private function init_client(): void {
		$this->client = new PrivateCaptchaWP\Client();

		if ( PrivateCaptchaWP\Settings::is_configured() ) {
			$this->client->update(
				PrivateCaptchaWP\Settings::get_api_key(),
				PrivateCaptchaWP\Settings::get_custom_domain(),
				PrivateCaptchaWP\Settings::is_eu_isolation_enabled()
			);
		}
	}

	/**
	 * Plugin activation hook.
	 */
	public function activate(): void {
		// Set default options.
		if ( ! get_option( 'private_captcha_settings' ) ) {
			update_option( 'private_captcha_settings', PrivateCaptchaWP\Settings::get_default_settings() );
		}

		// Create database tables if needed (none for now).
		flush_rewrite_rules();
	}

	/**
	 * Plugin deactivation hook.
	 */
	public function deactivate(): void {
		flush_rewrite_rules();
	}

	/**
	 * Add custom action links to the plugin page.
	 *
	 * @param array<string> $links Array of plugin action links.
	 * @return array<string> Modified array of plugin action links.
	 */
	public function plugin_action_links( array $links ): array {
		$settings_link = '<a href="' . admin_url( 'options-general.php?page=private-captcha' ) . '">' . __( 'Settings', 'private-captcha' ) . '</a>';
		$docs_link     = '<a href="https://docs.privatecaptcha.com" target="_blank">' . __( 'Documentation', 'private-captcha' ) . '</a>';

		array_unshift( $links, $settings_link, $docs_link );

		return $links;
	}
}

/**
 * Initialize the plugin.
 *
 * @return PrivateCaptchaWordPress
 */
function private_captcha_init(): PrivateCaptchaWordPress {
	return PrivateCaptchaWordPress::get_instance();
}

// Start the plugin.
private_captcha_init();
