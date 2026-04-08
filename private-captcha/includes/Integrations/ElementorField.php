<?php
/**
 * Elementor Form Field for Private Captcha WordPress plugin.
 *
 * @package PrivateCaptchaWP
 */

declare(strict_types=1);

namespace PrivateCaptchaWP\Integrations;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! class_exists( '\ElementorPro\Modules\Forms\Fields\Field_Base' ) ) {
	return;
}

use PrivateCaptchaWP\Client;
use PrivateCaptchaWP\Settings;
use PrivateCaptchaWP\Widget;

/**
 * Elementor Form Field for Private Captcha.
 *
 * Registers a custom "Private Captcha" field type in Elementor's form widget.
 * This follows Elementor's custom form field API documented at:
 * https://developers.elementor.com/docs/form-fields/add-new-field/
 */
class ElementorField extends \ElementorPro\Modules\Forms\Fields\Field_Base {

	/**
	 * Get field type identifier.
	 *
	 * @return string Field type.
	 */
	public function get_type(): string {
		return 'private-captcha';
	}

	/**
	 * Get field display name.
	 *
	 * @return string Field name.
	 */
	public function get_name(): string {
		return esc_html__( 'Private Captcha', 'private-captcha' );
	}

	/**
	 * Render field output on the frontend.
	 *
	 * @param mixed $item       The field item data.
	 * @param mixed $item_index The field item index.
	 * @param mixed $form       The form widget instance.
	 */
	public function render( $item, $item_index, $form ): void {
		if ( ! Settings::is_configured() ) {
			return;
		}

		Widget::render( '--border-radius: 0.25rem; width: 100%;', 'elementor-field' );

		// Render a hidden input so Elementor knows where to display validation errors.
		$form->add_render_attribute(
			'input' . $item_index,
			array(
				'type'  => 'hidden',
				'style' => 'display: none',
			)
		);

		echo '<input ' . $form->get_render_attribute_string( 'input' . $item_index ) . '>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Elementor's render attribute string is pre-escaped
	}

	/**
	 * Validate the captcha field on form submission.
	 *
	 * @param mixed $field        The submitted field data.
	 * @param mixed $record       The form record.
	 * @param mixed $ajax_handler The AJAX handler.
	 */
	public function validation( $field, $record, $ajax_handler ): void {
		if ( ! Settings::is_configured() ) {
			return;
		}

		// phpcs:ignore WordPress.Security.NonceVerification.Missing -- Elementor handles nonce verification
		$solution = isset( $_POST[ Client::FORM_FIELD ] )
			// phpcs:ignore WordPress.Security.NonceVerification.Missing
			? sanitize_text_field( wp_unslash( $_POST[ Client::FORM_FIELD ] ) )
			: '';

		if ( empty( $solution ) ) {
			$ajax_handler->add_error(
				$field['id'],
				esc_html__( 'Please complete the captcha verification.', 'private-captcha' )
			);
			return;
		}

		$client = new Client();
		$client->update(
			Settings::get_api_key(),
			Settings::get_custom_domain(),
			Settings::is_eu_isolation_enabled()
		);

		if ( ! $client->is_available() ) {
			$ajax_handler->add_error(
				$field['id'],
				esc_html__( 'Captcha service is currently unavailable.', 'private-captcha' )
			);
			return;
		}

		$sitekey = Settings::get_sitekey();
		$result  = $client->verify_solution( $solution, $sitekey );

		if ( ! $result ) {
			$ajax_handler->add_error(
				$field['id'],
				esc_html__( 'Captcha verification failed. Please try again.', 'private-captcha' )
			);
		}
	}

	/**
	 * Update form widget controls.
	 *
	 * Hides the "Required" toggle for this field type since captcha is always required.
	 *
	 * @param \Elementor\Widget_Base $widget The form widget instance.
	 */
	public function update_controls( $widget ): void {
		$elementor = \ElementorPro\Plugin::elementor();

		$control_data = $elementor->controls_manager->get_control_from_stack( $widget->get_unique_name(), 'form_fields' );

		if ( is_wp_error( $control_data ) ) {
			return;
		}

		$control_data = $this->filter_field_control( 'required', $control_data );
		$control_data = $this->filter_field_control( 'width', $control_data );

		$widget->update_control( 'form_fields', $control_data );
	}

	/**
	 * Hide a control for this field type by adding it to the exclusion list.
	 *
	 * @param string               $control_name The control name to hide.
	 * @param array<string, mixed> $control_data The control data array.
	 * @return array<string, mixed> Modified control data.
	 */
	private function filter_field_control( string $control_name, array $control_data ): array {
		if ( ! isset( $control_data['fields'] ) || ! is_array( $control_data['fields'] ) ) {
			return $control_data;
		}

		foreach ( $control_data['fields'] as $index => $field ) {
			if ( ! is_array( $field ) || ! isset( $field['name'] ) || $control_name !== $field['name'] ) {
				continue;
			}

			if ( ! isset( $field['conditions']['terms'] ) || ! is_array( $field['conditions']['terms'] ) ) {
				continue;
			}

			foreach ( $field['conditions']['terms'] as $condition_index => $terms ) {
				if ( ! is_array( $terms ) ) {
					continue;
				}

				if ( ! isset( $terms['name'] ) || 'field_type' !== $terms['name'] ) {
					continue;
				}

				if ( ! isset( $terms['operator'] ) || '!in' !== $terms['operator'] ) {
					continue;
				}

				if ( ! isset( $terms['value'] ) || ! is_array( $terms['value'] ) ) {
					continue;
				}

				$control_data['fields'][ $index ]['conditions']['terms'][ $condition_index ]['value'][] = $this->get_type();
				break;
			}
			break;
		}

		return $control_data;
	}

	/**
	 * Constructor.
	 *
	 * Sets up the editor preview script hook.
	 */
	public function __construct() {
		parent::__construct();
		add_action( 'elementor/preview/init', array( $this, 'editor_preview_footer' ) );
	}

	/**
	 * Register footer script for editor preview.
	 */
	public function editor_preview_footer(): void {
		add_action( 'wp_footer', array( $this, 'content_template_script' ) );
	}

	/**
	 * Output JavaScript template for the Elementor editor preview.
	 *
	 * Renders a placeholder instead of the actual widget in the editor
	 * to avoid interfering with the Elementor drag-and-drop interface.
	 */
	public function content_template_script(): void {
		?>
		<script>
		jQuery( document ).ready( () => {
			elementor.hooks.addFilter(
				'elementor_pro/forms/content_template/field/<?php echo esc_js( $this->get_type() ); ?>',
				function( inputField, item, i ) {
					const fieldId = `form_field_${i}`;
					return `<div id="${fieldId}" style="
						position: relative;
						width: 100%;
						text-align: center;
						border: 1px solid #e0e0e0;
						border-radius: 0.25rem;
						padding: 20px;
						background-color: #fafafa;
						color: #555;
						font-size: 14px;">&#128274; Private Captcha</div>`;
				}, 10, 3
			);
		});
		</script>
		<?php
	}
}
