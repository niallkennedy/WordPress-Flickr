<?php
/**
 * Customize Flickr plugin settings
 * Currently supports only Flickr API key.
 * @todo customize text before and after the linked image
 */

/**
 * Customize Flickr plugin
 *
 * @author Niall Kennedy
 * @since 0.1
 */
function flickr_settings() {

	// If you can upload a file you can set Flickr settings
	if ( !current_user_can('upload_files') )
		return;

	$page = 'media';
	$section = 'flickr';
	add_settings_section( $section, 'Flickr', 'flickr_settings_section_callback_function', $page );
	add_settings_field( 'flickr_api_key', sprintf( __( '%s API key' ), 'Flickr' ), 'flickr_settings_api_key_field_markup', $page, $section );
	register_setting( $page, 'flickr_api_key', 'flickr_settings_api_key_field_sanitize' );
}
add_action( 'admin_init', 'flickr_settings' );

/**
 * Explain the secton
 *
 * @author Niall Kennedy
 * @since 0.1
 */
function flickr_settings_section_callback_function() {
	echo '<p>' . __( 'Flickr requires a valid API key for programmatic access to its services.', 'flickr' ) . '</p>';
}

/**
 * Text input expecting 32-character alphanumeric
 *
 * @author Niall Kennedy
 * @since 0.1
 */
function flickr_settings_api_key_field_markup() {
	$field_id = 'flickr_api_key';
	$field_length = 32;
	$html = '<fieldset>';
	$html .= '<legend class="screen-reader-text"><span>' . __( 'Use of the Flickr <abbr title="Application Programming Interface">API</abbr> requires an <abbr>API</abbr> key associated with your Yahoo! account.' ) . '</span></legend>';
	$html .= '<div><input type="text" name="' . $field_id . '" id="' . $field_id . '" maxlength="' . $field_length .  '" size="' . $field_length . '" pattern="[a-z0-9]{' . $field_length . '}" value="' . esc_attr( get_option( 'flickr_api_key' ) ) . '" /></div>';
	$html .= '<label for="' . $field_id . '"><a rel="nofollow" href="http://www.flickr.com/services/api/keys/">' . __( 'Flickr <abbr title="Application Programming Interface">API</abbr> key' ) . '</label>';
	$html .= '</fieldset>';
	echo $html;
}

/**
 * Verify the provided Flickr API key by calling Flickr's API
 *
 * @author Niall Kennedy
 * @since 0.1
 * @param string $api_key Flickr API key input into the settings field
 * @return string $api_key Flickr API key, or blank if validation failed
 */
function flickr_settings_api_key_field_sanitize( $api_key ) {
	$api_key = trim( $api_key );
	if ( empty($api_key) || !ctype_alnum( $api_key ) )
		return '';

	if ( !class_exists( 'FlickrRequest' ) )
		require_once( dirname(__FILE__) . '/class.flickr.php' );

	if ( FlickrRequest::validate_api_key( $api_key ) === true ) {
		return $api_key;
	} else {
		add_settings_error( 'flickr_api_key', 'flickr-api-key-invalid', __( 'Invalid Flickr API key: Flickr rejected the key.' ) );
		return '';
	}
}
?>