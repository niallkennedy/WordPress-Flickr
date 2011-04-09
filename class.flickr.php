<?php
/**
 * Flickr handlers in PHP class form
 */

/**
 * Information about an individual Flickr photo
 */
class FlickrPhoto {
	/**
	 * Flickr photo identifier
	 * var string
	 */
	public $id;

	/**
	 * Photo title
	 * @var string
	 */
	public $title;

	/**
	 * Photo description
	 */
	public $description;

	/**
	 * url, width, height
	 * @var array
	 */
	public $sizes;

	/**
	 * Flickr photo URI
	 * @var string
	 */
	public $url;

	/**
	 * Photo owner
	 * @var array
	 */
	public $owner;

	/**
	 * Create a new Flickr object and retrieve data from Flickr server
	 *
	 * @param string id Flickr photo identifier
	 */
	public function __construct( $id ) {
		$this->id = $id;

		$request = $this->load_info();
		if ( is_wp_error( $request ) )
			return $request;
		if ( empty( $request ) )
			return new WP_Error( 'flickrapifail', 'Unable to retrieve information from the Flickr API' );

		$this->load_sizes( $request );
		asort( $this->sizes );
	}

	/**
	 * Determine the best available size photo available based on the sizes array.
	 *
	 * @param int $width desired width
	 * @return int Flickr sizes width
	 */
	public function closest_size_match( $width ) {
		$last_width = 0;
		foreach( array_keys( $this->sizes ) as $flickr_width ) {
			if ( $flickr_width > $width ) {
				if ( $last_width !== 0 ) {
					$halfway = absint( ($flickr_width - $last_width) / 2 );
					// show the larger 
					if ( $width >= $halfway )
						return $flickr_width;
					else
						return $last_width;
				}
			}
			$last_width = $flickr_width;
		}

		/**
		 * Maximum available size
		 */
		return $last_width;
	}

	/**
	 * Express object as MediaRSS
	 *
	 * @link http://video.search.yahoo.com/mrss MediaRSS specification
	 * @return string MediaRSS markup. Group if multiple sizes.
	 */
	public function asMediaRSS() {
		if ( !isset( $this->sizes ) || !is_array( $this->sizes ) || empty( $this->sizes ) )
			return '';

		$xml = '';
		if ( !empty( $this->title ) )
			$xml .= '<media:title type="plain">' . esc_html( trim( strip_tags( $this->title ) ) ) . '</media:title>' . PHP_EOL;
		if ( !empty( $this->description ) )
			$xml .= '<media:description type="html">' . esc_html( $this->description ) . '</media:description>' . PHP_EOL;

		if ( !empty( $this->owner ) && is_array( $this->owner ) ) {
			$name = '';
			if ( isset( $this->owner['name'] ) && !empty( $this->owner['name'] ) )
				$name = $this->owner['name'];
			elseif ( isset( $this->owner['username'] ) && !empty( $this->owner['username'] ) )
				$name = $this->owner['username'];
			if ( !empty( $name ) )
				$xml .= '<media:credit role="photographer">' . esc_html( $name ) . '</media:credit>' . PHP_EOL;
			unset( $name );
		}

		$content = array();
		foreach( $this->sizes as $width=>$size ) {
			if ( !isset( $size['url'] ) )
				continue;
			$content[] = array( 'medium'=>'image', 'type'=>'image/jpeg', 'url'=>$size['url'], 'width'=>$width, 'height'=>$size['height'] );
		}
		if ( empty( $content ) )
			return '';
		$content_count = count( $content );
		if ( $content_count > 1 ) {
			$xml .= '<media:thumbnail url="' . esc_url( $content[0]['url'] ) . '" width="' . $content[0]['width'] . '" height="' . $content[0]['height'] . '" />' . PHP_EOL;
			$content[ $content_count - 1 ]['expression'] = 'full';
			$content[ $content_count - 1 ]['isDefault'] = 'true';
			$xml = '<media:group>' . PHP_EOL . $xml;
		}
		foreach( $content as $c ) {
			$xml .= '<media:content';
			foreach( $c as $key=>$value ) {
				$xml .= ' ' . $key . '="' . esc_attr( $value ) .  '"';
			}
			$xml .= '/>' . PHP_EOL;
		}
		if ( $content_count > 1 )
			$xml .= '</media:group>'. PHP_EOL;
		unset( $content_count );
		unset( $content );

		return $xml;
	}

	public function asImageSitemap( SimpleXMLElement $url_el ) {
		if ( empty( $url_el ) || empty( $this->sizes ) )
			return;

		$largest_image = array_pop( $this->sizes );
		if ( empty( $largest_image ) )
			return;

		$prefix = 'image';
		$image = $url_el->addChild( $prefix . ':image' );
		$image->addChild( $prefix . ':loc', $largest_image["url"] );
		if ( ! empty( $this->title ) )
			$image->addChild( $prefix . ':title', $this->title );
		if ( ! empty( $this->description ) )
			$image->addChild( $prefix . ':caption', $this->description );

		return $url_el;
	}

