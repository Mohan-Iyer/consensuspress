<?php
/**
 * DNA Header
 * File:         tests/bootstrap.php
 * Version:      1.5.0 (sanitize_title stub added; float→int cast fix in get_score_class call)
 * Purpose:      PHPUnit bootstrap: WordPress function stubs + plugin autoload
 * Author:       C-C (Session 02, Sprint 1) | Modified: C-C (Session 04, Sprint 2) | Modified: C-C (Session 05, Sprint 3) | Modified: D-C (Session 10, hotfix)
 * Spec:         sprint1_d1_d7.yaml D7, sprint2_d1_d7.yaml D6, sprint3_d1_d7.yaml D6
 * PHP Version:  7.4+
 * Dependencies: none (standalone stubs)
 * Reusable:     Yes — all test files
 */

// =============================================================================
// ENVIRONMENT CONSTANTS
// =============================================================================

define( 'ABSPATH', '/tmp/wp/' );
define( 'CONSENSUSPRESS_VERSION',    '1.2.0' );
define( 'CONSENSUSPRESS_PLUGIN_DIR', dirname( __DIR__ ) . '/plugin/consensuspress/' );
define( 'CONSENSUSPRESS_PLUGIN_URL', 'https://example.com/wp-content/plugins/consensuspress/' );
define( 'CONSENSUSPRESS_PLUGIN_FILE', CONSENSUSPRESS_PLUGIN_DIR . 'consensuspress.php' );

// =============================================================================
// LOAD PLUGIN SOURCE (transport interface, then classes)
// =============================================================================

require_once __DIR__ . '/../plugin/consensuspress/includes/interface-consensuspress-transport.php';
require_once __DIR__ . '/class-consensuspress-mock-transport.php';
require_once __DIR__ . '/../plugin/consensuspress/includes/class-consensuspress-api.php';
require_once __DIR__ . '/../plugin/consensuspress/includes/class-consensuspress-settings.php';
require_once __DIR__ . '/../plugin/consensuspress/includes/class-consensuspress-post-builder.php';
require_once __DIR__ . '/../plugin/consensuspress/includes/class-consensuspress-create.php';
require_once __DIR__ . '/../plugin/consensuspress/includes/class-consensuspress-async.php';
require_once __DIR__ . '/../plugin/consensuspress/includes/class-consensuspress-rescue.php';
require_once __DIR__ . '/../plugin/consensuspress/includes/class-consensuspress-usage.php';
require_once __DIR__ . '/../plugin/consensuspress/includes/class-consensuspress-meta-box.php';

// =============================================================================
// SPRINT 1 — WORDPRESS FUNCTION STUBS
// =============================================================================

if ( ! function_exists( 'plugin_dir_path' ) ) {
	function plugin_dir_path( string $file ): string {
		return dirname( $file ) . '/';
	}
}

if ( ! function_exists( 'plugin_dir_url' ) ) {
	function plugin_dir_url( string $file ): string {
		return 'https://example.com/wp-content/plugins/' . basename( dirname( $file ) ) . '/';
	}
}

if ( ! function_exists( 'add_action' ) ) {
	function add_action( string $hook, $callback, int $priority = 10, int $accepted_args = 1 ): void {
		// No-op in tests.
	}
}

if ( ! function_exists( 'add_filter' ) ) {
	function add_filter( string $hook, $callback, int $priority = 10, int $accepted_args = 1 ): void {
		// No-op in tests.
	}
}

if ( ! function_exists( 'register_activation_hook' ) ) {
	function register_activation_hook( string $file, callable $callback ): void {
		// No-op in tests.
	}
}

if ( ! function_exists( 'register_deactivation_hook' ) ) {
	function register_deactivation_hook( string $file, callable $callback ): void {
		// No-op in tests.
	}
}

if ( ! function_exists( 'sanitize_text_field' ) ) {
	function sanitize_text_field( $str ): string {
		return trim( strip_tags( (string) $str ) );
	}
}

if ( ! function_exists( 'sanitize_textarea_field' ) ) {
	function sanitize_textarea_field( $str ): string {
		return trim( strip_tags( (string) $str ) );
	}
}

