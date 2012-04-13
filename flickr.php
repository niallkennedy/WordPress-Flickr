<?php
/**
 * @package flickr
 * @author Niall Kennedy
 * @version 0.1
 */
/*
 * Plugin Name: Flickr
 * Plugin URI: http://www.niallkennedy.com/
 * Description: Flickr photo data
 * Author: Niall Kennedy
 * Author URI: http://www.niallkennedy.com/
 * Version: 0.1
 */

/**
 * Centralize Flickr API key reference.
 * Pull from get_option, or set as static string for speed and multisite config.
 *
 * @author Niall Kennedy
 * @since 0.1
 * @return string Flickr API key
 */
function get_flickr_api_key() {
	return get_option( 'flickr_api_key' );
}

/**
 * Display a linked Flickr photo in place of a shortcode.
 * One required attribute: 'photo' with a Flickr photo id
 * One optional attribute: 'w' to explicitly specify a photo width. Defaults to content_width of your theme, or 400 if no content width provided
 *
 * @author Niall Kennedy
 * @since 0.1
 * @return string HTML markup
 */
function flickr_shortcode( $attr ) {
	global $content_width;

	if ( !is_array( $attr ) )
		return '';

	extract( shortcode_atts( array(
		'photo'=>'',
		'w'=>0
	), $attr ) );

	if ( empty($photo) )
		return '';
	else
		$photo = trim( $photo );

	$w = absint($w);
	if ( isset( $content_width ) && $content_width > 0 && ( $w < 20 || $w > $content_width ) )
		$w = $content_width;
	if ( $w < 20 )
		$w = 400;

	require_once( dirname(__FILE__) . '/class.flickr.php' );
	$flickr_photo = new FlickrPhoto( $photo );
	if ( is_wp_error( $flickr_photo ) )
		return '';

	$flickr_width = $flickr_photo->closest_size_match( $w );
	if ( $flickr_width === 0 )
		return '';
	else
		return flickr_shortcode_markup( $flickr_photo, $flickr_width, $w );
}


/**
 * HTML markup for Flickr
 *
 * @author Niall Kennedy
 * @since 0.1
 * @param FlickrPhoto $flickr_photo photo information
 * @param int $flickr_width matched width
 * @return string HTML markup
 */
function flickr_shortcode_markup( $flickr_photo, $flickr_width, $display_width ) {
	$photo = $flickr_photo->sizes[$flickr_width];
	if ( empty($photo) )
		return '';

	$display_height = absint( $display_width * ( $photo['height'] / $flickr_width ) );

	/**
	 * @todo allow other people to customize this later
	 */
	$before = '<div style="text-align:center">';
	$after = '</div>';

	if ( isset( $flickr_photo->url ) )
		$photo_markup = '<a href="' . esc_url( $flickr_photo->url ) . '">';
	else
		$photo_markup = '';

	$photo_markup .= '<img alt="' . esc_attr( $flickr_photo->title ) . '" src="' . esc_url( $photo['url'] ) . '" width="' . $display_width . '" height="' . $display_height . '" />';

	if ( isset( $flickr_photo->url ) )
		$photo_markup .= '</a>';

	return $before . $photo_markup . $after;
}


/**
 * A combination of get_shortcode_regex() and do_shortcode_tag() in wp-includes/shortcode.php
 * Focus on a single shortcode and extract values through shortcode_parse_atts without calling the shortcode handler(s)
 *
 * @author Niall Kennedy
 * @since 0.1
 * @see get_shortcode_regex()
 * @see do_shortcode_tag()
 * @param string $content the content
 * @return array shortcode attributes as they would be presented to the shortcode handler
 */
function find_all_flickr_shortcodes( $content ) {
	$r = preg_match_all( '/(.?)\[(flickr)\b(.*?)(?:(\/))?\](?:(.+?)\[\/\2\])?(.?)/s', $content, $matches, PREG_SET_ORDER );

	if ( $r === false || $r === 0 ) 
		return array();

	$flickrs = array();
	foreach ( $matches as $m ) {
		$flickrs[] = shortcode_parse_atts( $m[3] );
	}

	return $flickrs;
}


/**
 * MediaRSS representation of a single Flickr photo
 *
 * @author Niall Kennedy
 * @since 0.1
 * @param string $photo_id Flickr photo identifier
 * @return string XML string or empty string if no data
 */
function flickr_media_rss_single_photo( $photo_id ) {
	require_once( dirname(__FILE__) . '/class.flickr.php' );

	$photo = new FlickrPhoto( $photo_id );
	if ( is_wp_error( $photo ) )
		return '';
	else
		return $photo->asMediaRSS();
}


