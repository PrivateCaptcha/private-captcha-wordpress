<?php

/**
 * Plugin Name: Private Captcha for WordPress
 * Plugin URI: https://privatecaptcha.com
 * Description: Integrates Private Captcha with WordPress forms for enhanced security.
 * Version: 1.0.0
 * Author: Private Captcha
 * Author URI: https://privatecaptcha.com
 * License: MIT
 * Requires at least: 5.6
 * Requires PHP: 8.2
 * Text Domain: private-captcha
 */

declare(strict_types=1);

// Prevent direct access
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

// Define plugin constants
define( 'PRIVATE_CAPTCHA_VERSION', '1.0.0' );
define( 'PRIVATE_CAPTCHA_PLUGIN_DIR', plugin_dir_path( __FILE__ ) );
define( 'PRIVATE_CAPTCHA_PLUGIN_URL', plugin_dir_url( __FILE__ ) );
define( 'PRIVATE_CAPTCHA_PLUGIN_FILE', __FILE__ );

// Load Composer autoloader
require_once PRIVATE_CAPTCHA_PLUGIN_DIR . 'vendor/autoload.php';

/**
 * Main plugin class
 */
class PrivateCaptchaWordPress {

	private static ?PrivateCaptchaWordPress $instance = null;

	public static function get_instance(): PrivateCaptchaWordPress {
		if ( self::$instance === null ) {
			self::$instance = new self();
		}

		return self::$instance;
	}

	private function __construct() {
		add_action( 'init', array( $this, 'init' ) );
		register_activation_hook( __FILE__, array( $this, 'activate' ) );
		register_deactivation_hook( __FILE__, array( $this, 'deactivate' ) );
	}

	public function init(): void {
		// Add plugin action links
		add_filter( 'plugin_action_links_' . plugin_basename( PRIVATE_CAPTCHA_PLUGIN_FILE ), array( $this, 'plugin_action_links' ) );

		// Initialize admin interface
		if ( is_admin() ) {
			new PrivateCaptchaWP\Admin();
		}

		// Initialize frontend functionality
		new PrivateCaptchaWP\Frontend();

		// Initialize WP-CLI commands
		if ( defined( 'WP_CLI' ) && WP_CLI ) {
			new PrivateCaptchaWP\CLI();
		}
	}

	public function activate(): void {
		// Set default options
		if ( ! get_option( 'private_captcha_settings' ) ) {
			update_option( 'private_captcha_settings', PrivateCaptchaWP\Settings::get_default_settings() );
		}

		// Create database tables if needed (none for now)
		flush_rewrite_rules();
	}

	public function deactivate(): void {
		flush_rewrite_rules();
	}

	/**
	 * Add custom action links to the plugin page
	 *
	 * @param array<string> $links
	 * @return array<string>
	 */
	public function plugin_action_links( array $links ): array {
		$settings_link = '<a href="' . admin_url( 'options-general.php?page=private-captcha' ) . '">' . __( 'Settings', 'private-captcha' ) . '</a>';
		$docs_link     = '<a href="https://docs.privatecaptcha.com" target="_blank">' . __( 'Documentation', 'private-captcha' ) . '</a>';

		array_unshift( $links, $settings_link, $docs_link );

		return $links;
	}
}

/**
 * Initialize the plugin
 */
function private_captcha_init(): PrivateCaptchaWordPress {
	return PrivateCaptchaWordPress::get_instance();
}

// Start the plugin
private_captcha_init();
