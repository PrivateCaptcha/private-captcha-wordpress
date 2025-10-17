<?php
/**
 * Contact Form 7 integration for Private Captcha WordPress plugin.
 *
 * @package PrivateCaptchaWP
 */

declare(strict_types=1);

namespace PrivateCaptchaWP\Integrations;

use PrivateCaptchaWP\Assets;
use PrivateCaptchaWP\Settings;
use PrivateCaptchaWP\SettingsField;
use PrivateCaptchaWP\Widget;

/**
 * Contact Form 7 integration class
 */
class ContactForm7 extends AbstractIntegration {

	/**
	 * Contact Form 7 settings field.
	 *
	 * @var SettingsField
	 */
	private SettingsField $cf7_field;

	/**
	 * Constructor to initialize the integration.
	 *
	 * @param \PrivateCaptchaWP\Client $client The Private Captcha client instance.
	 */
	public function __construct( \PrivateCaptchaWP\Client $client ) {
		parent::__construct( $client );

		$this->plugin_url  = 'https://wordpress.org/plugins/contact-form-7/';
		$this->plugin_name = 'Contact Form 7';

		$this->cf7_field = new SettingsField(
			'contactform7_enable',
			'Contact Form 7 plugin',
			'Protect Contact Form 7 submissions from spam.',
			'Add captcha to forms created with Contact Form 7 plugin'
		);
	}

	/**
	 * Get all settings fields for this integration.
	 *
	 * @return array<\PrivateCaptchaWP\SettingsField> Array of SettingsField instances.
	 */
	public function get_settings_fields(): array {
		return array(
			$this->cf7_field,
		);
	}

	/**
	 * Check if Contact Form 7 plugin is active.
	 *
	 * @return bool True if Contact Form 7 is active.
	 */
	public function is_available(): bool {
		return is_plugin_active( 'contact-form-7/wp-contact-form-7.php' );
	}

	/**
	 * Check if Contact Form 7 integration is enabled.
	 *
	 * @return bool True if Contact Form 7 integration is enabled.
	 */
	public function is_enabled(): bool {
		return $this->cf7_field->is_enabled();
	}

	/**
	 * Initialize Contact Form 7 integration hooks.
	 */
	public function init(): void {
		$this->write_log( 'Initializing Contact Form 7 integration' );

		// Enqueue scripts.
		add_action( 'wp_enqueue_scripts', array( $this, 'enqueue_scripts_cf7' ), 20, 0 );

		// Add hidden field for captcha response.
		add_filter( 'wpcf7_form_hidden_fields', array( $this, 'add_hidden_fields' ), 100, 1 );

		// Register form-tag.
		add_action( 'wpcf7_init', array( $this, 'add_form_tag_privatecaptcha' ), 10, 0 );

		// Auto-inject widget if not present in form.
		add_filter( 'wpcf7_form_elements', array( $this, 'prepend_widget' ), 10, 1 );

		// Verify captcha solution during form processing.
		add_filter( 'wpcf7_spam', array( $this, 'verify_response_cf7' ), 9, 2 );
	}

	/**
	 * Enqueue frontend scripts for Private Captcha.
	 */
	public function enqueue_scripts_cf7(): void {
		if ( ! $this->is_enabled() ) {
			return;
		}

		$cf7_custom_js = '
        const events = ["wpcf7mailsent", "wpcf7invalid", "wpcf7spam", "wpcf7mailfailed", "wpcf7submit", "wpcf7reset"];
        document.querySelectorAll(".wpcf7").forEach(function(wpcf7Element) {
            events.forEach(function(eventName) {
                wpcf7Element.addEventListener(eventName, function(event) {
                    resetCaptchaWidget(event.target);
                });
            });

            wpcf7Element.addEventListener("wpcf7init", function(event) {
                const submit = event.target.querySelector(".wpcf7-submit");
                if (submit) submit.disabled = true;
            });
        });';

		$this->enqueue_scripts( 'wpcf7-privatecaptcha', $cf7_custom_js );
	}

	/**
	 * Add hidden form field for Private Captcha.
	 *
	 * @param array<string, mixed> $fields Existing hidden fields.
	 * @return array<string, mixed> Modified hidden fields.
	 */
	public function add_hidden_fields( array $fields ): array {
		if ( ! $this->is_enabled() ) {
			$this->write_log( 'Skipping adding hidden fields as Contact Form 7 integration is not enabled' );
			return $fields;
		}

		return array_merge(
			$fields,
			array(
				\PrivateCaptchaWP\Client::FORM_FIELD => '',
			)
		);
	}

	/**
	 * Register form-tag types for Private Captcha.
	 */
	public function add_form_tag_privatecaptcha(): void {
		$this->write_log( 'About to register [privatecaptcha] tag' );

		if ( ! $this->is_enabled() ) {
			$this->write_log( 'Skipping form tag registration as Contact Form 7 integration is not enabled' );
			if ( function_exists( 'wpcf7_add_form_tag' ) ) {
				$this->write_log( 'Registering [privatecaptcha] tag as empty string' );
				wpcf7_add_form_tag(
					'privatecaptcha',
					'__return_empty_string',
					array(
						'display-block' => true,
					)
				);
			}
			return;
		}

		if ( function_exists( 'wpcf7_add_form_tag' ) ) {
			$this->write_log( 'Registering real [privatecaptcha] tag' );
			wpcf7_add_form_tag(
				'privatecaptcha',
				array( $this, 'form_tag_handler' ),
				array(
					'display-block' => true,
					'singular'      => true,
					'theme'         => true,
				)
			);
		}
	}

