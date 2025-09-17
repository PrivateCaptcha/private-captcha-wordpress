<?php
/**
 * Auto-configure Private Captcha plugin for development
 */

// Only run in debug/development mode
if (defined('WP_DEBUG') && WP_DEBUG) {
	add_action('wp_loaded', function() {
		// Check if WordPress is properly installed
		if (!is_blog_installed()) {
			return;
		}
		
		// Only run once
		if (get_option('private_captcha_auto_configured')) {
			return;
		}
		
		// Auto-configure plugin settings from environment variables
		$settings = array(
			'api_key'             => $_ENV['PC_API_KEY'] ?? '',
			'sitekey'             => $_ENV['PC_SITEKEY'] ?? '',
			'theme'               => $_ENV['PC_THEME'] ?? 'light',
			'debug_mode'          => filter_var($_ENV['PC_DEBUG'] ?? 'true', FILTER_VALIDATE_BOOLEAN),
			'domain'              => $_ENV['PC_DOMAIN'] ?? 'global',
			'custom_styles'       => '',
			'enable_login'        => true,
			'enable_registration' => true,
		);
		
		update_option('private_captcha_settings', $settings);
		update_option('private_captcha_auto_configured', true);
		
		// Auto-activate the plugin if it exists and isn't active
		$plugin_file = 'private-captcha/private-captcha.php';
		if (file_exists(WP_PLUGIN_DIR . '/' . $plugin_file) && !is_plugin_active($plugin_file)) {
			activate_plugin($plugin_file);
		}
		
		// Log successful configuration
		error_log('Private Captcha plugin auto-configured for development');
	});
}
