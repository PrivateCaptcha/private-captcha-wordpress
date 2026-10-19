<?php
/**
 * Review reminders for Private Captcha WordPress plugin.
 *
 * @package PrivateCaptchaWP
 */

declare(strict_types=1);

namespace PrivateCaptchaWP;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Show review reminders on the plugin settings page.
 */
class ReviewNotice {

	/**
	 * Form integrations manager.
	 *
	 * @var Integrations
	 */
	private Integrations $integrations;

	/**
	 * Register review notice hooks.
	 *
	 * @param Integrations $integrations Form integrations manager.
	 */
	public function __construct( Integrations $integrations ) {
		$this->integrations = $integrations;

		// Updates do not run activation hooks, so initialize existing installs too.
		add_action( 'admin_init', array( self::class, 'record_activation' ) );
		add_action( 'admin_notices', array( $this, 'show_notice' ) );
		add_action( 'admin_post_private_captcha_review_notice', array( $this, 'handle_action' ) );
	}

	/**
	 * Record the first known activation without resetting it on reactivation.
	 */
	public static function record_activation(): void {
		if ( false === get_option( 'private_captcha_first_activated_at' ) ) {
			add_option( 'private_captcha_first_activated_at', time(), '', false );
		}
	}

	/**
	 * Show a reminder when eligible and record it as asked, even if ignored.
	 */
	public function show_notice(): void {
		$screen = get_current_screen();
		if ( ! $screen || 'settings_page_private-captcha' !== $screen->base || ! current_user_can( 'manage_options' ) ) {
			return;
		}

		$now          = time();
		$activated_at = (int) get_option( 'private_captcha_first_activated_at', 0 );
		if ( ! $activated_at || $now <= $activated_at + 14 * DAY_IN_SECONDS ) {
			return;
		}

		if ( ! Settings::is_configured() || ! $this->has_enabled_protection() ) {
			return;
		}

		// User options are scoped to the current site as well as the administrator.
		$snoozed_until = (int) get_user_option( 'private_captcha_review_snoozed_until' );
		if ( $now < $snoozed_until ) {
			return;
		}

		$last_asked_at = (int) get_user_option( 'private_captcha_review_last_asked_at' );
		if ( $last_asked_at ) {
			// Never repeat a prompt within three days, even after a successful save.
			if ( $now < $last_asked_at + 3 * DAY_IN_SECONDS ) {
				return;
			}

			// A successful save can shorten the normal two-week reminder to three days.
			if ( $now < $last_asked_at + 14 * DAY_IN_SECONDS && ! $this->settings_saved_successfully() ) {
				return;
			}
		}

		update_user_option( get_current_user_id(), 'private_captcha_review_last_asked_at', $now );

		$action_url  = admin_url( 'admin-post.php?action=private_captcha_review_notice' );
		$review_url  = wp_nonce_url( add_query_arg( 'review_action', 'review', $action_url ), 'private_captcha_review_notice' );
		$dismiss_url = wp_nonce_url( add_query_arg( 'review_action', 'dismiss', $action_url ), 'private_captcha_review_notice' );
		?>
		<div class="notice notice-info is-dismissible">
			<p><?php esc_html_e( 'Thanks for using Private Captcha! Please consider leaving a review on WordPress.org to help others discover the plugin.', 'private-captcha' ); ?></p>
			<p>
				<a class="button button-secondary" href="<?php echo esc_url( $review_url ); ?>" target="_blank" rel="noopener noreferrer"><?php esc_html_e( 'Leave a review', 'private-captcha' ); ?></a>
				<a href="<?php echo esc_url( $dismiss_url ); ?>"><?php esc_html_e( "Don't ask again", 'private-captcha' ); ?></a>
			</p>
		</div>
		<?php
	}

	/**
	 * Snooze either explicit response for 365 days, including save reminders.
	 */
	public function handle_action(): void {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'You are not allowed to manage these settings.', 'private-captcha' ), '', array( 'response' => 403 ) );
		}

		check_admin_referer( 'private_captcha_review_notice' );

		$review_action = sanitize_key( wp_unslash( $_GET['review_action'] ?? '' ) );
		if ( ! in_array( $review_action, array( 'review', 'dismiss' ), true ) ) {
			wp_die( esc_html__( 'Invalid review action.', 'private-captcha' ), '', array( 'response' => 400 ) );
		}

		update_user_option( get_current_user_id(), 'private_captcha_review_snoozed_until', time() + 365 * DAY_IN_SECONDS );

		if ( 'review' === $review_action ) {
			add_filter(
				'allowed_redirect_hosts',
				/**
				 * Allow the fixed WordPress.org review destination for this request.
				 *
				 * @param string[] $hosts Allowed redirect hosts.
				 * @return string[]
				 */
				static function ( array $hosts ): array {
					$hosts[] = 'wordpress.org';
					return $hosts;
				}
			);
			wp_safe_redirect( 'https://wordpress.org/support/plugin/private-captcha/reviews/#new-post' );
		} else {
			wp_safe_redirect( admin_url( 'options-general.php?page=private-captcha' ) );
		}
		exit;
	}

	/**
	 * Check for at least one enabled protection whose integration is available.
	 *
	 * @return bool Whether any protection is enabled.
	 */
	private function has_enabled_protection(): bool {
		foreach ( $this->integrations->get_all_integrations() as $integration ) {
			if ( $integration->is_available() && $integration->has_enabled_settings() ) {
				return true;
			}
		}

		return false;
	}

	/**
	 * Use the same success message WordPress displays after saving settings.
	 * This also covers saving unchanged settings, which fires no update hook.
	 *
	 * @return bool Whether WordPress reported a successful settings save.
	 */
	private function settings_saved_successfully(): bool {
		foreach ( get_settings_errors( 'general' ) as $notice ) {
			if ( 'settings_updated' === $notice['code'] && in_array( $notice['type'], array( 'success', 'updated' ), true ) ) {
				return true;
			}
		}

		return false;
	}
}
