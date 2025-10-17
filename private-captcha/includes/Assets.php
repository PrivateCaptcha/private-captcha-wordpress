<?php
/**
 * Assets management functionality for Private Captcha WordPress plugin.
 *
 * @package PrivateCaptchaWP
 */

declare(strict_types=1);

namespace PrivateCaptchaWP;

/**
 * Assets management class
 */
class Assets {

	/**
	 * Enqueue Private Captcha widget script and styles.
	 *
	 * @param string $handle Script handle to use.
	 * @param string $custom_js Optional custom JavaScript code to add to setupPrivateCaptcha function.
	 */
	public static function enqueue( string $handle = 'private-captcha-widget', string $custom_js = '' ): void {
		$script_domain = Settings::get_custom_domain();
		if ( empty( $script_domain ) ) {
			$script_domain = 'privatecaptcha.com';
		}

		if ( str_starts_with( $script_domain, 'cdn.' ) ) {
			$script_domain = substr( $script_domain, 4 );
		}

		wp_enqueue_script(
			$handle,
			"https://cdn.{$script_domain}/widget/js/privatecaptcha.js",
			array(),
			PRIVATE_CAPTCHA_VERSION,
			true
		);

		add_filter( 'script_loader_tag', array( __CLASS__, 'add_defer_attribute' ), 10, 3 );

		self::enqueue_styles();
		self::enqueue_inline_script( $handle, $custom_js );
	}

	/**
	 * Enqueue inline styles.
	 */
	private static function enqueue_styles(): void {
		$custom_css = '
            .private-captcha {
                margin: 1rem 0;
            }
            input[type="submit"]:disabled,
            button[type="submit"]:disabled {
                opacity: 0.7;
                cursor: not-allowed;
            }
        ';
		wp_register_style( 'private-captcha-inline-style', false, array(), PRIVATE_CAPTCHA_VERSION );
		wp_enqueue_style( 'private-captcha-inline-style' );
		wp_add_inline_style( 'private-captcha-inline-style', trim( $custom_css ) );
	}

	/**
	 * Enqueue inline JavaScript for form button management.
	 *
	 * @param string $handle Script handle to attach inline script to.
	 * @param string $custom_js Optional custom JavaScript code to add to setupPrivateCaptcha function.
	 */
	private static function enqueue_inline_script( string $handle, string $custom_js = '' ): void {
		$custom_js_block = '';
		if ( ! empty( $custom_js ) ) {
			$custom_js_block = "\n                " . trim( $custom_js ) . "\n";
		}

		$custom_js_full = '
        (function() {
            function setFormButtonEnabled(captchaElement, enabled) {
                const form = captchaElement.closest("form");
                if (!form) return;
                const submitButton = form.querySelector("input[type=\"submit\"], button[type=\"submit\"]");
                if (submitButton) {
                    submitButton.disabled = !enabled;
                }
            }

            function resetCaptchaWidget(parent) {
                let anyReset = false;

                if (parent) {
                    const elements = parent.querySelectorAll(".private-captcha");
                    elements.forEach(function(element) {
                        if (element && element.hasOwnProperty("_privateCaptcha") && element._privateCaptcha) {
                            element._privateCaptcha.reset();
                            anyReset = true;
                        }
                    });
                }

                if (!anyReset && window.hasOwnProperty("privateCaptcha") && window.privateCaptcha) {
                    window.privateCaptcha.autoWidget.reset();
                }
            }

            function setupPrivateCaptcha() {
                document.querySelectorAll(".private-captcha").forEach((e) => setFormButtonEnabled(e, false));

                document.querySelectorAll(".private-captcha").forEach(function(currentWidget) {
                    currentWidget.addEventListener("privatecaptcha:init", (event) => setFormButtonEnabled(event.detail.element, false));
                    currentWidget.addEventListener("privatecaptcha:finish", (event) => setFormButtonEnabled(event.detail.element, true));
                });' . $custom_js_block . '
            }

            if (document.readyState === "loading") {
                document.addEventListener("DOMContentLoaded", setupPrivateCaptcha);
            } else {
                setupPrivateCaptcha();
            }
        })();
        ';
		wp_add_inline_script( $handle, trim( $custom_js_full ) );
	}

	/**
	 * Add defer attribute to the Private Captcha script.
	 *
	 * @param string $tag    The script tag.
	 * @param string $handle The script handle.
	 * @param string $src    The script source URL (unused).
	 * @return string Modified script tag.
	 */
	public static function add_defer_attribute( string $tag, string $handle, string $src ): string {
		unset( $src );

		if ( str_starts_with( $handle, 'private-captcha-' ) ) {
			return str_replace( '<script ', '<script defer ', $tag );
		}
		return $tag;
	}
}
