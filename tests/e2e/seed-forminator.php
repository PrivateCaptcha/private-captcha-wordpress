<?php
/**
 * Provision the Forminator fixture via wp --user=admin eval-file.
 */

// Source: https://plugins.svn.wordpress.org/forminator/tags/1.57.3/library/class-api.php
$form    = get_page_by_path( 'forminator-e2e', OBJECT, 'forminator_forms' );
$form_id = $form ? $form->ID : Forminator_API::add_form( 'Forminator e2e' );

if ( is_wp_error( $form_id ) || ! $form_id ) {
	WP_CLI::error( 'Could not create the Forminator fixture.' );
}

$saved = Forminator_API::update_form(
	$form_id,
	array(
		array(
			'wrapper_id' => 'wrapper-email',
			'fields'     => array(
				array(
					'element_id'  => 'email-1',
					'type'        => 'email',
					'cols'        => '12',
					'required'    => 'true',
					'field_label' => 'Email',
					'validation'  => true,
				),
			),
		),
	),
	array(
		'formName'             => 'Forminator e2e',
		'form-type'            => 'default',
		'enable-ajax'          => 'true',
		'validation-inline'    => true,
		'submission-behaviour' => 'behaviour-thankyou',
		'thankyou-message'     => 'Forminator e2e submission received.',
		'autoclose'            => false,
		'submitData'           => array( 'custom-submit-text' => 'Submit' ),
	),
	'publish',
	array() // Disable email notifications; validation and CAPTCHA verification still run.
);

if ( is_wp_error( $saved ) || ! $saved ) {
	WP_CLI::error( 'Could not configure the Forminator fixture.' );
}

$page    = get_page_by_path( 'forminator-e2e' );
$page_id = wp_insert_post(
	array(
		'ID'           => $page ? $page->ID : 0,
		'post_type'    => 'page',
		'post_status'  => 'publish',
		'post_name'    => 'forminator-e2e',
		'post_title'   => 'Forminator e2e',
		'post_content' => '[forminator_form id="' . $form_id . '"]',
	),
	true
);

if ( is_wp_error( $page_id ) ) {
	WP_CLI::error( $page_id->get_error_message() );
}

WP_CLI::success( 'Forminator fixture ready at http://localhost:18081/?pagename=forminator-e2e.' );