/**
 * Add Media RSS markup to a post with one or more Flickr photos
 *
 * @author Niall Kennedy
 * @since 0.1
 */
function flickr_media_rss() {
	foreach( find_all_flickr_shortcodes( get_the_content() ) as $attr ) {
		if ( !is_array( $attr ) )
			continue;

		extract( shortcode_atts( array('photo'=>''), $attr ) );
		if ( ! empty($photo) )
			echo flickr_media_rss_single_photo( $photo );
	}
}

/**
 * Include Media RSS namespace in the Atom feed
 *
 * @author Niall Kennedy
 * @since 0.1
 */
function flickr_media_rss_ns() {
	echo 'xmlns:media="http://search.yahoo.com/mrss/"';
}

/**
 * Add Google image sitemap elements to Sitemap XML
 *
 * @param array $namespaces existing prefix=>name pairs
 * @param string $type root element: either urlset for a list of urls or sitemapindex for a list of sitemaps
 */
function flickr_sitemap_xml_namespaces( array $namespaces, $type ) {
	if ( $type === 'urlset' )
		$namespaces['image'] = 'http://www.google.com/schemas/sitemap-image/1.1';
	return $namespaces;
}

/**
 * Describe a single Flickr photo in Google image sitemap XML
 *
 * @param string $photo_id Flickr photo identifier
 * @param SimpleXMLElement $url_el active URL element
 * @return SimpleXMLElement URL element with our data added in
 */
function flickr_sitemap_xml_single_photo( $photo_id, SimpleXMLElement $url_el ) {
	require_once( dirname(__FILE__) . '/class.flickr.php' );

	$photo = new FlickrPhoto( $photo_id );
	if ( ! is_wp_error( $photo ) )
		$url_el = $photo->asImageSitemap( $url_el );

	return $url_el;
}

/**
 * Scan post content for Flickr shortcodes. If found, add image sitemap entries for each.
 *
 * @param SimpleXMLElement $url_el single URL element from sitemap
 * @return SimpleXMLElement possibly modified URL element
 */
function flickr_sitemap_xml_url_element( SimpleXMLElement $url_el ) {
	foreach( find_all_flickr_shortcodes( get_the_content() ) as $attr ) {
		extract( shortcode_atts( array('photo'=>''), $attr ) );
		if ( ! empty($photo) )
			$url_el = flickr_sitemap_xml_single_photo( $photo, $url_el );
	}
	return $url_el;
}

/**
 * Do not check WordPress.org plugin repository for plugin updates. You won't find anything for this custom plugin.
 *
 * @param array $r merged explicit args and defaults from WP_Http->request()
 * @param string $url URI resource
 * @return array filtered version of $r with our plugin removed from the list if the request was an update check
 */
function flickr_remove_plugin_update_check( $r, $url ) {
	if ( strlen( $url ) < 45 || substr_compare( $url, 'http://api.wordpress.org/plugins/update-check', 0, 45 ) !== 0 || ! array_key_exists( 'plugins', $r['body'] ) )
		return $r; // not a plugin update request
	$plugins = maybe_unserialize( $r['body']['plugins'] );
	if ( ! empty( $plugins ) ) {
		$plugin_basename = plugin_basename( __FILE__ );
		unset( $plugins->plugins[ $plugin_basename ] );
		unset( $plugins->active[ array_search( $plugin_basename, $plugins->active ) ] );
		$r['body']['plugins'] = maybe_serialize( $plugins );
		unset( $plugin_basename );
	}
	return $r;
}

/* Break out actions by admin or user-facing
 */
if ( is_admin() ) {
	add_filter( 'http_request_args', 'flickr_remove_plugin_update_check', 5, 2 );
	require_once( dirname(__FILE__) . '/settings.php' );
} else {
	add_shortcode( 'flickr', 'flickr_shortcode' );
	add_action( 'rss2_item', 'flickr_media_rss' );
	add_action( 'atom_entry', 'flickr_media_rss' );
	add_action( 'rss2_ns', 'flickr_media_rss_ns' );
	add_action( 'atom_ns', 'flickr_media_rss_ns' );
	//add_filter( 'sitemap_xml_namespaces', 'flickr_sitemap_xml_namespaces', 1, 2 );
	//add_filter( 'sitemap_xml_url_element', 'flickr_sitemap_xml_url_element', 1, 1 );
}
?>