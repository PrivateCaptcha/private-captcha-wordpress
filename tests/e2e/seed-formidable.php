<?php
/**
 * Provision the Formidable Forms fixture via wp --user=admin eval-file.
 */

// Activation defers table creation; finish the plugin's normal migration before seeding.
// Source: https://plugins.svn.wordpress.org/formidable/tags/7.0/classes/controllers/FrmAppController.php
FrmAppController::api_install();

// Source: https://plugins.svn.wordpress.org/formidable/tags/7.0/classes/models/FrmForm.php
$form   = FrmForm::getOne( 'formidable-e2e' );
$values = array(
	'form_key'    => 'formidable-e2e',
	'name'        => 'Formidable e2e',
	'description' => '',
	'status'      => 'published',
	'options'     => array(
		'ajax_submit'  => 1,
		'custom_style' => 1,
		'js_validate'  => 0, // Exercise server-side field validation and CAPTCHA verification.
		'submit_value' => 'Submit',
		'success_msg'  => 'Formidable e2e submission received.',
		'show_form'    => 0,
	),
);

if ( $form ) {
	$form_id = $form->id;
	$saved   = FrmForm::update( $form_id, $values );
} else {
	$form_id = FrmForm::create( $values );
	$saved   = $form_id;
}

// Updates return zero when the fixture is already up to date.
if ( ! $form_id || false === $saved ) {
	WP_CLI::error( 'Could not save the Formidable Forms fixture.' );
}

// Use the plugin's field defaults, including its standard HTML and error messages.
// Source: https://plugins.svn.wordpress.org/formidable/tags/7.0/classes/models/FrmField.php
$field                = FrmField::getOne( 'formidable-e2e-email' );
$email                = FrmFieldsHelper::setup_new_vars( 'email', $form_id );
$email['field_key']   = 'formidable-e2e-email';
$email['name']        = 'Email';
$email['required']    = 1;
$email['field_order'] = 1;
$saved                = $field ? FrmField::update( $field->id, $email ) : FrmField::create( $email );

if ( false === $saved ) {
	WP_CLI::error( 'Could not save the Formidable Forms Email field.' );
}

// Disable email actions only; keep entry creation, validation and CAPTCHA verification.
FrmFormActionsController::get_form_actions( 'email' )->destroy( $form_id );

$page    = get_page_by_path( 'formidable-e2e' );
$page_id = wp_insert_post(
	array(
		'ID'           => $page ? $page->ID : 0,
		'post_type'    => 'page',
		'post_status'  => 'publish',
		'post_name'    => 'formidable-e2e',
		'post_title'   => 'Formidable e2e',
		'post_content' => '[formidable id="' . $form_id . '"]',
	),
	true
);

if ( is_wp_error( $page_id ) ) {
	WP_CLI::error( $page_id->get_error_message() );
}

WP_CLI::success( 'Formidable Forms fixture ready at http://localhost:18081/?pagename=formidable-e2e.' );