if ( ! function_exists( 'esc_html' ) ) {
	function esc_html( string $text ): string {
		return htmlspecialchars( $text, ENT_QUOTES, 'UTF-8' );
	}
}

if ( ! function_exists( 'esc_html__' ) ) {
	function esc_html__( string $text, string $domain = 'default' ): string {
		return htmlspecialchars( $text, ENT_QUOTES, 'UTF-8' );
	}
}

if ( ! function_exists( 'esc_html_e' ) ) {
	function esc_html_e( string $text, string $domain = 'default' ): void {
		echo htmlspecialchars( $text, ENT_QUOTES, 'UTF-8' );
	}
}

if ( ! function_exists( 'esc_attr' ) ) {
	function esc_attr( string $text ): string {
		return htmlspecialchars( $text, ENT_QUOTES, 'UTF-8' );
	}
}

if ( ! function_exists( 'esc_url_raw' ) ) {
	function esc_url_raw( string $url ): string {
		return filter_var( $url, FILTER_SANITIZE_URL ) ?: '';
	}
}

if ( ! function_exists( 'esc_url' ) ) {
	function esc_url( string $url ): string {
		return filter_var( $url, FILTER_SANITIZE_URL ) ?: '';
	}
}

if ( ! function_exists( 'get_option' ) ) {
	// Global option store for tests.
	$GLOBALS['_cp_test_options'] = array(
		'consensuspress_api_key'     => 'test-key-sprint1-valid',
		'consensuspress_post_status' => 'draft',
	);

	function get_option( string $key, $default = false ) {
		// Bridge: usage tests seed _cp_usage_option directly (null = fresh install, use defaults).
		if ( 'consensuspress_usage' === $key && null !== ( $GLOBALS['_cp_usage_option'] ?? null ) ) {
			return $GLOBALS['_cp_usage_option'];
		}
		return $GLOBALS['_cp_test_options'][ $key ] ?? $default;
	}
}

if ( ! function_exists( 'add_option' ) ) {
	function add_option( string $key, $value ): bool {
		$GLOBALS['_cp_test_options'][ $key ] = $value;
		return true;
	}
}

if ( ! function_exists( 'update_option' ) ) {
	function update_option( string $key, $value ): bool {
		// Bridge: usage class updates consensuspress_usage — mirror to _cp_usage_option.
		if ( 'consensuspress_usage' === $key ) {
			$GLOBALS['_cp_usage_option'] = $value;
		}
		$GLOBALS['_cp_test_options'][ $key ] = $value;
		return true;
	}
}

if ( ! function_exists( 'is_wp_error' ) ) {
	function is_wp_error( $thing ): bool {
		return ( $thing instanceof WP_Error );
	}
}

if ( ! class_exists( 'WP_Error' ) ) {
	class WP_Error {
		/** @var string */
		private $code;
		/** @var string */
		private $message;

		public function __construct( string $code = '', string $message = '' ) {
			$this->code    = $code;
			$this->message = $message;
		}

		public function get_error_message(): string {
			return $this->message;
		}

		public function get_error_code(): string {
			return $this->code;
		}
	}
}

if ( ! function_exists( 'admin_url' ) ) {
	function admin_url( string $path = '' ): string {
		return 'https://example.com/wp-admin/' . ltrim( $path, '/' );
	}
}

if ( ! function_exists( 'wp_enqueue_script' ) ) {
	function wp_enqueue_script( string $handle, string $src = '', array $deps = array(), $ver = false, bool $in_footer = false ): void {
		// No-op in tests.
	}
}

if ( ! function_exists( 'wp_enqueue_style' ) ) {
	function wp_enqueue_style( string $handle, string $src = '', array $deps = array(), $ver = false, string $media = 'all' ): void {
		// No-op in tests.
	}
}

if ( ! function_exists( 'wp_localize_script' ) ) {
	function wp_localize_script( string $handle, string $object_name, array $l10n ): bool {
		return true;
	}
}

if ( ! function_exists( 'wp_create_nonce' ) ) {
	function wp_create_nonce( $action = -1 ): string {
		return 'test-nonce-' . md5( $action );
	}
}

