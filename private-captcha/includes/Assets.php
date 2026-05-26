<?php
/**
 * Assets management functionality for Private Captcha WordPress plugin.
 *
 * @package PrivateCaptchaWP
 */

declare(strict_types=1);

namespace PrivateCaptchaWP;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Assets management class
 */
class Assets {

	/**
	 * Enqueue Private Captcha widget script and styles.
	 *
	 * @param string $handle Script handle to use.
	 * @param string $custom_js Optional custom JavaScript code to add to setupPrivateCaptchaWP function.
	 * @param string $custom_css Optional custom CSS code to add to inline styles.
	 * @param string $button_selector Optional custom submit button CSS selector.
	 */
	public static function enqueue( string $handle = 'private-captcha-widget', string $custom_js = '', string $custom_css = '', string $button_selector = '' ): void {
		$script_domain = Settings::get_custom_domain();
		if ( empty( $script_domain ) ) {
			$script_domain = 'privatecaptcha.com';
		}

		if ( 0 === strpos( $script_domain, 'cdn.' ) ) {
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

		self::enqueue_styles( $custom_css );
		self::enqueue_inline_script( $handle, $custom_js, $button_selector );
	}

	/**
	 * Enqueue inline styles.
	 *
	 * @param string $custom_css Optional custom CSS code to add to inline styles.
	 */
	private static function enqueue_styles( string $custom_css = '' ): void {
		$base_css = '
            .private-captcha {
                margin: 1rem 0;
            }
            input[type="submit"]:disabled,
            button[type="submit"]:disabled {
                opacity: 0.7;
                cursor: not-allowed;
            }
        ';

		$custom_css_block = '';
		if ( ! empty( $custom_css ) ) {
			$custom_css_block = "\n" . trim( $custom_css );
		}

		$full_css = $base_css . $custom_css_block;

		wp_register_style( 'private-captcha-inline-style', false, array(), PRIVATE_CAPTCHA_VERSION );
		wp_enqueue_style( 'private-captcha-inline-style' );
		wp_add_inline_style( 'private-captcha-inline-style', trim( $full_css ) );
	}

	/**
	 * Enqueue inline JavaScript for form button management.
	 *
	 * @param string $handle Script handle to attach inline script to.
	 * @param string $custom_js Optional custom JavaScript code to add to setupPrivateCaptchaWP function.
	 * @param string $button_selector Optional custom CSS selector for submit button.
	 */
	private static function enqueue_inline_script( string $handle, string $custom_js = '', string $button_selector = '' ): void {
		$custom_js_block = '';
		if ( ! empty( $custom_js ) ) {
			$custom_js_block = "\n                " . trim( $custom_js ) . "\n";
		}
		$btn_selector_js = empty( $button_selector ) ? '"input[type=\"submit\"], button[type=\"submit\"]"' : '"' . trim( $button_selector ) . '"';

		$custom_js_full = '
        (function() {
            function pcReportErrorWP(error) {
                if (typeof console !== "undefined" && typeof console.error === "function") {
                    console.error("Private Captcha WP error:", error);
                }
            }

            function pcSafeExecuteWP(callback) {
                try {
                    return callback();
                } catch (error) {
                    pcReportErrorWP(error);
                    return null;
                }
            }

            function pcHasOwnPropertyWP(object, property) {
                return !!object && Object.prototype.hasOwnProperty.call(object, property);
            }

            function pcForEachWP(collection, callback) {
                pcSafeExecuteWP(function() {
                    Array.prototype.forEach.call(collection || [], callback);
                });
            }

            function pcQuerySelectorAllWP(parent, selector) {
                return pcSafeExecuteWP(function() {
                    if (!parent || typeof parent.querySelectorAll !== "function") {
                        return [];
                    }

                    return parent.querySelectorAll(selector) || [];
                }) || [];
            }

            function pcGetEventElementWP(event) {
                return event && event.detail ? event.detail.element : null;
            }

            function pcSetFormButtonEnabledWP(element, enabled, customSelector) {
                pcSafeExecuteWP(function() {
                    let container = element && typeof element.closest === "function" ? element.closest("form") : null;
                    if (!container && customSelector) { container = document; }
                    if (!container) return;

                    const selector = customSelector || "input[type=\"submit\"], button[type=\"submit\"]";
                    const submitButtons = pcQuerySelectorAllWP(container, selector);
                    pcForEachWP(submitButtons, function(btn) {
                        if (btn) {
                            btn.disabled = !enabled;
                        }
                    });
                });
            }

            function pcGetCaptchaWidgetsWP(parent) {
                return pcQuerySelectorAllWP(parent || document, ".private-captcha");
            }

            function pcHasCaptchaWidgetWP(parent) {
                const captchaWidgets = pcGetCaptchaWidgetsWP(parent);
                return captchaWidgets && (captchaWidgets.length > 0);
            }

            function pcResetCaptchaWidgetWP(parent) {
                let anyReset = false;

                if (parent) {
                    const elements = pcGetCaptchaWidgetsWP(parent);
                    pcForEachWP(elements, function(element) {
                        if (element && pcHasOwnPropertyWP(element, "_privateCaptcha") && element._privateCaptcha && typeof element._privateCaptcha.reset === "function") {
                            element._privateCaptcha.reset();
                            anyReset = true;
                        }
                    });
                }

                if (!anyReset && pcHasOwnPropertyWP(window, "privateCaptcha") && window.privateCaptcha) {
                    if (pcHasOwnPropertyWP(window.privateCaptcha, "autoWidget") && window.privateCaptcha.autoWidget && typeof window.privateCaptcha.autoWidget.reset === "function") {
                        window.privateCaptcha.autoWidget.reset();
                    }
                }
            }

            function setupPrivateCaptchaWP() {
                pcSafeExecuteWP(function() {
                    const submitBtnSelector = ' . $btn_selector_js . ';
                    const captchaWidgets = pcGetCaptchaWidgetsWP(document);

                    if (captchaWidgets && (captchaWidgets.length > 0)) {
                        pcForEachWP(pcQuerySelectorAllWP(document, submitBtnSelector), function(btn) {
                            if (btn) {
                                btn.disabled = true;
                            }
                        });
                        pcForEachWP(captchaWidgets, (e) => pcSetFormButtonEnabledWP(e, false, submitBtnSelector));

                        pcForEachWP(captchaWidgets, function(currentWidget) {
                            if (!currentWidget || typeof currentWidget.addEventListener !== "function") {
                                return;
                            }

                            currentWidget.addEventListener("privatecaptcha:init", (event) => pcSetFormButtonEnabledWP(pcGetEventElementWP(event), false, submitBtnSelector));
                            currentWidget.addEventListener("privatecaptcha:reset", (event) => pcSetFormButtonEnabledWP(pcGetEventElementWP(event), false, submitBtnSelector));
                            currentWidget.addEventListener("privatecaptcha:finish", (event) => pcSetFormButtonEnabledWP(pcGetEventElementWP(event), true, submitBtnSelector));
                        });
                    }' . $custom_js_block . '
                });
            }

            if (document.readyState === "loading") {
                document.addEventListener("DOMContentLoaded", setupPrivateCaptchaWP);
            } else {
                setupPrivateCaptchaWP();
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

		if ( 0 === strpos( $handle, 'private-captcha-' ) ) {
			return str_replace( '<script ', '<script defer ', $tag );
		}
		return $tag;
	}
}
