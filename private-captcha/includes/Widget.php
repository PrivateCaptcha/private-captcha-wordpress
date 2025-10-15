<?php
/**
 * Widget rendering functionality for Private Captcha WordPress plugin.
 *
 * @package PrivateCaptchaWP
 */

declare(strict_types=1);

namespace PrivateCaptchaWP;

/**
 * Widget renderer class
 */
class Widget {

	/**
	 * Render the Private Captcha widget HTML.
	 *
	 * @param string      $default_styles Default CSS styles for the widget.
	 * @param string      $additional_class Additional CSS class to add to the widget.
	 * @param string|null $theme_override Optional theme override (light or dark).
	 */
	public static function render( string $default_styles = '', string $additional_class = '', ?string $theme_override = null ): void {
		if ( ! Settings::is_configured() ) {
			return;
		}

		$sitekey       = Settings::get_sitekey();
		$theme         = null !== $theme_override ? $theme_override : Settings::get_theme();
		$language      = Settings::get_language();
		$start_mode    = Settings::get_start_mode();
		$debug_mode    = Settings::is_debug_enabled();
		$custom_domain = Settings::get_custom_domain();
		$eu_isolation  = Settings::is_eu_isolation_enabled();
		$custom_styles = Settings::get_custom_styles();

		$effective_styles = ! empty( $custom_styles ) ? $custom_styles : $default_styles;

		$class_value = 'private-captcha';
		if ( ! empty( $additional_class ) ) {
			$class_value .= ' ' . esc_attr( $additional_class );
		}

		$attributes = array(
			'class="' . esc_attr( $class_value ) . '"',
			'data-solution-field="' . esc_attr( Client::FORM_FIELD ) . '"',
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

		$allowed_html = array(
			'div' => array(
				'class'                => array(),
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

		echo wp_kses( '<div ' . implode( ' ', $attributes ) . '></div>', $allowed_html );
	}
}