if ( ! function_exists( 'check_ajax_referer' ) ) {
	// Override in individual test setUp() for nonce failure scenarios.
	function check_ajax_referer( string $action, $query_arg = false, bool $die = true ): int {
		return 1;
	}
}

if ( ! function_exists( 'current_user_can' ) ) {
	// Default: user has all capabilities. Override per test as needed.
	function current_user_can( string $capability ): bool {
		return $GLOBALS['_cp_test_user_can'] ?? true;
	}
}

if ( ! function_exists( 'wp_send_json_success' ) ) {
	// Store response in global for test assertion.
	function wp_send_json_success( $data = null ): void {
		$GLOBALS['_cp_json_response'] = array( 'success' => true, 'data' => $data );
	}
}

if ( ! function_exists( 'wp_send_json_error' ) ) {
	function wp_send_json_error( $data = null ): void {
		$GLOBALS['_cp_json_response'] = array( 'success' => false, 'data' => $data );
	}
}

if ( ! function_exists( 'wp_unslash' ) ) {
	function wp_unslash( $value ) {
		return is_string( $value ) ? stripslashes( $value ) : $value;
	}
}

if ( ! function_exists( '__' ) ) {
	function __( string $text, string $domain = 'default' ): string {
		return $text;
	}
}

if ( ! function_exists( 'add_menu_page' ) ) {
	function add_menu_page( ...$args ): void {
		// No-op in tests.
	}
}

if ( ! function_exists( 'register_setting' ) ) {
	function register_setting( string $group, string $option_name, array $args = array() ): void {
		// No-op in tests.
	}
}

if ( ! function_exists( 'add_settings_section' ) ) {
	function add_settings_section( ...$args ): void {
		// No-op in tests.
	}
}

if ( ! function_exists( 'add_settings_field' ) ) {
	function add_settings_field( ...$args ): void {
		// No-op in tests.
	}
}

// =============================================================================
// SPRINT 2 — NEW WORDPRESS FUNCTION STUBS (14 stubs, D6 spec)
// =============================================================================

if ( ! function_exists( 'wp_insert_post' ) ) {
	// Returns incrementing post IDs. Tracks calls.
	function wp_insert_post( array $args, bool $wp_error = false ) {
		static $post_id = 0;
		$post_id++;
		$GLOBALS['_cp_wp_insert_post_calls'][] = $args;
		$GLOBALS['_cp_last_post_id']            = $post_id;
		return $post_id;
	}
}

if ( ! function_exists( 'update_post_meta' ) ) {
	// Tracks all calls for assertion in tests.
	function update_post_meta( int $post_id, string $meta_key, $meta_value, $prev_value = '' ): bool {
		$GLOBALS['_cp_post_meta'][ $post_id ][ $meta_key ] = $meta_value;
		return true;
	}
}

if ( ! function_exists( 'get_post_meta' ) ) {
	function get_post_meta( int $post_id, string $key = '', bool $single = false ) {
		if ( isset( $GLOBALS['_cp_post_meta_override'][ $post_id ][ $key ] ) ) {
			return $GLOBALS['_cp_post_meta_override'][ $post_id ][ $key ];
		}
		$meta = $GLOBALS['_cp_post_meta'][ $post_id ][ $key ] ?? ( $single ? '' : array() );
		return $meta;
	}
}

if ( ! function_exists( 'set_post_thumbnail' ) ) {
	function set_post_thumbnail( int $post_id, int $thumbnail_id ): bool {
		$GLOBALS['_cp_thumbnails'][ $post_id ] = $thumbnail_id;
		return true;
	}
}

if ( ! function_exists( 'media_sideload_image' ) ) {
	// Default: returns a valid <img> HTML string. Override in tests for failure.
	function media_sideload_image( string $file, int $post_id, string $desc = '', string $return = 'html' ) {
		if ( $GLOBALS['_cp_media_sideload_fail'] ?? false ) {
			return new WP_Error( 'upload_error', 'Sideload failed.' );
		}
		$GLOBALS['_cp_media_sideload_calls'][] = array(
			'file'    => $file,
			'post_id' => $post_id,
			'desc'    => $desc,
		);
		return '<img src="https://example.com/wp-content/uploads/test.jpg" />';
	}
}