	/**
	 * Load information about the given photo from Flickr
	 *
	 * @param FlickrRequest $request existing Flickr request if you would like to reuse
	 * @return FlickrRequest Flickr request object for reuse
	 */
	private function load_info( $request=null ) {
		if ( is_null( $request ) )
			$request = new FlickrRequest();

		$photo = $request->get_photo_info( $this->id );
		if ( is_wp_error( $photo ) || empty( $photo ) )
			return $photo;

		if ( isset( $photo->owner ) ) {
			$owner = array( 'id'=>$photo->owner->nsid );
			if ( isset( $photo->owner->username ) )
				$owner['username'] = $photo->owner->username;
			if ( isset( $photo->owner->realname ) )
				$owner['name'] = $photo->owner->realname;
			$this->owner = $owner;
			unset( $owner );
		}

		if ( isset( $photo->title ) && isset( $photo->title->_content ) && !empty( $photo->title->_content ) )
			$this->title = trim( $photo->title->_content );

		if ( isset( $photo->description ) && isset( $photo->description->_content ) && !empty( $photo->description->_content ) )
			$this->description = trim( $photo->description->_content );

		if ( isset( $photo->urls ) && isset( $photo->urls->url ) && is_array( $photo->urls->url ) ) {
			foreach( $photo->urls->url as $url ) {
				if ( isset( $url->type ) && $url->type==='photopage' && isset( $url->_content ) ) {
					$this->url = esc_url_raw( $url->_content, array( 'http', 'https' ) );
					break;
				}
			}
		}
		unset( $photo );

		return $request;
	}

	/**
	 * Store multiple sizes available for the given photo identifier on Flickr
	 *
	 * @param FlickrRequest $request existing Flickr request if you would like to reuse
	 * @return FlickrRequest Flickr request object for reuse
	 */
	private function load_sizes( $request=null ) {
		if ( is_null( $request ) )
			$request = new FlickrRequest();

		$sizes = $request->get_photo_sizes( $this->id );
		if ( is_wp_error( $sizes ) || empty( $sizes ) )
			return $sizes;

		if ( isset( $sizes->size ) )
			$sizes = $sizes->size;
		else
			return '';

		$this->sizes = array();
		foreach( $sizes as $size ) {
			if ( isset( $size->media ) && $size->media==='photo' )
				$this->sizes[absint($size->width)] = array( 'height'=>absint($size->height), 'url'=>esc_url_raw( $size->source, array( 'http', 'https' ) ) );
		}
		unset( $sizes );

		return $request;
	}
}

class FlickrRequest {

	/**
	 * Flickr API endpoint
	 * @var string
	 */
	protected $api_server_uri = 'http://api.flickr.com/services/rest/';

	/**
	 * Flickr API key
	 * @var string
	 */
	protected $api_key;

	/**
	 * Response format. JSON only for now.
	 */
	protected $format='json';

	protected $request_param_extras = array( 'nojsoncallback'=>true );

	/**
	 * Setup a new request to Flickr
	 *
	 * @param string $api_key Flickr API key
	 * @param array $request_param_extras extra paramters you would like to include with each request
	 */
	public function __construct( $api_key = '', $request_param_extras=null ) {
		if ( !empty($api_key) )
			$this->api_key = $api_key;
		else
			$this->api_key = get_flickr_api_key();
		if ( empty($this->api_key) )
			return false;

		if ( is_array( $request_param_extras ) && !empty( $request_param_extras ) )
			$this->request_param_extras = array_merge( $this->request_param_extras, $request_param_extras );
	}

	/**
	 * WP HTTP customizations
	 */
	public static function request_args() {
		return array( 'redirection' => 1, 'httpversion' => '1.1', 'user-agent'=>'WordPress Flickr Plugin/0.1; ' . home_url(), 'decompress'=>false, 'compress'=>false, 'sslverify'=>false, 'timeout'=>30, 'headers'=>array( 'Accept'=>'application/json' ) );
	}

	/**
	 * Build an initial query parameters array from class variables
	 *
	 * @return array associative array suitable for http_build_query
	 */
	private function get_params_array() {
		$params = array( 'api_key' => $this->api_key, 'format' => $this->format );
		foreach ( $this->request_param_extras as $key=>$value ) {
			$params[$key] = $value;
		}
		return $params;
	}

