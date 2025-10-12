<?php
/**
 * WordPress Core forms integration for Private Captcha WordPress plugin.
 *
 * @package PrivateCaptchaWP
 */

declare(strict_types=1);

namespace PrivateCaptchaWP\Integrations;

use PrivateCaptchaWP\Assets;
use PrivateCaptchaWP\Settings;
use PrivateCaptchaWP\SettingsField;
use PrivateCaptchaWP\Widget;
use WP_Error;
use WP_User;

/**
 * WordPress Core forms integration class
 */
class WordPressCore extends AbstractIntegration {

	/**
	 * Login form settings field.
	 *
	 * @var SettingsField
	 */
	private SettingsField $login_field;

	/**
	 * Registration form settings field.
	 *
	 * @var SettingsField
	 */
	private SettingsField $registration_field;

	/**
	 * Reset password form settings field.
	 *
	 * @var SettingsField
	 */
	private SettingsField $reset_password_field;

	/**
	 * Comments form (logged-in users) settings field.
	 *
	 * @var SettingsField
	 */
	private SettingsField $comments_logged_in_field;

	/**
	 * Comments form (guests) settings field.
	 *
	 * @var SettingsField
	 */
	private SettingsField $comments_guest_field;

	/**
	 * Constructor to initialize the integration.
	 *
	 * @param \PrivateCaptchaWP\Client $client The Private Captcha client instance.
	 */
	public function __construct( \PrivateCaptchaWP\Client $client ) {
		parent::__construct( $client );

		$this->login_field = new SettingsField(
			'wordpress_core_enable_login',
			'WordPress Login Form',
			'Login can be locked out if Site Key, API key or Custom Domain become invalid. WP-CLI commands available for recovery.',
			'Add captcha to login form'
		);

		$this->registration_field = new SettingsField(
			'wordpress_core_enable_registration',
			'WordPress Registration Form',
			'Add captcha to registration form',
			'Add captcha to registration form'
		);

		$this->reset_password_field = new SettingsField(
			'wordpress_core_enable_reset_password',
			'WordPress Reset Password Form',
			'Add captcha to reset password form',
			'Add captcha to reset password form'
		);

		$this->comments_logged_in_field = new SettingsField(
			'wordpress_core_enable_comments_logged_in',
			'WordPress Comments Form (Logged-in Users)',
			'Protect comment forms from spam for users who are logged into WordPress.',
			'Add captcha to comments form for logged-in users'
		);

		$this->comments_guest_field = new SettingsField(
			'wordpress_core_enable_comments_guest',
			'WordPress Comments Form (Guests)',
			'Protect comment forms from spam for visitors who are <strong>not</strong> logged into WordPress.',
			'Add captcha to comments form for guests'
		);
	}

	/**
	 * Get all settings fields for this integration.
	 *
	 * @return array<\PrivateCaptchaWP\SettingsField> Array of SettingsField instances.
	 */
	public function get_settings_fields(): array {
		return array(
			$this->login_field,
			$this->registration_field,
			$this->reset_password_field,
			$this->comments_logged_in_field,
			$this->comments_guest_field,
		);
	}

	/**
	 * Check if WordPress Core integration is available.
	 *
	 * @return bool Always true since WordPress core is always available.
	 */
	public function is_available(): bool {
		return true;
	}

	/**
	 * Check if WordPress Core integration is enabled.
	 *
	 * @return bool True if any WordPress core form integration is enabled.
	 */
	public function is_enabled(): bool {
		return $this->login_field->is_enabled() ||
			$this->registration_field->is_enabled() ||
			$this->reset_password_field->is_enabled() ||
			$this->comments_logged_in_field->is_enabled() ||
			$this->comments_guest_field->is_enabled();
	}

	/**
	 * Initialize WordPress Core integration hooks.
	 */
	public function init(): void {
		add_action( 'login_enqueue_scripts', array( $this, 'enqueue_scripts' ) );

		if ( $this->login_field->is_enabled() ) {
			add_action( 'login_form', array( $this, 'add_login_captcha' ) );
			add_filter( 'authenticate', array( $this, 'verify_login_captcha' ), 30, 3 );
		}

		if ( $this->registration_field->is_enabled() ) {
			add_action( 'register_form', array( $this, 'add_register_captcha' ) );
			add_filter( 'registration_errors', array( $this, 'verify_register_captcha' ), 10, 3 );
		}

		if ( $this->reset_password_field->is_enabled() ) {
			add_action( 'lostpassword_form', array( $this, 'add_reset_password_captcha' ) );
			add_action( 'lostpassword_post', array( $this, 'verify_reset_password_captcha' ), 10, 1 );
		}

		if ( $this->comments_logged_in_field->is_enabled() || $this->comments_guest_field->is_enabled() ) {
			add_action( 'wp_enqueue_scripts', array( $this, 'enqueue_scripts' ) );
			add_filter( 'preprocess_comment', array( $this, 'verify_comment_captcha' ) );
		}

		if ( $this->comments_logged_in_field->is_enabled() || $this->comments_guest_field->is_enabled() ) {
			add_filter( 'comment_form_submit_field', array( $this, 'modify_comment_submit_field' ), 10, 2 );
		}
	}

	/**
	 * Add captcha widget to login form.
	 */
	public function add_login_captcha(): void {
		Widget::render( 'display: block; min-width: 0; height: 100%; --border-radius: 0.25rem;' );
	}