if ( ! function_exists( 'get_cat_ID' ) ) {
	// Returns 0 (not found) by default. Override via $GLOBALS['_cp_cat_map'].
	function get_cat_ID( string $cat_name ): int {
		return (int) ( $GLOBALS['_cp_cat_map'][ $cat_name ] ?? 0 );
	}
}

if ( ! function_exists( 'wp_create_category' ) ) {
	// Returns an incrementing category ID.
	function wp_create_category( string $cat_name, int $parent = 0 ): int {
		static $cat_id = 100;
		$cat_id++;
		$GLOBALS['_cp_cat_map'][ $cat_name ] = $cat_id;
		return $cat_id;
	}
}

if ( ! function_exists( 'wp_set_post_categories' ) ) {
	function wp_set_post_categories( int $post_id, array $post_categories = array(), bool $append = false ) {
		$GLOBALS['_cp_post_categories'][ $post_id ] = $post_categories;
		return true;
	}
}

if ( ! function_exists( 'wp_set_post_tags' ) ) {
	function wp_set_post_tags( int $post_id, $tags = '', bool $append = false ) {
		$GLOBALS['_cp_post_tags'][ $post_id ] = is_array( $tags ) ? $tags : array( $tags );
		return true;
	}
}

if ( ! function_exists( 'wp_kses_post' ) ) {
	// In tests: strip only <script> tags to validate sanitisation tests.
	function wp_kses_post( string $content ): string {
		return preg_replace( '#<script[^>]*>.*?</script>#is', '', $content );
	}
}

if ( ! function_exists( 'current_time' ) ) {
	function current_time( string $type ): string {
		return '2026-03-01 09:00:00';
	}
}

if ( ! function_exists( 'wp_json_encode' ) ) {
	function wp_json_encode( $data, int $options = 0, int $depth = 512 ) {
		return json_encode( $data, $options, $depth );
	}
}

if ( ! function_exists( 'get_edit_post_link' ) ) {
	function get_edit_post_link( int $post_id, string $context = 'display' ): string {
		return '/wp-admin/post.php?post=' . $post_id . '&action=edit';
	}
}

if ( ! function_exists( 'add_submenu_page' ) ) {
	function add_submenu_page( ...$args ): void {
		// No-op in tests.
	}
}

if ( ! function_exists( 'wp_die' ) ) {
	function wp_die( $message = '', $title = '', $args = array() ): void {
		throw new \RuntimeException( is_string( $message ) ? $message : 'wp_die called.' );
	}
}

// =============================================================================
// SPRINT 3 — NEW WORDPRESS FUNCTION STUBS (D6 spec)
// =============================================================================

if ( ! function_exists( 'set_transient' ) ) {
	function set_transient( string $transient, $value, int $expiration = 0 ): bool {
		$GLOBALS['_cp_transients'][ $transient ] = $value;
		return true;
	}
}

if ( ! function_exists( 'get_transient' ) ) {
	function get_transient( string $transient ) {
		return $GLOBALS['_cp_transients'][ $transient ] ?? false;
	}
}

if ( ! function_exists( 'delete_transient' ) ) {
	function delete_transient( string $transient ): bool {
		unset( $GLOBALS['_cp_transients'][ $transient ] );
		return true;
	}
}

if ( ! function_exists( 'wp_schedule_single_event' ) ) {
	function wp_schedule_single_event( int $timestamp, string $hook, array $args = array() ): bool {
		$GLOBALS['_cp_scheduled_cron_events'][] = array(
			'timestamp' => $timestamp,
			'hook'      => $hook,
			'args'      => $args,
		);
		return true;
	}
}

if ( ! function_exists( 'wp_generate_uuid4' ) ) {
	function wp_generate_uuid4(): string {
		return 'test-uuid-' . uniqid( '', true );
	}
}

if ( ! function_exists( 'site_url' ) ) {
	function site_url( string $path = '' ): string {
		return 'https://example.com/' . ltrim( $path, '/' );
	}
}

if ( ! function_exists( 'apply_filters' ) ) {
	function apply_filters( string $tag, $value, ...$args ) {
		return $value;
	}
}

