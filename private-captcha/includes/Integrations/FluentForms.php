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
use PrivateCaptchaWP\Client;
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
	private SettingsField $fluent_forms_field;

	/**
	 * Rendered Fluent Forms instance identifiers.
	 *
	 * @var array<string, bool>
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

		$this->fluent_forms_field = new SettingsField(
			'fluentforms_enable',
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
			$this->fluent_forms_field,
		);
	}

	/**
	 * Check if Fluent Forms plugin is active.
	 *
	 * @return bool True if Fluent Forms is active.
	 */
	public function is_available(): bool {
		return is_plugin_active( 'fluentform/fluentform.php' );
	}

	/**
	 * Check if Fluent Forms integration is enabled.
	 *
	 * @return bool True if Fluent Forms integration is enabled.
	 */
	public function is_enabled(): bool {
		return $this->fluent_forms_field->is_enabled();
	}

	/**
	 * Initialize Fluent Forms integration hooks.
	 */
	public function init(): void {
		$this->write_log( 'Initializing Fluent Forms integration' );

		add_action( 'fluentform/render_item_submit_button', array( $this, 'add_captcha_widget' ), 10, 2 );
		add_action( 'fluentform/render_item_step_end', array( $this, 'add_captcha_widget' ), 10, 2 );
		add_filter( 'fluentform/validation_errors', array( $this, 'validate_captcha' ), 10, 4 );
		add_filter( 'fluentform/white_listed_fields', array( $this, 'add_whitelisted_fields' ), 10, 2 );
		add_action( 'wp_enqueue_scripts', array( $this, 'enqueue_scripts' ) );
	}

	/**
	 * Add Private Captcha widget before the submit button area.
	 *
	 * @param array<string, mixed> $item The Fluent Forms item being rendered.
	 * @param object               $form The current Fluent Forms form object.
	 */
	public function add_captcha_widget( array $item, object $form ): void {
		// Fluent Forms passes the rendered item, but this integration only needs the form instance.
		unset( $item );

		$form_id                 = absint( $form->id ?? 0 );
		$form_instance_id        = (string) ( $form->instance_index ?? 0 );
		$unique_form_identifier  = $form_id . ':' . $form_instance_id;

		if ( isset( $this->rendered_form_instances[ $unique_form_identifier ] ) ) {
			return;
		}

		ob_start();
		Widget::render( '--border-radius: 0.25rem; font-size: 1rem !important;' );
		$widget = ob_get_clean();
		$widget = (string) $widget;

		$allowed_html = array(
			'div' => array(
				'class'                => array(),
				'data-name'            => array(),
				'data-store-variable'  => array(),
				'data-sitekey'         => array(),
				'data-solution-field'  => array(),
				'data-theme'           => array(),
				'data-display-mode'    => array(),
				'data-start-mode'      => array(),
				'data-lang'            => array(),
				'data-debug'           => array(),
				'data-puzzle-endpoint' => array(),
				'data-eu'              => array(),
				'data-styles'          => array(),
			),
		);

		$widget = wp_kses( $widget, $allowed_html );

		if ( '' === trim( $widget ) ) {
			return;
		}

		$this->rendered_form_instances[ $unique_form_identifier ] = true;

		echo wp_kses(
			'<div class="ff-el-group ff-private-captcha-field"><div class="ff-el-input--content"><div class="ff-el-private-captcha" data-name="' . esc_attr( Client::FORM_FIELD ) . '">',
			array(
				'div' => array(
					'class'     => array(),
					'data-name' => array(),
				),
			)
		);
		// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Sanitized with wp_kses() above.
		echo $widget;
		echo '</div></div></div>';
	}

	/**
	 * Validate the Private Captcha solution for Fluent Forms submissions.
	 *
	 * @param array<string, array<int, string>> $errors   Existing validation errors.
	 * @param array<string, mixed>              $form_data Submitted form data.
	 * @param object                            $form      The current Fluent Forms form object.
	 * @param array<string, mixed>              $fields    Parsed Fluent Forms fields.
	 * @return array<string, array<int, string>> Updated validation errors.
	 */
	public function validate_captcha( array $errors, array $form_data, object $form, array $fields ): array {
		// These parameters are required by the Fluent Forms filter signature.
		unset( $form_data, $form, $fields );

		if ( ! $this->is_enabled() ) {
			$this->write_log( 'Skipping captcha verification as Fluent Forms integration is not enabled' );
			return $errors;
		}

		if ( ! empty( $errors ) ) {
			$this->write_log( 'Skipping captcha verification due to existing Fluent Forms validation errors' );
			return $errors;
		}

		if ( ! $this->client->is_available() ) {
			$errors[ Client::FORM_FIELD ] = array(
				__( 'Captcha service is currently unavailable.', 'private-captcha' ),
			);
			return $errors;
		}

		if ( ! parent::verify_captcha() ) {
			$errors[ Client::FORM_FIELD ] = array(
				__( 'Captcha verification failed. Please try again.', 'private-captcha' ),
			);
		}

		return $errors;
	}

	/**
	 * Add the Private Captcha field to Fluent Forms whitelist.
	 *
	 * @param array<int, string> $white_listed_fields Existing whitelisted fields.
	 * @param int                $form_id             Fluent Forms form ID.
	 * @return array<int, string> Updated whitelisted fields.
	 */
	public function add_whitelisted_fields( array $white_listed_fields, int $form_id ): array {
		// The whitelist applies uniformly, so the current Fluent Forms form ID is not needed here.
		unset( $form_id );

		if ( in_array( Client::FORM_FIELD, $white_listed_fields, true ) ) {
			return $white_listed_fields;
		}

		$white_listed_fields[] = Client::FORM_FIELD;

		return $white_listed_fields;
	}

	/**
	 * Enqueue Private Captcha widget script with Fluent Forms-specific handlers.
	 */
	public function enqueue_scripts(): void {
		if ( ! $this->is_enabled() ) {
			return;
		}

$fluent_forms_custom_js = <<<'JS'
if (window.jQuery) {
    function pcResetFluentFormsCaptcha(form) {
        if (typeof pcResetCaptchaWidgetWP === "function") {
            pcResetCaptchaWidgetWP(form);
        }
    }

    window.jQuery(document).on("fluentform_validation_failed fluentform_submission_failed", "form.frm-fluent-form", function() {
        pcResetFluentFormsCaptcha(this);
    });

    window.jQuery(document.body).on("fluentform_reset fluentform_submission_success", function(event, form) {
        if (form && form.length) {
            pcResetFluentFormsCaptcha(form[0]);
        }
    });
}
JS;

		$fluent_forms_custom_css = <<<'CSS'
.ff-private-captcha-field .private-captcha {
    margin-bottom: 0;
}
CSS;

		Assets::enqueue( 'private-captcha-widget', $fluent_forms_custom_js, $fluent_forms_custom_css );
	}
}