	/**
	 * Add captcha widget to registration form.
	 */
	public function add_register_captcha(): void {
		Widget::render( 'display: block; min-width: 0; height: 100%; --border-radius: 0.25rem;' );
	}

	/**
	 * Add captcha widget to reset password form.
	 */
	public function add_reset_password_captcha(): void {
		Widget::render( 'display: block; min-width: 0; height: 100%; --border-radius: 0.25rem;' );
	}

	/**
	 * Modify comment form submit field to include captcha widget
	 *
	 * @param string               $submit_field HTML markup for the submit field.
	 * @param array<string, mixed> $args Comment form arguments (unused).
	 * @return string Modified submit field HTML.
	 */
	public function modify_comment_submit_field( string $submit_field, array $args ): string {
		unset( $args );
		$user_is_logged_in = is_user_logged_in();
		$should_show       = false;

		if ( $user_is_logged_in && $this->comments_logged_in_field->is_enabled() ) {
			$should_show = true;
		} elseif ( ! $user_is_logged_in && $this->comments_guest_field->is_enabled() ) {
			$should_show = true;
		}

		if ( ! $should_show ) {
			return $submit_field;
		}

		// Capture the captcha widget HTML.
		ob_start();
		Widget::render( '--border-radius: 0.25rem;' );
		$captcha_html = ob_get_clean();

		return $captcha_html . $submit_field;
	}

	/**
	 * Verify captcha for login form.
	 *
	 * @param WP_User|WP_Error|null $user The authenticated user or WP_Error.
	 * @param string                $username The username.
	 * @param string                $password The password.
	 * @return WP_User|WP_Error|null The user object or error.
	 */
	public function verify_login_captcha( WP_User|WP_Error|null $user, string $username, string $password ): WP_User|WP_Error|null {
		if ( empty( $username ) || empty( $password ) ) {
			$this->write_log( 'Skipping login captcha verification as username or password are empty.' );
			return $user;
		}

		if ( is_wp_error( $user ) ) {
			$this->write_log( 'Skipping login captcha verification due to user error.' );
			return $user;
		}

		if ( ! $this->client->is_available() ) {
			return new WP_Error(
				'private_captcha_unavailable',
				esc_html__( 'Captcha service is currently unavailable.', 'private-captcha' )
			);
		}

		if ( ! $this->verify_captcha() ) {
			return new WP_Error(
				'private_captcha_failed',
				esc_html__( 'Captcha verification failed. Please try again.', 'private-captcha' )
			);
		}

		return $user;
	}

	/**
	 * Verify captcha for registration form.
	 *
	 * @param WP_Error $errors Registration errors object.
	 * @param string   $sanitized_user_login The sanitized user login (unused).
	 * @param string   $user_email The user email (unused).
	 * @return WP_Error The errors object.
	 */
	public function verify_register_captcha( WP_Error $errors, string $sanitized_user_login, string $user_email ): WP_Error {
		unset( $user_email );

		// Only verify if no other errors exist (to avoid unnecessary API calls).
		if ( ! empty( $errors->errors ) ) {
			$this->write_log( 'Skipping register captcha verification due to other errors.' );
			return $errors;
		}

		if ( ! $this->client->is_available() ) {
			$errors->add(
				'private_captcha_unavailable',
				esc_html__( 'Captcha service is currently unavailable.', 'private-captcha' )
			);
			return $errors;
		}

		if ( ! $this->verify_captcha() ) {
			$errors->add(
				'private_captcha_failed',
				esc_html__( 'Captcha verification failed. Please try again.', 'private-captcha' )
			);
		}

		return $errors;
	}

	/**
	 * Verify captcha for reset password form.
	 *
	 * @param WP_Error $errors The errors object to add errors to.
	 */
	public function verify_reset_password_captcha( WP_Error $errors ): void {
		if ( ! $this->client->is_available() ) {
			$errors->add(
				'private_captcha_unavailable',
				esc_html__( 'Captcha service is currently unavailable.', 'private-captcha' )
			);
			return;
		}

		if ( ! $this->verify_captcha() ) {
			$errors->add(
				'private_captcha_failed',
				esc_html__( 'Captcha verification failed. Please try again.', 'private-captcha' )
			);
		}
	}

	/**
	 * Verify captcha for comment form.
	 *
	 * @param array<string, mixed> $commentdata The comment data.
	 * @return array<string, mixed> The comment data.
	 */
	public function verify_comment_captcha( array $commentdata ): array {
		$user_is_logged_in = is_user_logged_in();
		$should_verify     = false;

		if ( $user_is_logged_in && $this->comments_logged_in_field->is_enabled() ) {
			$should_verify = true;
		} elseif ( ! $user_is_logged_in && $this->comments_guest_field->is_enabled() ) {
			$should_verify = true;
		}

		if ( ! $should_verify ) {
			$this->write_log( 'Skipping comment captcha verification as it is not enabled.' );
			return $commentdata;
		}

		if ( ! $this->client->is_available() ) {
			wp_die(
				esc_html__( 'Captcha service is currently unavailable.', 'private-captcha' ),
				esc_html__( 'Comment Submission Error', 'private-captcha' ),
				array(
					'response'  => 400,
					'back_link' => true,
				)
			);
		}

		if ( ! $this->verify_captcha() ) {
			wp_die(
				esc_html__( 'Captcha verification failed. Please try again.', 'private-captcha' ),
				esc_html__( 'Comment Submission Error', 'private-captcha' ),
				array(
					'response'  => 400,
					'back_link' => true,
				)
			);
		}

		return $commentdata;
	}
}