if ( ! function_exists( 'wp_remote_post' ) ) {
	/**
	 * Mock wp_remote_post.
	 *
	 * For API endpoint calls: returns _cp_mock_api_response (JSON body).
	 * For spawn_cron loopback calls: returns 200 no-op.
	 */
	function wp_remote_post( string $url, array $args = array() ) {
		$GLOBALS['_cp_remote_post_calls'][] = array( 'url' => $url, 'args' => $args );

		// Detect Seekrates API endpoint.
		if ( false !== strpos( $url, 'seekrates-ai.com' ) ) {
			$mock = $GLOBALS['_cp_mock_api_response'] ?? null;
			if ( $mock ) {
				return array(
					'response' => array( 'code' => (int) ( $mock['http_status'] ?? 200 ) ),
					'body'     => json_encode( $mock['_raw_body'] ?? array() ),
				);
			}
		}

		// Default: loopback / unknown — return 200 empty body.
		return array( 'response' => array( 'code' => 200 ), 'body' => '{}' );
	}
}

if ( ! function_exists( 'sleep' ) ) {
	// Stub sleep() — no-op in tests to prevent delays during retry logic.
	function sleep( int $seconds ): int {
		return 0;
	}
}

// =============================================================================
// SPRINT 8 — wp_remote_get stub (Unsplash API calls)
// =============================================================================

if ( ! function_exists( 'add_query_arg' ) ) {
	/**
	 * Stub add_query_arg — appends key/value pairs to a URL as query string.
	 *
	 * @param array  $args Key-value pairs to add.
	 * @param string $url  Base URL.
	 * @return string URL with appended query string.
	 */
	function add_query_arg( array $args, string $url ): string {
		$query = http_build_query( $args, '', '&' );
		$sep   = ( false === strpos( $url, '?' ) ) ? '?' : '&';
		return $url . $sep . $query;
	}
}

if ( ! function_exists( 'wp_remote_get' ) ) {
	/**
	 * Mock wp_remote_get for Unsplash API calls.
	 *
	 * Default: returns a valid Unsplash photo JSON response.
	 * Set $GLOBALS['_cp_remote_get_fail'] = true to simulate WP_Error.
	 * Set $GLOBALS['_cp_remote_get_status'] = int to override HTTP status code.
	 * Set $GLOBALS['_cp_remote_get_body'] = string to override response body.
	 *
	 * @param string $url  Request URL.
	 * @param array  $args Request arguments.
	 * @return array|WP_Error
	 */
	function wp_remote_get( string $url, array $args = array() ) {
		$GLOBALS['_cp_remote_get_calls'][] = array( 'url' => $url, 'args' => $args );

		if ( $GLOBALS['_cp_remote_get_fail'] ?? false ) {
			return new WP_Error( 'http_request_failed', 'Simulated wp_remote_get failure.' );
		}

		$status = $GLOBALS['_cp_remote_get_status'] ?? 200;
		$body   = $GLOBALS['_cp_remote_get_body'] ?? json_encode( array(
			'urls'            => array( 'regular' => 'https://images.unsplash.com/photo-test' ),
			'alt_description' => 'A test photo description',
			'user'            => array(
				'name'  => 'Test Photographer',
				'links' => array( 'html' => 'https://unsplash.com/@testphotographer' ),
			),
		) );

		return array(
			'response' => array( 'code' => (int) $status ),
			'body'     => $body,
		);
	}
}

if ( ! function_exists( 'add_meta_box' ) ) {
	function add_meta_box( string $id, string $title, callable $cb, $screen = null, string $context = 'advanced', string $priority = 'default' ): void {
		// No-op in tests.
	}
}

// WP_Post stub class for meta box tests.
if ( ! class_exists( 'WP_Post' ) ) {
	class WP_Post {
		/** @var int */
		public $ID = 0;
		/** @var string */
		public $post_title = '';
		/** @var string */
		public $post_status = 'draft';
		/** @var string */
		public $post_content = '';
		/** @var string */
		public $post_excerpt = '';
		/** @var string */
		public $post_type = 'post';

		/**
		 * @param int    $id    Post ID.
		 * @param string $title Post title.
		 */
		public function __construct( int $id = 0, string $title = '' ) {
			$this->ID         = $id;
			$this->post_title = $title;
		}
	}
}

