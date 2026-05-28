<?php
/**
 * Forminator integration for Private Captcha WordPress plugin.
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
 * Forminator integration class
 */
class Forminator extends AbstractIntegration {

	/**
	 * Forminator settings field.
	 *
	 * @var SettingsField
	 */
	private SettingsField $forminator_field;

	/**
	 * Constructor to initialize the integration.
	 *
	 * @param \PrivateCaptchaWP\Client $client The Private Captcha client instance.
	 */
	public function __construct( \PrivateCaptchaWP\Client $client ) {
		parent::__construct( $client );

		$this->plugin_url  = 'https://wordpress.org/plugins/forminator/';
		$this->plugin_name = 'Forminator';

		$this->forminator_field = new SettingsField(
			'forminator_enable',
			'Forminator plugin',
			'Protect Forminator submissions from spam.',
			'Add captcha to forms created with Forminator plugin'
		);
	}

	/**
	 * Get all settings fields for this integration.
	 *
	 * @return array<\PrivateCaptchaWP\SettingsField> Array of SettingsField instances.
	 */
	public function get_settings_fields(): array {
		return array(
			$this->forminator_field,
		);
	}

	/**
	 * Check if Forminator plugin is active.
	 *
	 * @return bool True if Forminator is active.
	 */
	public function is_available(): bool {
		return defined( 'FORMINATOR_VERSION' ) || is_plugin_active( 'forminator/forminator.php' );
	}

	/**
	 * Check if Forminator integration is enabled.
	 *
	 * @return bool True if Forminator integration is enabled.
	 */
	public function is_enabled(): bool {
		return $this->forminator_field->is_enabled();
	}

	/**
	 * Initialize Forminator integration hooks.
	 */
	public function init(): void {
		$this->write_log( 'Initializing Forminator integration' );

		add_filter( 'forminator_render_button_markup', array( $this, 'add_captcha_widget' ), 10, 1 );
		add_filter( 'forminator_pagination_submit_markup', array( $this, 'add_captcha_widget' ), 10, 1 );

		add_filter( 'forminator_cform_form_is_submittable', array( $this, 'verify_captcha_forminator' ), 10, 3 );

		add_action( 'wp_enqueue_scripts', array( $this, 'enqueue_scripts' ) );
	}

	/**
	 * Add Private Captcha widget before the submit button.
	 *
	 * @param string $html The submit button HTML.
	 * @return string Modified HTML.
	 */
	public function add_captcha_widget( $html ) {
		if ( ! $this->is_enabled() ) {
			return $html;
		}

		ob_start();
		Widget::render( '--border-radius: 0.25rem; font-size: 1rem !important;' );
		$widget_html = ob_get_clean();
		$widget_html = false !== $widget_html ? $widget_html : '';

		return str_replace( '<button ', $widget_html . '<button ', (string) $html );
	}

	/**
	 * Verify captcha solution during form processing.
	 *
	 * @param array|mixed          $can_show      Can show the form.
	 * @param int                  $id            Form id.
	 * @param array<string, mixed> $form_settings Form settings.
	 * @return array|mixed Modified submittable array or original.
	 */
	public function verify_captcha_forminator( $can_show, $id, $form_settings ) {
		if ( ! $this->is_enabled() ) {
			return $can_show;
		}

		if ( ! $this->client->is_available() ) {
			return array(
				'can_submit' => false,
				'error'      => __( 'Captcha service is currently unavailable.', 'private-captcha' ),
			);
		}

		static $already_verified = false;
		if ( $already_verified ) {
			return $can_show;
		}

		// phpcs:ignore WordPress.Security.NonceVerification.Missing -- Verified in Forminator itself.
		$solution = isset( $_POST[ \PrivateCaptchaWP\Client::FORM_FIELD ] ) && is_string( $_POST[ \PrivateCaptchaWP\Client::FORM_FIELD ] )
            // phpcs:ignore WordPress.Security.NonceVerification.Missing -- Verified in Forminator itself.
			? sanitize_text_field( wp_unslash( $_POST[ \PrivateCaptchaWP\Client::FORM_FIELD ] ) )
			: '';

		$sitekey          = \PrivateCaptchaWP\Settings::get_sitekey();
		$result           = $this->client->verify_solution( $solution, $sitekey );
		$already_verified = true;

		$this->write_log( 'Private Captcha verification finished. result=' . $result );

		if ( ! $result ) {
			return array(
				'can_submit' => false,
				'error'      => parent::verification_error_message(),
			);
		}

		return $can_show;
	}

	/**
	 * Enqueue Private Captcha widget script with Forminator-specific handlers.
	 */
	public function enqueue_scripts(): void {
		$custom_js = '
                jQuery(document).on("forminator:form:submit:failed forminator:form:submit:success", function(event) {
                    if (event && event.target) {
                        pcResetCaptchaWidgetWP(event.target);
                    }
                });';

		$custom_css = '
            .forminator-pagination--content ~ .private-captcha {
                margin-top: 2rem;
            }
            button.forminator-button-submit:disabled {
                opacity: 0.7;
                cursor: not-allowed;
            }
        ';

		Assets::enqueue( 'private-captcha-widget', $custom_js, $custom_css, '.forminator-button-submit' );
	}
}