	/**
	 * Retrieve information about a specific Flickr photo identifier from the Flickr API
	 *
	 * @link http://www.flickr.com/services/api/flickr.photos.getInfo.html Flickr getInfo API call
	 * @param string $photo_id Flickr photo identifier
	 * @return stdClass Flickr photo representation in the JSON response
	 */
	public function get_photo_info( $photo_id ) {
		if ( empty( $photo_id ) )
			return '';

		$params = $this->get_params_array();
		$params['method'] = 'flickr.photos.getInfo';
		$params['photo_id'] = $photo_id;
		$response = wp_remote_get( $this->api_server_uri . '?' . http_build_query( $params, null, '&' ), self::request_args() );

		return $this->handle_flickr_response( $response, 'photo' );
	}

	/**
	 * Retrieve all photos associated with a given Flickr identifier
	 *
	 * @link http://www.flickr.com/services/api/flickr.photos.getSizes.html Flickr getSizes API call
	 * @param string Flickr photo identifier
	 * @return stdClass Flickr sizes representation in the JSON response
	 */
	public function get_photo_sizes( $photo_id ) {
		if ( empty( $photo_id ) )
			return '';

		$params = $this->get_params_array();
		$params['method'] = 'flickr.photos.getSizes';
		$params['photo_id'] = $photo_id;
		$response = wp_remote_get( $this->api_server_uri . '?' . http_build_query( $params, null, '&' ), self::request_args() );

		return $this->handle_flickr_response( $response, 'sizes' );
	}

	/**
	 * Search Flickr for the latest N photos posted or taken by a given Flickr user
	 *
	 * @link http://www.flickr.com/services/api/flickr.photos.search.html Flickr search API
	 * @param string $user_id Flickr NSID
	 * @param int $lastn Maximum number of photos to include in the response. actual number of photos included in the response may be fewer than requested if user has fewer than requested photos available
	 * @param string $sortby sort by date the photo was taken ('taken') instead of the default sort by posted
	 * @param array $extras extra parameters to pass into the API call
	 * @return stdClass photos representation in the JSON response
	 */
	public function get_latest_photos_by_user( $user_id, $lastn=5, $sortby='posted', $extras=null ) {
		if ( empty($user_id) || $lastn < 1 || $lastn > 500 )
			return '';

		$params = $this->get_params_array();
		$params['method'] = 'flickr.photos.search';
		$params['user_id'] = $user_id;
		$params['media'] = 'photos';
		$params['content_type'] = 1; // photos, not screenshots
		$params['privacy_filter'] = 1; // public
		$params['per_page'] = absint( $lastn );
		if ( $sortby==='taken' )
			$params['sort'] = 'date-taken-desc';
		if ( !empty( $extras ) )
			$params['extras'] = implode( ',', $extras );

		$response = wp_remote_get( $this->api_server_uri . '?' . http_build_query( $params, null, '&' ), array('redirection'=>0, 'timeout'=>30, 'httpversion'=>'1.1' ) );
		return $this->handle_flickr_response( $response, 'photos' );
	}

	/**
	 * Validate a given Flickr API key by calling Flickr's echo method.
	 *
	 * @param string $api_key Flickr API key
	 * @return bool true if remote request successful and response matches expectations; else false
	 */
	public static function validate_api_key( $api_key ) {
		if ( empty($api_key) )
			return false;
		$response = wp_remote_get( 'http://api.flickr.com/services/rest/?' . http_build_query( array( 'method'=>'flickr.test.echo', 'api_key'=>$api_key, 'format'=>'json', 'nojsoncallback'=>'1' ), null, '&' ) );
		if ( is_wp_error( $response ) || empty( $response ) || wp_remote_retrieve_response_code($response) !== 200 )
			return false;

		$data = json_decode( wp_remote_retrieve_body( $response ) );
		// stat 'ok' would probably be enough, but let's be thorough
		if ( isset( $data->stat ) && $data->stat==='ok' && isset( $data->api_key ) && isset( $data->api_key->_content ) && $data->api_key->_content === $api_key )
			return true;

		return false;
	}

	/**
	 * Test for a valid WP_HTTP response and an expected key within.
	 *
	 * @param array $response WP_HTTP response
	 * @param string $key expected first-level child of root expected in the response. scopes the response to this value.
	 * @return stdClass children of the given key if key is a valid child of the root element
	 */
	private function handle_flickr_response( $response, $key ) {
		if ( is_wp_error( $response ) || empty( $response ) )
			return $response;

		if ( wp_remote_retrieve_response_code($response) !== 200 )
			return '';

		if ( $this->format === 'json' )
			$data = json_decode( wp_remote_retrieve_body( $response ) );
		else
			return '';

		if ( isset( $data->stat ) ) {
			if ( $data->stat === 'ok' && isset( $data->$key ) ) {
				return $data->$key;
			} elseif ( $data->stat === 'fail' ) {
				if ( isset( $data->code ) && isset( $data->message ) )
					return new WP_Error( $data->code, $data->message );
				else
					return new WP_Error( 'fail', __( 'Flickr request failed' , 'flickr' ) );
			}
		}
		return '';
	}
}
?>