// =============================================================================
// TEST HELPER: Reset global state between tests
// =============================================================================

/**
 * Reset all test globals between test cases.
 *
 * Call in setUp() for isolation.
 *
 * @return void
 */
function cp_test_reset_globals(): void {
	$GLOBALS['_cp_json_response']          = null;
	$GLOBALS['_cp_post_meta']              = array();
	$GLOBALS['_cp_post_meta_override']     = array();
	$GLOBALS['_cp_thumbnails']             = array();
	$GLOBALS['_cp_post_categories']        = array();
	$GLOBALS['_cp_post_tags']              = array();
	$GLOBALS['_cp_media_sideload_calls']   = array();
	$GLOBALS['_cp_media_sideload_fail']    = false;
	$GLOBALS['_cp_wp_insert_post_calls']   = array();
	$GLOBALS['_cp_last_post_id']           = 0;
	$GLOBALS['_cp_cat_map']               = array();
	$GLOBALS['_cp_test_user_can']          = true;
	$GLOBALS['_cp_test_user_id']           = 1;
	$GLOBALS['_cp_transients']             = array();
	$GLOBALS['_cp_scheduled_cron_events']  = array();
	$GLOBALS['_cp_remote_post_calls']      = array();
	$GLOBALS['_cp_mock_api_response']      = null;
	$GLOBALS['_cp_remote_get_calls']       = array();
	$GLOBALS['_cp_remote_get_fail']        = false;
	$GLOBALS['_cp_remote_get_status']      = 200;
	$GLOBALS['_cp_remote_get_body']        = null;
	$GLOBALS['_cp_term_map']               = array();
	$GLOBALS['_cp_post_override']          = array();   // get_post() — keyed by post ID
	$GLOBALS['_cp_mock_posts']             = array();   // get_posts() — flat array of WP_Post
	$GLOBALS['_cp_usage_option']           = null;      // ConsensusPress_Usage state — null = fresh install defaults
	$GLOBALS['_cp_current_screen']         = null;      // get_current_screen() stub
	$GLOBALS['_cp_test_options']           = array(
		'consensuspress_api_key'     => 'test-key-sprint3-valid',
		'consensuspress_post_status' => 'draft',
		'date_format'                => 'Y-m-d H:i:s',
	);
}

// =============================================================================
// SPRINT 7 — NEW WORDPRESS FUNCTION STUBS
// wp_remote_retrieve_response_code: used by class-consensuspress-api.php v1.1.0
// get_bloginfo: used by class-consensuspress-post-builder.php v2.0.0 (article schema)
// home_url: used by class-consensuspress-post-builder.php v2.0.0 (internal links)
// =============================================================================

if ( ! function_exists( 'wp_remote_retrieve_response_code' ) ) {
	/**
	 * Retrieve the HTTP status code from a wp_remote_post() response array.
	 *
	 * @param array $response Response from wp_remote_post().
	 * @return int HTTP status code, or 0 if not present.
	 */
	function wp_remote_retrieve_response_code( $response ): int {
		return isset( $response['response']['code'] ) ? (int) $response['response']['code'] : 0;
	}
}

if ( ! function_exists( 'wp_remote_retrieve_body' ) ) {
	/**
	 * Retrieve the body from a wp_remote_post() response array.
	 *
	 * @param array $response Response from wp_remote_post().
	 * @return string Response body or empty string.
	 */
	function wp_remote_retrieve_body( $response ): string {
		return isset( $response['body'] ) ? (string) $response['body'] : '';
	}
}

if ( ! function_exists( 'get_current_user_id' ) ) {
	/**
	 * Stub for get_current_user_id() — returns deterministic test user ID.
	 *
	 * @return int
	 */
	function get_current_user_id(): int {
		return $GLOBALS['_cp_test_user_id'] ?? 1;
	}
}

