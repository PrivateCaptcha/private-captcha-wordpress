<?php
/**
 * Fluent Forms integration for Private Captcha WordPress plugin.
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
 * Fluent Forms integration class
 */
class FluentForms extends AbstractIntegration {

	/**
	 * Fluent Forms settings field.
	 *
	 * @var SettingsField
	 */
	private SettingsField $fluentforms_field;

	/**
	 * Rendered forms to skip multi-step issues.
	 *
	 * @var array<mixed>
	 */
	private array $rendered_form_instances = array();

	/**
	 * Constructor to initialize the integration.
	 *
	 * @param \PrivateCaptchaWP\Client $client The Private Captcha client instance.
	 */
	public function __construct( \PrivateCaptchaWP\Client $client ) {
		parent::__construct( $client );

		$this->plugin_url  = 'https://wordpress.org/plugins/fluentform/';
		$this->plugin_name = 'Fluent Forms';

		$this->fluentforms_field = new SettingsField(
			'fluentforms_enable_fluentforms',
			'Fluent Forms plugin',
			'Protect Fluent Forms submissions from spam.',
			'Add captcha to forms created with Fluent Forms plugin'
		);
	}

	/**
	 * Get all settings fields for this integration.
	 *
	 * @return array<\PrivateCaptchaWP\SettingsField> Array of SettingsField instances.
	 */
	public function get_settings_fields(): array {
		return array(
			$this->fluentforms_field,
		);
	}

	/**
	 * Check if Fluent Forms plugin is active.
	 *
	 * @return bool True if Fluent Forms is active.
	 */
	public function is_available(): bool {
		return defined( 'FLUENTFORM' ) || is_plugin_active( 'fluentform/fluentform.php' );
	}

	/**
	 * Check if Fluent Forms integration is enabled.
	 *
	 * @return bool True if Fluent Forms integration is enabled.
	 */
	public function is_enabled(): bool {
		return $this->fluentforms_field->is_enabled();
	}

	/**
	 * Initialize Fluent Forms integration hooks.
	 */
	public function init(): void {
		$this->write_log( 'Initializing Fluent Forms integration' );

		// Add captcha widget before submit button.
		add_action( 'fluentform/render_item_submit_button', array( $this, 'add_captcha_widget' ), 10, 2 );
		add_action( 'fluentform/render_item_step_end', array( $this, 'add_captcha_widget' ), 10, 2 );

		// Verify captcha solution during form processing.
		add_filter( 'fluentform/before_insert_submission', array( $this, 'verify_captcha_fluentforms' ), 5, 3 );

		// Enqueue scripts on Fluent Forms footer.
		add_action( 'wp_enqueue_scripts', array( $this, 'enqueue_scripts' ) );
	}

	/**
	 * Add Private Captcha widget before the submit button.
	 *
	 * @param array<mixed> $item Form item.
	 * @param object       $form Form instance.
	 */
	public function add_captcha_widget( array $item, object $form ): void {
		$form_id          = absint( $form->id ?? 0 );
		$form_instance_id = (string) ( $form->instance_index ?? 0 );
		$render_key       = $form_id . ':' . $form_instance_id;

		if ( isset( $this->rendered_form_instances[ $render_key ] ) ) {
			return;
		}

		Widget::render( '--border-radius: 0.25rem; font-size: 1rem !important;', 'fluentforms-field' );
		$this->rendered_form_instances[ $render_key ] = true;
	}

	/**
	 * Verify captcha solution during form processing.
	 *
	 * @param array<string, mixed> $insert_data Form insert data.
	 * @param array<string, mixed> $data        Form submission raw data.
	 * @param object               $form        Form object.
	 * @return mixed Form insert data.
	 */
	public function verify_captcha_fluentforms( array $insert_data, array $data, $form ) {
		if ( ! $this->is_enabled() ) {
			$this->write_log( 'Skipping captcha verification as Fluent Forms integration is not enabled' );
			return $insert_data;
		}

		if ( ! $this->client->is_available() ) {
			wp_send_json(
				array(
					'errors' => array(
						'g-recaptcha-response' => array( __( 'Captcha service is currently unavailable.', 'private-captcha' ) ),
					),
				),
				422
			);
		}

		$solution = isset( $data[ \PrivateCaptchaWP\Client::FORM_FIELD ] ) && is_string( $data[ \PrivateCaptchaWP\Client::FORM_FIELD ] )
			? sanitize_text_field( wp_unslash( $data[ \PrivateCaptchaWP\Client::FORM_FIELD ] ) )
			: '';
		$sitekey  = \PrivateCaptchaWP\Settings::get_sitekey();
		$result   = $this->client->verify_solution( $solution, $sitekey );

		$this->write_log( 'Private Captcha verification finished. result=' . $result );

		if ( ! $result ) {
			wp_send_json(
				array(
					'errors' => array(
						'g-recaptcha-response' => array( __( 'Captcha verification failed. Please try again.', 'private-captcha' ) ),
					),
				),
				422
			);
		}

		return $insert_data;
	}

	/**
	 * Enqueue Private Captcha widget script with Fluent Forms-specific handlers.
	 */
	public function enqueue_scripts(): void {
		$fluentforms_custom_js = '
                jQuery(document).on("fluentform_submission_failed", function(event, data) {
                    if (data && data.form) {
                        pcResetCaptchaWidgetWP(data.form[0]);
                    }
                });
                jQuery(document).on("fluentform_submission_success", function(event, data) {
                    if (data && data.form) {
                        pcResetCaptchaWidgetWP(data.form[0]);
                    }
                });';

		$fluentforms_custom_css = '
            button.ff-btn-submit:disabled {
                opacity: 0.7;
                cursor: not-allowed;
            }
        ';

		Assets::enqueue( 'private-captcha-widget', $fluentforms_custom_js, $fluentforms_custom_css, '.ff-btn-submit' );
	}
}
