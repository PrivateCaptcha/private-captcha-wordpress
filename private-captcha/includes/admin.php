<?php

declare(strict_types=1);

namespace PrivateCaptchaWP;

use PrivateCaptcha\Exceptions\PrivateCaptchaException;

/**
 * Admin interface class
 */
class Admin {

	public function __construct() {
		add_action( 'admin_menu', array( $this, 'add_admin_menu' ) );
		add_action( 'admin_init', array( $this, 'admin_init' ) );
		add_action( 'admin_notices', array( $this, 'show_configuration_notice' ) );
	}

	public function add_admin_menu(): void {
		add_options_page(
			__( 'Private Captcha Settings', 'private-captcha' ),
			__( 'Private Captcha', 'private-captcha' ),
			'manage_options',
			'private-captcha',
			array( $this, 'settings_page' )
		);
	}

	public function admin_init(): void {
		register_setting(
			'private_captcha_settings_group',
			'private_captcha_settings',
			array( $this, 'validate_settings' )
		);

		add_settings_section(
			'private_captcha_account',
			__( 'Account', 'private-captcha' ),
			array( $this, 'required_section_callback' ),
			'private-captcha'
		);

		add_settings_field(
			'api_key',
			__( 'API Key', 'private-captcha' ) . ' <span class="required">*</span>',
			array( $this, 'api_key_callback' ),
			'private-captcha',
			'private_captcha_account'
		);

		add_settings_field(
			'sitekey',
			__( 'Site Key', 'private-captcha' ) . ' <span class="required">*</span>',
			array( $this, 'sitekey_callback' ),
			'private-captcha',
			'private_captcha_account'
		);

		add_settings_section(
			'private_captcha_forms',
			__( 'Integrations', 'private-captcha' ),
			array( $this, 'forms_section_callback' ),
			'private-captcha'
		);

		add_settings_field(
			'enable_login',
			__( 'WordPress Login Form', 'private-captcha' ),
			array( $this, 'enable_login_callback' ),
			'private-captcha',
			'private_captcha_forms'
		);

		add_settings_field(
			'enable_registration',
			__( 'WordPress Registration Form', 'private-captcha' ),
			array( $this, 'enable_registration_callback' ),
			'private-captcha',
			'private_captcha_forms'
		);

		add_settings_field(
			'enable_reset_password',
			__( 'WordPress Reset Password Form', 'private-captcha' ),
			array( $this, 'enable_reset_password_callback' ),
			'private-captcha',
			'private_captcha_forms'
		);

		add_settings_field(
			'enable_comments_logged_in',
			__( 'WordPress Comments Form (Logged-in Users)', 'private-captcha' ),
			array( $this, 'enable_comments_logged_in_callback' ),
			'private-captcha',
			'private_captcha_forms'
		);

		add_settings_field(
			'enable_comments_guest',
			__( 'WordPress Comments Form (Guests)', 'private-captcha' ),
			array( $this, 'enable_comments_guest_callback' ),
			'private-captcha',
			'private_captcha_forms'
		);

		add_settings_section(
			'private_captcha_widget',
			__( 'Widget', 'private-captcha' ),
			array( $this, 'widget_section_callback' ),
			'private-captcha'
		);

		add_settings_field(
			'theme',
			__( 'Theme', 'private-captcha' ),
			array( $this, 'theme_callback' ),
			'private-captcha',
			'private_captcha_widget'
		);

		add_settings_field(
			'language',
			__( 'Language', 'private-captcha' ),
			array( $this, 'language_callback' ),
			'private-captcha',
			'private_captcha_widget'
		);

		add_settings_field(
			'start_mode',
			__( 'Start Mode', 'private-captcha' ),
			array( $this, 'start_mode_callback' ),
			'private-captcha',
			'private_captcha_widget'
		);

		add_settings_section(
			'private_captcha_advanced',
			__( 'Advanced', 'private-captcha' ),
			array( $this, 'advanced_section_callback' ),
			'private-captcha'
		);

		add_settings_field(
			'eu_isolation',
			__( 'EU Isolation', 'private-captcha' ),
			array( $this, 'eu_isolation_callback' ),
			'private-captcha',
			'private_captcha_advanced'
		);

		add_settings_field(
			'custom_domain',
			__( 'Custom Domain', 'private-captcha' ),
			array( $this, 'custom_domain_callback' ),
			'private-captcha',
			'private_captcha_advanced'
		);

		add_settings_field(
			'debug_mode',
			__( 'Debug Mode', 'private-captcha' ),
			array( $this, 'debug_mode_callback' ),
			'private-captcha',
			'private_captcha_advanced'
		);

		add_settings_field(
			'custom_styles',
			__( 'Custom Styles', 'private-captcha' ),
			array( $this, 'custom_styles_callback' ),
			'private-captcha',
			'private_captcha_advanced'
		);

		add_settings_field(
			'reset_settings',
			__( 'Reset Settings', 'private-captcha' ),
			array( $this, 'reset_settings_callback' ),
			'private-captcha',
			'private_captcha_advanced'
		);
	}

