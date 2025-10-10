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
use PrivateCaptchaWP\Widget;
use WP_Error;
use WP_User;

/**
 * WordPress Core forms integration class
 */
class WordPressCore extends AbstractIntegration {

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
		return Settings::is_login_enabled() ||
			Settings::is_registration_enabled() ||
			Settings::is_reset_password_enabled() ||
			Settings::is_comments_logged_in_enabled() ||
			Settings::is_comments_guest_enabled();
	}

	/**
	 * Initialize WordPress Core integration hooks.
	 */
	public function init(): void {
		add_action( 'login_enqueue_scripts', array( $this, 'enqueue_scripts' ) );

		if ( Settings::is_login_enabled() ) {
			add_action( 'login_form', array( $this, 'add_login_captcha' ) );
			add_filter( 'authenticate', array( $this, 'verify_login_captcha' ), 30, 3 );
		}

		if ( Settings::is_registration_enabled() ) {
			add_action( 'register_form', array( $this, 'add_register_captcha' ) );
			add_filter( 'registration_errors', array( $this, 'verify_register_captcha' ), 10, 3 );
		}

		if ( Settings::is_reset_password_enabled() ) {
			add_action( 'lostpassword_form', array( $this, 'add_reset_password_captcha' ) );
			add_action( 'lostpassword_post', array( $this, 'verify_reset_password_captcha' ), 10, 1 );
		}

		if ( Settings::is_comments_logged_in_enabled() || Settings::is_comments_guest_enabled() ) {
			add_action( 'wp_enqueue_scripts', array( $this, 'enqueue_scripts' ) );
			add_filter( 'preprocess_comment', array( $this, 'verify_comment_captcha' ) );
		}

		if ( Settings::is_comments_logged_in_enabled() || Settings::is_comments_guest_enabled() ) {
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

		if ( $user_is_logged_in && Settings::is_comments_logged_in_enabled() ) {
			$should_show = true;
		} elseif ( ! $user_is_logged_in && Settings::is_comments_guest_enabled() ) {
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

		if ( $user_is_logged_in && Settings::is_comments_logged_in_enabled() ) {
			$should_verify = true;
		} elseif ( ! $user_is_logged_in && Settings::is_comments_guest_enabled() ) {
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
