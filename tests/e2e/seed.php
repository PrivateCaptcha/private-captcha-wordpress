<?php
/**
 * Provision shared CAPTCHA settings and the WPForms fixture via wp --user=admin eval-file.
 */

use PrivateCaptchaWP\Settings;

Settings::update_all_settings(
	array_merge(
		Settings::get_default_settings(),
		array(
			'api_key'                           => getenv( 'PC_API_KEY' ),
			'sitekey'                           => getenv( 'PC_SITEKEY' ),
			'wpforms_enable_wpforms'            => true,
			'contactform7_enable'               => true,
			'woocommerce_enable_checkout_guest' => true,
		)
	)
);

// Use WPForms' own form handler so repeated provisioning updates the same form.
// Source: https://plugins.svn.wordpress.org/wpforms-lite/tags/2.0.2.1/includes/class-form.php
$handler = wpforms()->obj( 'form' );
$form    = get_page_by_path( 'wpforms-e2e', OBJECT, 'wpforms' );
$form_id = $form ? $form->ID : $handler->add( 'WPForms e2e', array( 'post_name' => 'wpforms-e2e' ) );

if ( is_wp_error( $form_id ) || ! $form_id ) {
	WP_CLI::error( 'Could not create the WPForms fixture.' );
}

$saved = $handler->update(
	$form_id,
	array(
		'id'       => $form_id,
		'field_id' => 2,
		'fields'   => array(
			1 => array(
				'id'       => '1',
				'type'     => 'email',
				'label'    => 'Email',
				'required' => '1',
				'size'     => 'medium',
			),
		),
		'settings' => array(
			'form_title'             => 'WPForms e2e',
			'submit_text'            => 'Submit',
			'submit_text_processing' => 'Sending...',
			'ajax_submit'            => '1',
			'notification_enable'    => '0',
			'confirmations'          => array(
				1 => array(
					'type'           => 'message',
					'message'        => 'Thanks for contacting us! We will be in touch with you shortly.',
					'message_scroll' => '1',
				),
			),
		),
	)
);

if ( ! $saved ) {
	WP_CLI::error( 'Could not configure the WPForms fixture.' );
}

$page    = get_page_by_path( 'wpforms-e2e' );
$page_id = wp_insert_post(
	array(
		'ID'           => $page ? $page->ID : 0,
		'post_type'    => 'page',
		'post_status'  => 'publish',
		'post_name'    => 'wpforms-e2e',
		'post_title'   => 'WPForms e2e',
		'post_content' => '[wpforms id="' . $form_id . '"]',
	),
	true
);

if ( is_wp_error( $page_id ) ) {
	WP_CLI::error( $page_id->get_error_message() );
}

update_option( 'show_on_front', 'page' );
update_option( 'page_on_front', $page_id );
WP_CLI::success( 'WPForms fixture ready at http://localhost:18081 (admin / e2e-password).' );
