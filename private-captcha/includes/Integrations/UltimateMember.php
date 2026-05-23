<?php
/**
 * Ultimate Member integration for Private Captcha WordPress plugin.
 *
 * @package PrivateCaptchaWP
 */

declare(strict_types=1);

namespace PrivateCaptchaWP\Integrations;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

use PrivateCaptchaWP\Assets;
use PrivateCaptchaWP\SettingsField;
use PrivateCaptchaWP\Widget;

/**
 * Ultimate Member integration class
 */
class UltimateMember extends AbstractIntegration {

	/**
	 * Ultimate Member Login settings field.
	 *
	 * @var SettingsField
	 */
	private SettingsField $um_login_field;

	/**
	 * Ultimate Member Register settings field.
	 *
	 * @var SettingsField
	 */
	private SettingsField $um_register_field;

	/**
	 * Ultimate Member Password Reset settings field.
	 *
	 * @var SettingsField
	 */
	private SettingsField $um_password_field;

	/**
	 * Constructor to initialize the integration.
	 *
	 * @param \PrivateCaptchaWP\Client $client The Private Captcha client instance.
	 */
	public function __construct( \PrivateCaptchaWP\Client $client ) {
		parent::__construct( $client );

		$this->plugin_url  = 'https://wordpress.org/plugins/ultimate-member/';
		$this->plugin_name = 'Ultimate Member';

		$this->um_login_field = new SettingsField(
			'ultimatemember_login_enable',
			'Ultimate Member Login',
			'Protect Ultimate Member login form from bots.',
			'Add captcha to Ultimate Member login form'
		);

		$this->um_register_field = new SettingsField(
			'ultimatemember_register_enable',
			'Ultimate Member Register',
			'Protect Ultimate Member registration form from spam.',
			'Add captcha to Ultimate Member registration form'
		);

		$this->um_password_field = new SettingsField(
			'ultimatemember_password_enable',
			'Ultimate Member Password Reset',
			'Protect Ultimate Member password reset form from bots.',
			'Add captcha to Ultimate Member password reset form'
		);
	}

	/**
	 * Get all settings fields for this integration.
	 *
	 * @return array<\PrivateCaptchaWP\SettingsField> Array of SettingsField instances.
	 */
	public function get_settings_fields(): array {
		return array(
			$this->um_login_field,
			$this->um_register_field,
			$this->um_password_field,
		);
	}

	/**
	 * Check if Ultimate Member plugin is active.
	 *
	 * @return bool True if Ultimate Member is active.
	 */
	public function is_available(): bool {
		return class_exists( 'UM' ) || is_plugin_active( 'ultimate-member/ultimate-member.php' );
	}

	/**
	 * Initialize Ultimate Member integration hooks.
	 */
	public function init(): void {
		if ( ! $this->is_available() ) {
			return;
		}

		if ( $this->um_login_field->is_enabled() ) {
			add_action( 'um_after_login_fields', array( $this, 'show_widget' ), 500 );
		}

		if ( $this->um_register_field->is_enabled() ) {
			add_action( 'um_after_register_fields', array( $this, 'show_widget' ), 500 );
		}

		// For login and registration forms, we use the same hook but check form mode.
		if ( $this->um_login_field->is_enabled() || $this->um_register_field->is_enabled() ) {
			add_action( 'um_submit_form_errors_hook', array( $this, 'validate_captcha' ), 20, 2 );
		}

		if ( $this->um_password_field->is_enabled() ) {
			add_action( 'um_after_password_reset_fields', array( $this, 'show_widget' ), 500 );
			add_action( 'um_reset_password_errors_hook', array( $this, 'validate_captcha_password' ), 20, 2 );
		}

		add_action( 'wp_enqueue_scripts', array( $this, 'enqueue_scripts' ) );
	}

	/**
	 * Display the Private Captcha widget.
	 */
	public function show_widget(): void {
		Widget::render( '--border-radius: 0.25rem;' );
	}

	/**
	 * Validate Private Captcha on Ultimate Member login and register.
	 *
	 * @param array<mixed> $post Submitted data.
	 * @param array<mixed> $form_data Form data.
	 */
	public function validate_captcha( $post, $form_data ): void {
		if ( ! isset( $form_data['mode'] ) ) {
			return;
		}

		$mode = $form_data['mode'];

		if ( 'login' === $mode && ! $this->um_login_field->is_enabled() ) {
			return;
		}

		if ( 'register' === $mode && ! $this->um_register_field->is_enabled() ) {
			return;
		}

		if ( in_array( $mode, array( 'login', 'register' ), true ) ) {
			$this->do_validate();
		}
	}

	/**
	 * Validate Private Captcha on Ultimate Member password reset.
	 *
	 * @param array<mixed> $post Submitted data.
	 * @param array<mixed> $form_data Form data.
	 */
	public function validate_captcha_password( $post, $form_data ): void {
		if ( isset( $form_data['mode'] ) && 'password' === $form_data['mode'] ) {
			$this->do_validate();
		}
	}

	/**
	 * Execute captcha validation.
	 */
	private function do_validate(): void {
		if ( ! $this->client->is_available() ) {
			UM()->form()->add_error( 'privatecaptcha', __( 'Captcha service is currently unavailable.', 'private-captcha' ) );
			return;
		}

		// phpcs:ignore WordPress.Security.NonceVerification.Missing -- Verification handles its own security
		$solution = sanitize_text_field( wp_unslash( $_POST[ \PrivateCaptchaWP\Client::FORM_FIELD ] ?? '' ) );

		if ( empty( $solution ) ) {
			UM()->form()->add_error( 'privatecaptcha', __( 'Please complete Private Captcha.', 'private-captcha' ) );
			return;
		}

		if ( ! $this->verify_captcha() ) {
			UM()->form()->add_error( 'privatecaptcha', __( 'Private Captcha verification failed.', 'private-captcha' ) );
		}
	}

	/**
	 * Enqueue Private Captcha widget script with.
	 */
	public function enqueue_scripts(): void {
		Assets::enqueue();
	}
}
