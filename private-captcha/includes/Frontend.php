<?php

declare(strict_types=1);

namespace PrivateCaptchaWP;

use WP_Error;
use WP_User;

/**
 * Frontend functionality
 */
class Frontend {

	private Client $client;

	public function __construct() {
		if ( Settings::is_configured() ) {
			try {
				$this->client = new Client(
					Settings::get_api_key(),
					Settings::get_custom_domain(),
					Settings::is_eu_isolation_enabled()
				);
				$this->init_hooks();
			} catch ( \PrivateCaptcha\Exceptions\PrivateCaptchaException $e ) {
				wp_debug_log( 'Private Captcha Frontend initialization failed: ' . $e->getMessage() );
				return;
			}
		}
	}

	private function init_hooks(): void {
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

	public function enqueue_scripts(): void {
		$script_domain = Settings::get_custom_domain();
		if ( empty( $script_domain ) ) {
			$script_domain = 'privatecaptcha.com';
		}

		if ( str_starts_with( $script_domain, 'cdn.' ) ) {
			$script_domain = substr( $script_domain, 4 );
		}

		wp_enqueue_script(
			'private-captcha-widget',
			"https://cdn.{$script_domain}/widget/js/privatecaptcha.js",
			array(),
			PRIVATE_CAPTCHA_VERSION,
			true
		);

		add_filter( 'script_loader_tag', array( $this, 'add_defer_to_captcha_script' ), 10, 3 );
	}

	public function add_login_captcha(): void {
		$this->render_captcha_widget( 'display: block; min-width: 0; height: 100%; --border-radius: 0.25rem;' );
	}

	public function add_register_captcha(): void {
		$this->render_captcha_widget( 'display: block; min-width: 0; height: 100%; --border-radius: 0.25rem;' );
	}

	public function add_reset_password_captcha(): void {
		$this->render_captcha_widget( 'display: block; min-width: 0; height: 100%; --border-radius: 0.25rem;' );
	}

	public function add_comment_captcha(): void {
		$this->render_captcha_widget( '--border-radius: 0.25rem;' );
	}

	/**
	 * Modify comment form submit field to include captcha widget
	 *
	 * @param string               $submit_field HTML markup for the submit field.
	 * @param array<string, mixed> $args Comment form arguments.
	 * @return string Modified submit field HTML.
	 */
	public function modify_comment_submit_field( string $submit_field, array $args ): string {
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

		// Capture the captcha widget HTML
		ob_start();
		$this->render_captcha_widget( '--border-radius: 0.25rem;' );
		$captcha_html = ob_get_clean();

		return $captcha_html . $submit_field;
	}

	private function render_captcha_widget( string $default_styles = '' ): void {
		if ( ! Settings::is_configured() || ! isset( $this->client ) ) {
			return;
		}

		$sitekey       = Settings::get_sitekey();
		$theme         = Settings::get_theme();
		$language      = Settings::get_language();
		$start_mode    = Settings::get_start_mode();
		$debug_mode    = Settings::is_debug_enabled();
		$custom_domain = Settings::get_custom_domain();
		$eu_isolation  = Settings::is_eu_isolation_enabled();
		$custom_styles = Settings::get_custom_styles();

		$effective_styles = ! empty( $custom_styles ) ? $custom_styles : $default_styles;

		$attributes = array(
			'class="private-captcha"',
			'data-sitekey="' . esc_attr( $sitekey ) . '"',
			'data-theme="' . esc_attr( $theme ) . '"',
			'data-display-mode="widget"',
			'data-start-mode="' . esc_attr( $start_mode ) . '"',
			'data-lang="' . esc_attr( $language ) . '"',
		);

		if ( $debug_mode ) {
			$attributes[] = 'data-debug="true"';
		}

		if ( ! empty( $custom_domain ) ) {
			if ( str_starts_with( $custom_domain, 'api.' ) ) {
				$custom_domain = substr( $custom_domain, 4 );
			}
			$attributes[] = 'data-puzzle-endpoint="' . esc_attr( "https://api.{$custom_domain}/puzzle" ) . '"';
		} elseif ( $eu_isolation ) {
			$attributes[] = 'data-eu="true"';
		}

		if ( ! empty( $effective_styles ) ) {
			$attributes[] = 'data-styles="' . esc_attr( $effective_styles ) . '"';
		}

		// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
		echo '<div ' . implode( ' ', $attributes ) . '></div>';

		// Add custom CSS for better integration
		$allowed_html = array(
			'style' => array(),
		);
		echo wp_kses(
			'<style>
            .private-captcha {
                margin: 1rem 0;
            }
            input[type="submit"]:disabled,
            button[type="submit"]:disabled {
                opacity: 0.7;
                cursor: not-allowed;
            }
        </style>',
			$allowed_html
		);

		$allowed_html = array(
			'script' => array(),
		);
		echo wp_kses(
			'<script>
        (function() {
            function setFormButtonEnabled(captchaElement, enabled) {
                const form = captchaElement.closest("form");
                if (!form) return;
                const submitButton = form.querySelector("input[type=\"submit\"], button[type=\"submit\"]");
                if (submitButton) {
                    submitButton.disabled = !enabled;
                }
            }

            function setupPagePrivateCaptcha() {
                document.querySelectorAll(".private-captcha").forEach((e) => setFormButtonEnabled(e, false));

                document.querySelectorAll(".private-captcha").forEach(function(currentWidget) {
                    currentWidget.addEventListener("privatecaptcha:init", (event) => setFormButtonEnabled(event.detail.element, false));
                    currentWidget.addEventListener("privatecaptcha:finish", (event) => setFormButtonEnabled(event.detail.element, true));
                });
            }

            if (document.readyState === "loading") {
                document.addEventListener("DOMContentLoaded", setupPagePrivateCaptcha);
            } else {
                setupPagePrivateCaptcha();
            }
        })();
        </script>',
			$allowed_html
		);
	}

	public function verify_login_captcha( WP_User|WP_Error|null $user, string $username, string $password ): WP_User|WP_Error|null {
		if ( empty( $username ) || empty( $password ) ) {
			return $user;
		}

		if ( is_wp_error( $user ) ) {
			return $user;
		}

		if ( ! isset( $this->client ) ) {
			return new WP_Error(
				'private_captcha_unavailable',
				esc_html__( 'Captcha service is currently unavailable.', 'private-captcha' )
			);
		}

		if ( ! $this->client->verify_request() ) {
			return new WP_Error(
				'private_captcha_failed',
				esc_html__( 'Captcha verification failed. Please try again.', 'private-captcha' )
			);
		}

		return $user;
	}

	public function verify_register_captcha( WP_Error $errors, string $sanitized_user_login, string $user_email ): WP_Error {
		// Only verify if no other errors exist (to avoid unnecessary API calls)
		if ( ! empty( $errors->errors ) ) {
			return $errors;
		}

		if ( ! isset( $this->client ) ) {
			$errors->add(
				'private_captcha_unavailable',
				esc_html__( 'Captcha service is currently unavailable.', 'private-captcha' )
			);
			return $errors;
		}

		if ( ! $this->client->verify_request() ) {
			$errors->add(
				'private_captcha_failed',
				esc_html__( 'Captcha verification failed. Please try again.', 'private-captcha' )
			);
		}

		return $errors;
	}

	public function verify_reset_password_captcha( WP_Error $errors ): void {
		if ( ! isset( $this->client ) ) {
			$errors->add(
				'private_captcha_unavailable',
				esc_html__( 'Captcha service is currently unavailable.', 'private-captcha' )
			);
			return;
		}

		if ( ! $this->client->verify_request() ) {
			$errors->add(
				'private_captcha_failed',
				esc_html__( 'Captcha verification failed. Please try again.', 'private-captcha' )
			);
		}
	}

	/**
	 * @param array<string, mixed> $commentdata
	 * @return array<string, mixed>
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
			return $commentdata;
		}

		if ( ! isset( $this->client ) ) {
			wp_die(
				esc_html__( 'Captcha service is currently unavailable.', 'private-captcha' ),
				esc_html__( 'Comment Submission Error', 'private-captcha' ),
				array(
					'response'  => 400,
					'back_link' => true,
				)
			);
		}

		if ( ! $this->client->verify_request() ) {
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

	/**
	 * Add defer attribute to the Private Captcha script
	 *
	 * @param string $tag    The script tag.
	 * @param string $handle The script handle.
	 * @param string $src    The script source URL.
	 * @return string Modified script tag.
	 */
	public function add_defer_to_captcha_script( string $tag, string $handle, string $src ): string {
		if ( $handle === 'private-captcha-widget' ) {
			return str_replace( '<script ', '<script defer ', $tag );
		}
		return $tag;
	}
}