	/**
	 * The Private Captcha form-tag handler.
	 *
	 * @param mixed $tag The form tag object.
	 * @return string The widget HTML.
	 */
	public function form_tag_handler( $tag ): string {
		if ( ! $this->is_enabled() ) {
			$this->write_log( 'Skipping form tag handler as Contact Form 7 integration is not enabled' );
			return '';
		}

		// Check if theme option is specified in the form-tag.
		$theme_override = null;
		if ( is_object( $tag ) && method_exists( $tag, 'get_option' ) ) {
			$theme_option = $tag->get_option( 'theme', '(light|dark)', true );
			if ( ! empty( $theme_option ) ) {
				$theme_override = $theme_option;
				$this->write_log( 'Using tag theme override:' . $theme_option );
			}
		}

		ob_start();
		Widget::render( '--border-radius: 0.25rem;', 'wpcf7-form-control', $theme_override );
		$output = ob_get_clean();
		return false !== $output ? $output : '';
	}

	/**
	 * Prepend a Private Captcha widget to the form content if the form template
	 * does not include a Private Captcha form-tag.
	 *
	 * @param string $content The form content.
	 * @return string Modified form content.
	 */
	public function prepend_widget( string $content ): string {
		if ( ! $this->is_enabled() ) {
			$this->write_log( 'Skipping prepending widget as Contact Form 7 integration is not enabled' );
			return $content;
		}

		if ( ! function_exists( 'wpcf7_get_current_contact_form' ) ) {
			$this->write_log( 'wpcf7_get_current_contact_form function not found' );
			return $content;
		}

		$contact_form = wpcf7_get_current_contact_form();

		if ( ! $contact_form ) {
			$this->write_log( 'Contact Form 7 form is not valid' );
			return $content;
		}

		if ( ! is_object( $contact_form ) || ! method_exists( $contact_form, 'scan_form_tags' ) ) {
			return $content;
		}

		// Check if form already has a [privatecaptcha] tag.
		$tags = $contact_form->scan_form_tags(
			array(
				'type' => 'privatecaptcha',
			)
		);

		if ( ! empty( $tags ) ) {
			return $content;
		}

		// Check if form content already contains a div with class="private-captcha".
		if ( preg_match( '/<div[^>]+class=["\'][^"\']*private-captcha[^"\']*["\'][^>]*>/i', $content ) ) {
			$this->write_log( 'Contact Form 7 form already contains a div with class="private-captcha"' );
			return $content;
		}

		$this->write_log( 'Contact Form 7 form does not have [privatecaptcha] tag' );
		ob_start();
		Widget::render( '--border-radius: 0.25rem;', 'wpcf7-form-control' );
		$widget  = ob_get_clean();
		$widget  = false !== $widget ? $widget : '';
		$content = $widget . "\n\n" . $content;

		return $content;
	}

	/**
	 * Verify Private Captcha token on the server side.
	 *
	 * @param bool  $spam The spam/ham status inherited from preceding callbacks.
	 * @param mixed $submission The submission object.
	 * @return bool True if the submitter is a bot, false if a human.
	 */
	public function verify_response_cf7( bool $spam, $submission ): bool {
		if ( $spam ) {
			$this->write_log( 'Skipping captcha verification as submission is already spam' );
			return $spam;
		}

		if ( ! $this->is_enabled() ) {
			$this->write_log( 'Skipping captcha verification as Contact Form 7 integration is not enabled' );
			return $spam;
		}

		if ( ! $this->client->is_available() ) {
			$this->write_log( 'Skipping captcha verification in Contact Form 7 as PC client is not available' );
			return $spam;
		}

		// phpcs:ignore WordPress.Security.NonceVerification.Missing -- Contact Form 7 handles nonce verification
		$token = isset( $_POST[ \PrivateCaptchaWP\Client::FORM_FIELD ] )
			// phpcs:ignore WordPress.Security.NonceVerification.Missing -- Contact Form 7 handles nonce verification
			? sanitize_text_field( wp_unslash( $_POST[ \PrivateCaptchaWP\Client::FORM_FIELD ] ) )
			: '';

		if ( $this->client->verify_solution( $token ) ) {
			// Human.
			$spam = false;
		} else {
			// Bot.
			$spam = true;

			if ( '' === $token ) {
				if ( is_object( $submission ) && method_exists( $submission, 'add_spam_log' ) ) {
					$submission->add_spam_log(
						array(
							'agent'  => 'privatecaptcha',
							'reason' => __( 'Private Captcha token is empty.', 'private-captcha' ),
						)
					);
				}
			} elseif ( is_object( $submission ) && method_exists( $submission, 'add_spam_log' ) ) {
					$submission->add_spam_log(
						array(
							'agent'  => 'privatecaptcha',
							'reason' => __( 'Private Captcha verification failed.', 'private-captcha' ),
						)
					);
			}
		}

		return $spam;
	}
}
