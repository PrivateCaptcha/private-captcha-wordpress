<?php
/**
 * Provision the Contact Form 7 fixture via wp --user=admin eval-file.
 */

// Source: https://plugins.svn.wordpress.org/contact-form-7/tags/6.1.7/includes/contact-form.php
$existing = get_page_by_path( 'contact-form-7-e2e', OBJECT, 'wpcf7_contact_form' );
$form     = $existing
	? WPCF7_ContactForm::get_instance( $existing )
	: WPCF7_ContactForm::get_template( array( 'title' => 'Contact Form 7 e2e' ) );

$mail            = $form->prop( 'mail' );
$mail['subject'] = 'Contact Form 7 e2e';
$mail['body']    = 'Email: [your-email]';

$form->set_properties(
	array(
		'form'                => '<label>Email [email* your-email]</label>' . "\n[privatecaptcha]\n[submit \"Submit\"]",
		'mail'                => $mail,
		// Skip delivery only; validation and CAPTCHA verification still run.
		'additional_settings' => 'skip_mail: on',
	)
);
$form_id = $form->save();

if ( ! $form_id ) {
	WP_CLI::error( 'Could not save the Contact Form 7 fixture.' );
}

// Reload to obtain the hash-backed shortcode after the first save.
$shortcode = WPCF7_ContactForm::get_instance( $form_id )->shortcode();
$page      = get_page_by_path( 'contact-form-7-e2e' );
$page_id   = wp_insert_post(
	array(
		'ID'           => $page ? $page->ID : 0,
		'post_type'    => 'page',
		'post_status'  => 'publish',
		'post_name'    => 'contact-form-7-e2e',
		'post_title'   => 'Contact Form 7 e2e',
		'post_content' => $shortcode,
	),
	true
);

if ( is_wp_error( $page_id ) ) {
	WP_CLI::error( $page_id->get_error_message() );
}

WP_CLI::success( 'Contact Form 7 fixture ready at http://localhost:18081/?pagename=contact-form-7-e2e.' );