if ( ! function_exists( 'get_bloginfo' ) ) {
	/**
	 * Stub for get_bloginfo() — returns deterministic test values.
	 *
	 * @param string $show   The type of info to retrieve.
	 * @param string $filter Optional filter.
	 * @return string
	 */
	function get_bloginfo( string $show = '', string $filter = 'raw' ): string {
		$info = array(
			'name'        => 'Test Site',
			'description' => 'Test Description',
			'url'         => 'https://example.com',
			'wpurl'       => 'https://example.com',
			'charset'     => 'UTF-8',
			'language'    => 'en-US',
			'version'     => '6.4',
		);
		return $info[ $show ] ?? 'Test Site';
	}
}

if ( ! function_exists( 'home_url' ) ) {
	/**
	 * Stub for home_url() — used by post-builder v2.0.0 for internal links.
	 *
	 * @param string $path Optional path to append.
	 * @return string
	 */
	function home_url( string $path = '' ): string {
		return 'https://example.com' . ( $path ? '/' . ltrim( $path, '/' ) : '' );
	}
}

if ( ! function_exists( 'wp_insert_term' ) ) {
	/**
	 * Stub for wp_insert_term() — insert a term into a taxonomy.
	 * Returns a fake term array. Stores in _cp_term_map by slug for get_term_by() to find.
	 *
	 * @param string $term     Term name.
	 * @param string $taxonomy Taxonomy slug.
	 * @param array  $args     Optional args (slug, parent, description).
	 * @return array{term_id: int, term_taxonomy_id: int}
	 */
	function wp_insert_term( string $term, string $taxonomy, array $args = array() ) {
		static $term_id = 2000;
		$term_id++;
		$slug = isset( $args['slug'] ) ? $args['slug'] : sanitize_title( $term );
		$term_obj = (object) array(
			'term_id'  => $term_id,
			'name'     => $term,
			'slug'     => $slug,
			'taxonomy' => $taxonomy,
		);
		$GLOBALS['_cp_term_map'][ $slug ] = $term_obj;
		$GLOBALS['_cp_term_map'][ $term ] = $term_obj;
		return array( 'term_id' => $term_id, 'term_taxonomy_id' => $term_id );
	}
}

if ( ! function_exists( 'strip_shortcodes' ) ) {
	/**
	 * Stub for strip_shortcodes() — remove WordPress shortcode tags from content.
	 * Simple regex — matches [shortcode] and [shortcode attr="val"] patterns.
	 *
	 * @param string $content Content potentially containing shortcodes.
	 * @return string Content with shortcode tags removed.
	 */
	function strip_shortcodes( string $content ): string {
		return preg_replace( '/\[[\w\/][^\]]*\]/', '', $content );
	}
}

if ( ! function_exists( 'get_current_screen' ) ) {
	/**
	 * Stub for get_current_screen() — returns null outside WP admin context.
	 * ConsensusPress_Usage::render_quota_notice() guards on this — returning
	 * null causes early return, which is the correct test-environment behaviour.
	 *
	 * @return null
	 */
	function get_current_screen(): ?object {
		return $GLOBALS['_cp_current_screen'] ?? null;
	}
}

if ( ! function_exists( 'absint' ) ) {
	/**
	 * Stub for absint() — convert value to non-negative integer.
	 *
	 * @param mixed $maybeint Data to convert.
	 * @return int Non-negative integer.
	 */
	function absint( $maybeint ): int {
		return abs( (int) $maybeint );
	}
}

if ( ! function_exists( 'sanitize_title' ) ) {
	/**
	 * Stub for sanitize_title() — convert string to URL-friendly slug.
	 * Mirrors WordPress core behaviour: lowercase, hyphens, strip non-alphanum.
	 *
	 * @param string $title     The string to sanitize.
	 * @param string $fallback  Fallback if result is empty.
	 * @param string $context   'save' | 'display' | 'query'.
	 * @return string Sanitized slug.
	 */
	function sanitize_title( string $title, string $fallback = '', string $context = 'save' ): string {
		$title = strtolower( $title );
		$title = preg_replace( '/[^a-z0-9\s\-]/', '', $title );
		$title = preg_replace( '/[\s\-]+/', '-', trim( $title ) );
		return $title !== '' ? $title : $fallback;
	}
}