	public function settings_page(): void {
		?>
		<div class="wrap">
			<h1><?php echo esc_html( get_admin_page_title() ); ?></h1>
			<p>
				<?php
				printf(
					wp_kses(
						// translators: %1$s is the documentation URL, %2$s is the support URL
						__(
							'Need help? Check out our <a href="%1$s" target="_blank">documentation</a> or <a href="%2$s" target="_blank">contact support</a>.',
							'private-captcha'
						),
						array(
							'a' => array(
								'href'   => array(),
								'target' => array(),
							),
						)
					),
					'https://docs.privatecaptcha.com',
					'https://portal.privatecaptcha.com/support'
				);
				?>
			</p>
			<form method="post" action="options.php">
				<?php settings_fields( 'private_captcha_settings_group' ); ?>
				
				<?php $this->render_settings_sections( array( 'private_captcha_account', 'private_captcha_forms' ) ); ?>
				<?php submit_button(); ?>
				
				<hr style="margin: 2rem 0; border: none; border-top: 1px solid #ddd;">
				
				<?php $this->render_settings_sections( array( 'private_captcha_widget', 'private_captcha_advanced' ) ); ?>
				<?php submit_button(); ?>
			</form>
		</div>
		<?php
	}

	public function required_section_callback(): void {
		echo '<p>' . sprintf(
			// translators: %s is a link to create an account
			esc_html__( "Don't have an account yet? %s to get started.", 'private-captcha' ),
			'<a href="https://portal.privatecaptcha.com/signup" target="_blank">' . esc_html__( 'Create one here', 'private-captcha' ) . '</a>'
		) .
		'</p>';
	}

	public function widget_section_callback(): void {
		echo '<p>' . esc_html__( 'Customize the appearance and behavior of the captcha widget.', 'private-captcha' ) . '</p>';
	}

	public function forms_section_callback(): void {
		echo '<p>' . esc_html__( 'Choose which forms should include Private Captcha protection.', 'private-captcha' ) . '</p>';
	}

