<?php
/**
 * Settings field class for Private Captcha WordPress plugin.
 *
 * @package PrivateCaptchaWP
 */

declare(strict_types=1);

namespace PrivateCaptchaWP;

/**
 * Settings field class
 */
class SettingsField {

	/**
	 * The field label text (untranslated).
	 *
	 * @var string
	 */
	private string $label;

	/**
	 * The field description text (untranslated).
	 *
	 * @var string
	 */
	private string $description;

	/**
	 * The checkbox text (untranslated).
	 *
	 * @var string
	 */
	private string $checkbox_text;

	/**
	 * The unavailable warning text (untranslated).
	 *
	 * @var string
	 */
	private string $unavailable_warning;

	/**
	 * The setting name/key.
	 *
	 * @var string
	 */
	private string $setting_name;

	/**
	 * Constructor to initialize the settings field.
	 *
	 * @param string $setting_name The setting name/key.
	 * @param string $label The field label text.
	 * @param string $description The field description text.
	 * @param string $checkbox_text The checkbox text.
	 * @param string $unavailable_warning The unavailable warning text.
	 */
	public function __construct(
		string $setting_name,
		string $label,
		string $description = '',
		string $checkbox_text = '',
		string $unavailable_warning = ''
	) {
		$this->setting_name        = $setting_name;
		$this->label               = $label;
		$this->description         = $description;
		$this->checkbox_text       = $checkbox_text;
		$this->unavailable_warning = $unavailable_warning;
	}

	/**
	 * Get the translated label.
	 *
	 * @return string The translated label.
	 */
	public function get_label(): string {
		return $this->label;
	}

	/**
	 * Get the translated description with allowed HTML tags.
	 *
	 * @return string The translated and sanitized description.
	 */
	public function get_description(): string {
		if ( empty( $this->description ) ) {
			return '';
		}

		return wp_kses(
			$this->description,
			array(
				'a'      => array(
					'href'   => true,
					'target' => true,
				),
				'strong' => array(),
			)
		);
	}

	/**
	 * Get the translated checkbox text.
	 *
	 * @return string The translated checkbox text.
	 */
	public function get_checkbox_text(): string {
		if ( empty( $this->checkbox_text ) ) {
			// translators: %s is the name of the form or integration being configured.
			return sprintf( __( 'Add captcha to %s', 'private-captcha' ), $this->get_label() );
		}

		return $this->checkbox_text;
	}

	/**
	 * Get the translated unavailable warning with allowed HTML tags.
	 *
	 * @return string The translated and sanitized unavailable warning.
	 */
	public function get_unavailable_warning(): string {
		if ( empty( $this->unavailable_warning ) ) {
			return '';
		}

		return wp_kses(
			$this->unavailable_warning,
			array(
				'a' => array(
					'href'   => true,
					'target' => true,
				),
			)
		);
	}

	/**
	 * Check if the field has a description.
	 *
	 * @return bool True if description is not empty.
	 */
	public function has_description(): bool {
		return ! empty( $this->description );
	}

	/**
	 * Check if the field has an unavailable warning.
	 *
	 * @return bool True if unavailable warning is not empty.
	 */
	public function has_unavailable_warning(): bool {
		return ! empty( $this->unavailable_warning );
	}

	/**
	 * Get the setting name/key.
	 *
	 * @return string The setting name.
	 */
	public function get_setting_name(): string {
		return $this->setting_name;
	}

	/**
	 * Check if this setting is enabled.
	 *
	 * @return bool True if the setting is enabled.
	 */
	public function is_enabled(): bool {
		return (bool) \PrivateCaptchaWP\Settings::get_option( $this->setting_name, false );
	}
}