if ( ! function_exists( 'date_i18n' ) ) {
	/**
	 * Stub for date_i18n() — locale-aware date formatting.
	 * In tests: delegates to PHP date() using UTC.
	 *
	 * @param string   $format    PHP date format string.
	 * @param int|bool $timestamp Optional Unix timestamp.
	 * @return string Formatted date.
	 */
	function date_i18n( string $format, $timestamp = false ): string {
		return date( $format, $timestamp ?: time() );
	}
}

if ( ! function_exists( 'get_term_by' ) ) {
	/**
	 * Stub for get_term_by() — taxonomy term lookup.
	 * Default: returns false (term not found).
	 * Override via $GLOBALS['_cp_term_map'][ $value ] = WP_Term_stub.
	 *
	 * @param string     $field    'slug' | 'name' | 'id' | 'term_taxonomy_id'
	 * @param string|int $value    Search value.
	 * @param string     $taxonomy Taxonomy name.
	 * @return object|false WP_Term-like object or false.
	 */
	function get_term_by( string $field, $value, string $taxonomy = '' ) {
		return $GLOBALS['_cp_term_map'][ $value ] ?? false;
	}
}

if ( ! function_exists( 'get_post' ) ) {
	/**
	 * Stub for get_post() — retrieve post by ID.
	 * Returns value from $GLOBALS['_cp_post_store'][ $post_id ] or null.
	 *
	 * @param int|null $post    Post ID.
	 * @param string   $output  OBJECT | ARRAY_A | ARRAY_N.
	 * @param string   $filter  Filter type.
	 * @return WP_Post|null
	 */
	function get_post( $post = null, string $output = 'OBJECT', string $filter = 'raw' ): ?WP_Post {
		if ( $post instanceof WP_Post ) {
			return $post;
		}
		return $GLOBALS['_cp_post_override'][ (int) $post ] ?? null;
	}
}

if ( ! function_exists( 'get_posts' ) ) {
	/**
	 * Stub for get_posts() — retrieve list of posts.
	 * Returns value from $GLOBALS['_cp_mock_posts'] or empty array.
	 *
	 * @param array $args Query arguments.
	 * @return WP_Post[] Array of post objects.
	 */
	function get_posts( array $args = array() ): array {
		return $GLOBALS['_cp_mock_posts'] ?? array();
	}
}

if ( ! function_exists( 'get_post_field' ) ) {
	/**
	 * Stub for get_post_field() — retrieve a post field by post ID.
	 * Returns value from _cp_post_override if available, otherwise empty string.
	 *
	 * @param string   $field   Post field name (e.g. 'post_content').
	 * @param int      $post_id Post ID.
	 * @param string   $context Context — 'raw' | 'edit' | 'display'.
	 * @return string  Field value or empty string.
	 */
	function get_post_field( string $field, int $post_id, string $context = 'display' ): string {
		$post = $GLOBALS['_cp_post_override'][ $post_id ] ?? null;
		if ( $post && isset( $post->$field ) ) {
			return (string) $post->$field;
		}
		return '';
	}
}

if ( ! function_exists( 'get_post_thumbnail_id' ) ) {
	/**
	 * Stub for get_post_thumbnail_id() — returns featured image attachment ID.
	 * Returns value from _cp_thumbnails[ $post_id ] or 0.
	 *
	 * @param int $post_id Post ID.
	 * @return int Attachment ID or 0.
	 */
	function get_post_thumbnail_id( int $post_id ): int {
		return (int) ( $GLOBALS['_cp_thumbnails'][ $post_id ] ?? 0 );
	}
}

// =============================================================================
// $wpdb STUB — for attachment ID lookup in sideload_featured_image
// =============================================================================

if ( ! isset( $GLOBALS['wpdb'] ) ) {
	$GLOBALS['wpdb'] = new class {
		public string $posts = 'wp_posts';

		/**
		 * Prepare a SQL statement (stub — returns query string).
		 * @return string
		 */
		public function prepare( string $query, ...$args ): string {
			return vsprintf( str_replace( '%d', '%s', $query ), $args );
		}

		/**
		 * Get single var (stub — returns incrementing attachment ID).
		 * @return int
		 */
		public function get_var( string $query ) {
			static $attach_id = 1000;
			$attach_id++;
			return $attach_id;
		}
	};
}