	public function advanced_section_callback(): void {
		echo '<p>' . esc_html__( 'Advanced configuration options. Not recommended to change by default.', 'private-captcha' ) . '</p>';
		echo '<style>
			#private_captcha_advanced { margin-top: 20px; border-top: 1px solid #ddd; padding-top: 20px; }
			.required { color: #d63638; }
		</style>';
	}

	public function api_key_callback(): void {
		$value = Settings::get_api_key();
		echo '<input type="password" id="api_key" name="private_captcha_settings[api_key]" value="' . esc_attr( $value ) . '" size="50" required />';
		echo '<p class="description">' .
			sprintf(
				// translators: %s is a link to account settings
				esc_html__( 'Your Private Captcha API key, created in the %s.', 'private-captcha' ),
				'<a href="https://portal.privatecaptcha.com/settings?tab=apikeys" target="_blank">' . esc_html__( 'account settings', 'private-captcha' ) . '</a>'
			) .
		'</p>';
	}

	public function sitekey_callback(): void {
		$value = Settings::get_sitekey();
		echo '<input type="text" id="sitekey" name="private_captcha_settings[sitekey]" value="' . esc_attr( $value ) . '" size="32" placeholder="aaaaaaaabbbbccccddddeeeeeeeeeeee" required />';
		echo '<p class="description">' . esc_html__( 'Your Private Captcha property site key.', 'private-captcha' ) . '</p>';
	}

	public function eu_isolation_callback(): void {
		$value = Settings::is_eu_isolation_enabled();
		echo '<input type="checkbox" id="eu_isolation" name="private_captcha_settings[eu_isolation]" value="1"' . checked( $value, true, false ) . ' />';
		echo '<label for="eu_isolation">' . esc_html__( 'Use EU-only endpoints 🇪🇺', 'private-captcha' ) . '</label>';
		echo '<p class="description">' .
			sprintf(
				// translators: %s is a link to EU-only endpoints documentation
				esc_html__( 'Enable to use %s for GDPR compliance. Ignored if custom domain is set.', 'private-captcha' ),
				'<a href="https://docs.privatecaptcha.com/docs/reference/eu-isolation/" target="_blank">' . esc_html__( 'EU-only endpoints', 'private-captcha' ) . '</a>'
			) .
		'</p>';
	}

	public function custom_domain_callback(): void {
		$value = Settings::get_custom_domain();
		echo '<input type="text" id="custom_domain" name="private_captcha_settings[custom_domain]" value="' . esc_attr( $value ) . '" size="50" placeholder="privatecaptcha.com" />';
		echo '<p class="description">' . esc_html__( 'Custom root domain for Private Captcha API endpoints. Leave empty to use privatecaptcha.com.', 'private-captcha' ) . '</p>';
	}

	public function theme_callback(): void {
		$value   = Settings::get_theme();
		$options = array(
			'light' => esc_html__( 'Light', 'private-captcha' ),
			'dark'  => esc_html__( 'Dark', 'private-captcha' ),
		);

		echo '<select id="theme" name="private_captcha_settings[theme]">';
		foreach ( $options as $key => $label ) {
			echo '<option value="' . esc_attr( $key ) . '"' . selected( $value, $key, false ) . '>' . esc_html( $label ) . '</option>';
		}
		echo '</select>';
	}


	public function language_callback(): void {
		$value   = Settings::get_language();
		$options = array(
			'en' => esc_html__( 'English', 'private-captcha' ),
			'de' => esc_html__( 'Deutsch', 'private-captcha' ),
			'es' => esc_html__( 'Español', 'private-captcha' ),
			'fr' => esc_html__( 'Français', 'private-captcha' ),
			'it' => esc_html__( 'Italiano', 'private-captcha' ),
			'nl' => esc_html__( 'Nederlands', 'private-captcha' ),
			'sv' => esc_html__( 'Svenska', 'private-captcha' ),
			'no' => esc_html__( 'Norsk', 'private-captcha' ),
			'pl' => esc_html__( 'Polski', 'private-captcha' ),
			'fi' => esc_html__( 'Suomi', 'private-captcha' ),
			'et' => esc_html__( 'Eesti', 'private-captcha' ),
		);

		echo '<select id="language" name="private_captcha_settings[language]">';
		foreach ( $options as $key => $label ) {
			echo '<option value="' . esc_attr( $key ) . '"' . selected( $value, $key, false ) . '>' . esc_html( $label ) . '</option>';
		}
		echo '</select>';
		echo '<p class="description">' . esc_html__( 'Language for the captcha widget interface.', 'private-captcha' ) . '</p>';
	}

	public function start_mode_callback(): void {
		$value   = Settings::get_start_mode();
		$options = array(
			'auto'  => esc_html__( 'Auto', 'private-captcha' ),
			'click' => esc_html__( 'On click', 'private-captcha' ),
		);

		echo '<select id="start_mode" name="private_captcha_settings[start_mode]">';
		foreach ( $options as $key => $label ) {
			echo '<option value="' . esc_attr( $key ) . '"' . selected( $value, $key, false ) . '>' . esc_html( $label ) . '</option>';
		}
		echo '</select>';
		echo '<p class="description">' . esc_html__( 'When to start the captcha challenge. "Auto" starts when user starts filling in the form.', 'private-captcha' ) . '</p>';
	}

	public function debug_mode_callback(): void {
		$value = Settings::is_debug_enabled();
		echo '<input type="checkbox" id="debug_mode" name="private_captcha_settings[debug_mode]" value="1"' . checked( $value, true, false ) . ' />';
		echo '<label for="debug_mode">' . esc_html__( 'Enable debug mode', 'private-captcha' ) . '</label>';
		echo '<p class="description">' . esc_html__( 'Captcha widget prints verbose logs to browser console to help with debugging.', 'private-captcha' ) . '</p>';
	}

	public function custom_styles_callback(): void {
		$value = Settings::get_custom_styles();
		echo '<textarea id="custom_styles" name="private_captcha_settings[custom_styles]" style="font-family: monospace;" cols="60" rows="3" placeholder="--border-radius: 0.25rem;">' . esc_textarea( $value ) . '</textarea>';
		echo '<p class="description">' .
			sprintf(
				// translators: %s is a link to custom CSS styles documentation
				esc_html__( '%s for the captcha widget. Leave empty to use default styles.', 'private-captcha' ),
				'<a href="https://docs.privatecaptcha.com/docs/reference/widget-options/#data-styles" target="_blank">' . esc_html__( 'Custom CSS styles', 'private-captcha' ) . '</a>'
			) .
		'</p>';
	}

	public function enable_login_callback(): void {
		$value = Settings::is_login_enabled();
		echo '<input type="checkbox" id="enable_login" name="private_captcha_settings[enable_login]" value="1"' . checked( $value, true, false ) . ' />';
		echo '<label for="enable_login">' . esc_html__( 'Add captcha to login form', 'private-captcha' ) . '</label>';
		echo '<p class="description">' . esc_html__( 'Login can be locked out if Site Key, API key or Custom Domain become invalid. WP-CLI commands available for recovery.', 'private-captcha' ) . '</p>';
	}

	public function enable_registration_callback(): void {
		$value = Settings::is_registration_enabled();
		echo '<input type="checkbox" id="enable_registration" name="private_captcha_settings[enable_registration]" value="1"' . checked( $value, true, false ) . ' />';
		echo '<label for="enable_registration">' . esc_html__( 'Add captcha to registration form', 'private-captcha' ) . '</label>';
	}

	public function enable_reset_password_callback(): void {
		$value = Settings::is_reset_password_enabled();
		echo '<input type="checkbox" id="enable_reset_password" name="private_captcha_settings[enable_reset_password]" value="1"' . checked( $value, true, false ) . ' />';
		echo '<label for="enable_reset_password">' . esc_html__( 'Add captcha to reset password form', 'private-captcha' ) . '</label>';
	}

	public function enable_comments_logged_in_callback(): void {
		$value = Settings::is_comments_logged_in_enabled();
		echo '<input type="checkbox" id="enable_comments_logged_in" name="private_captcha_settings[enable_comments_logged_in]" value="1"' . checked( $value, true, false ) . ' />';
		echo '<label for="enable_comments_logged_in">' . esc_html__( 'Add captcha to comments form for logged-in users', 'private-captcha' ) . '</label>';
		echo '<p class="description">' . esc_html__( 'Protect comment forms from spam for users who are logged into WordPress.', 'private-captcha' ) . '</p>';
	}

	public function enable_comments_guest_callback(): void {
		$value = Settings::is_comments_guest_enabled();
		echo '<input type="checkbox" id="enable_comments_guest" name="private_captcha_settings[enable_comments_guest]" value="1"' . checked( $value, true, false ) . ' />';
		echo '<label for="enable_comments_guest">' . esc_html__( 'Add captcha to comments form for guests', 'private-captcha' ) . '</label>';
		echo '<p class="description">' . wp_kses( __( 'Protect comment forms from spam for visitors who are <strong>not</strong> logged into WordPress.', 'private-captcha' ), array( 'strong' => array() ) ) . '</p>';
	}

	public function reset_settings_callback(): void {
		echo '<input type="submit" name="private_captcha_reset" value="' . esc_attr__( 'Reset All Settings', 'private-captcha' ) . '" class="button button-secondary private-captcha-reset-button" formnovalidate onclick="return confirm(\'' . esc_js( __( 'Are you sure you want to reset all Private Captcha settings? This action cannot be undone.', 'private-captcha' ) ) . '\');" />';
		echo '<p class="description" style="color: #d63638;">' . wp_kses( __( '<strong>Warning:</strong> This will reset your Private Captcha configuration to default values.', 'private-captcha' ), array( 'strong' => array() ) ) . '</p>';
		echo '<style>
			.private-captcha-reset-button {
				background-color: #d63638 !important;
				border-color: #d63638 !important;
				color: #fff !important;
			}
			.private-captcha-reset-button:hover,
			.private-captcha-reset-button:focus {
				background-color: #b32d2e !important;
				border-color: #b32d2e !important;
				color: #fff !important;
			}
		</style>';
	}

	/**
	 * @param array<string, mixed> $input
	 * @return array<string, mixed>
	 */
	public function validate_settings( array $input ): array {
		if ( isset( $_POST['private_captcha_reset'] ) ) {
			// Verify nonce for reset action
			if ( ! isset( $_POST['_wpnonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['_wpnonce'] ) ), 'private_captcha_settings_group-options' ) ) {
				wp_die( esc_html__( 'Security check failed.', 'private-captcha' ) );
			}
			
			delete_option( 'private_captcha_settings' );

			return Settings::get_default_settings();
		}

		$sanitized = array();

		$sanitized['api_key'] = sanitize_text_field( $input['api_key'] );
		$sanitized['sitekey'] = sanitize_text_field( $input['sitekey'] );

		$custom_domain = sanitize_text_field( $input['custom_domain'] );

		if ( str_starts_with( $custom_domain, 'https://' ) ) {
			$custom_domain = substr( $custom_domain, 8 );
		} elseif ( str_starts_with( $custom_domain, 'http://' ) ) {
			$custom_domain = substr( $custom_domain, 7 );
		}

		// Remove well-known Private Captcha prefixes (assumed possible user error?)
		if ( str_starts_with( $custom_domain, 'api.' ) ) {
			$custom_domain = substr( $custom_domain, 4 );
		} elseif ( str_starts_with( $custom_domain, 'cdn.' ) ) {
			$custom_domain = substr( $custom_domain, 4 );
		} elseif ( str_starts_with( $custom_domain, 'portal.' ) ) {
			$custom_domain = substr( $custom_domain, 7 );
		}

		$custom_domain              = rtrim( ltrim( $custom_domain ), '/' );
		$sanitized['custom_domain'] = $custom_domain;

		$sanitized['eu_isolation'] = isset( $input['eu_isolation'] ) && $input['eu_isolation'] === '1';

		$valid_themes       = array( 'light', 'dark' );
		$sanitized['theme'] = in_array( $input['theme'], $valid_themes, true ) ? $input['theme'] : 'light';

		$valid_languages       = array( 'en', 'de', 'es', 'fr', 'it', 'nl', 'sv', 'no', 'pl', 'fi', 'et' );
		$sanitized['language'] = in_array( $input['language'], $valid_languages, true ) ? $input['language'] : 'en';

		$valid_start_modes       = array( 'auto', 'click' );
		$sanitized['start_mode'] = in_array( $input['start_mode'], $valid_start_modes, true ) ? $input['start_mode'] : 'auto';

		$custom_styles = sanitize_textarea_field( $input['custom_styles'] );
		if ( ! empty( $custom_styles ) ) {
			$custom_styles = str_replace( array( "\r", "\n", "\t" ), ' ', $custom_styles );
			// Collapse multiple consecutive spaces into single spaces
			while ( strpos( $custom_styles, '  ' ) !== false ) {
				$custom_styles = str_replace( '  ', ' ', $custom_styles );
			}
			$custom_styles = trim( $custom_styles );
		}
		$sanitized['custom_styles'] = $custom_styles;

		$sanitized['debug_mode']                = isset( $input['debug_mode'] ) && $input['debug_mode'] === '1';
		$sanitized['enable_login']              = isset( $input['enable_login'] ) && $input['enable_login'] === '1';
		$sanitized['enable_registration']       = isset( $input['enable_registration'] ) && $input['enable_registration'] === '1';
		$sanitized['enable_reset_password']     = isset( $input['enable_reset_password'] ) && $input['enable_reset_password'] === '1';
		$sanitized['enable_comments_logged_in'] = isset( $input['enable_comments_logged_in'] ) && $input['enable_comments_logged_in'] === '1';
		$sanitized['enable_comments_guest']     = isset( $input['enable_comments_guest'] ) && $input['enable_comments_guest'] === '1';

		if ( empty( $sanitized['api_key'] ) ) {
			add_settings_error(
				'private_captcha_settings',
				'api_key_required',
				__( 'API Key is required.', 'private-captcha' ),
				'error'
			);
		}

		if ( empty( $sanitized['sitekey'] ) ) {
			add_settings_error(
				'private_captcha_settings',
				'sitekey_required',
				__( 'Site Key is required.', 'private-captcha' ),
				'error'
			);
		}

		// Warning for stub/test sitekey
		if ( $sanitized['sitekey'] === 'aaaaaaaabbbbccccddddeeeeeeeeeeee' ) {
			add_settings_error(
				'private_captcha_settings',
				'stub_sitekey_warning',
				sprintf(
					// translators: %s is a link to the Private Captcha portal
					__( 'Demo site key is active. For live sites, please use a real site key from %s.', 'private-captcha' ),
					'<a href="https://portal.privatecaptcha.com" target="_blank">Private Captcha portal</a>'
				),
				'warning'
			);
		}

		$any_form_integration = $sanitized['enable_login'] ||
			$sanitized['enable_registration'] ||
			$sanitized['enable_reset_password'] ||
			$sanitized['enable_comments_logged_in'] ||
			$sanitized['enable_comments_guest'];
		$settings_valid       = false;

		// Test settings if form integrations are enabled
		if ( $any_form_integration && ! empty( $sanitized['api_key'] ) && ! empty( $sanitized['sitekey'] ) ) {
			try {
				$client         = new Client( $sanitized['api_key'], $sanitized['custom_domain'], $sanitized['eu_isolation'] );
				$settings_valid = $client->test_current_settings( $sanitized['sitekey'] );
			} catch ( PrivateCaptchaException $e ) {
				wp_debug_log( 'Private Captcha settings test error: ' . $e->getMessage() );
			}

			if ( ! $settings_valid ) {
				add_settings_error(
					'private_captcha_settings',
					'settings_test_failed',
					__( 'Private Captcha settings test failed. Please verify your API Key, Site Key, and domain settings. Form integrations have been disabled to prevent lockout.', 'private-captcha' ),
					'error'
				);
			}
		}

		// Disable form integrations to prevent lockout
		if ( ! $settings_valid ) {
			$sanitized['enable_login']              = false;
			$sanitized['enable_registration']       = false;
			$sanitized['enable_reset_password']     = false;
			$sanitized['enable_comments_logged_in'] = false;
			$sanitized['enable_comments_guest']     = false;
		}

		return $sanitized;
	}

	/**
	 * Show admin notice when plugin is not configured
	 */
	public function show_configuration_notice(): void {
		$screen = get_current_screen();
		if ( $screen && $screen->base === 'settings_page_private-captcha' ) {
			return;
		}

		if ( Settings::is_configured() ) {
			return;
		}

		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}

		$settings_url = admin_url( 'options-general.php?page=private-captcha' );

		echo '<div class="notice notice-warning is-dismissible">';
		echo '<p>';
		printf(
			wp_kses(
				// translators: %s is the URL to the settings page
				__( '<strong>Private Captcha</strong> is installed but <strong>not configured</strong>. Please <a href="%s">configure your API Key and Site Key</a> to start protecting your forms.', 'private-captcha' ),
				array(
					'strong' => array(),
					'a'      => array(
						'href' => array(),
					),
				)
			),
			esc_url( $settings_url )
		);
		echo '</p>';
		echo '</div>';
	}

	/**
	 * Render a specific settings section
	 *
	 * @param string $section_id The section ID to render.
	 */
	private function render_settings_section( string $section_id ): void {
		global $wp_settings_sections, $wp_settings_fields;

		if ( ! isset( $wp_settings_sections['private-captcha'][ $section_id ] ) ) {
			return;
		}

		$section = $wp_settings_sections['private-captcha'][ $section_id ];

		if ( $section['title'] ) {
			echo '<h2>' . esc_html( $section['title'] ) . "</h2>\n";
		}

		if ( $section['callback'] ) {
			call_user_func( $section['callback'], $section );
		}

		if ( isset( $wp_settings_fields['private-captcha'][ $section_id ] ) ) {
			echo '<table class="form-table" role="presentation">';
			do_settings_fields( 'private-captcha', $section_id );
			echo '</table>';
		}
	}

	/**
	 * Render multiple settings sections
	 *
	 * @param array<string> $section_ids Array of section IDs to render.
	 */
	private function render_settings_sections( array $section_ids ): void {
		foreach ( $section_ids as $section_id ) {
			$this->render_settings_section( $section_id );
		}
	}
}
