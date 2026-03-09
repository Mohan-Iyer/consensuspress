<?php
/**
 * Plugin Name: ConsensusPress by Seekrates
 * Plugin URI:  https://seekrates.ai
 * Description: AI-consensus content creation for WordPress. Cross-model validation, hallucination filtering, AI-search optimisation.
 * Version:     2.1.0
 * Author:      Seekrates AI
 * Author URI:  https://seekrates.ai
 * License:     GPL-2.0-or-later
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain: consensuspress
 * Domain Path: /languages
 * Requires at least: 6.2
 * Requires PHP:      7.4
 *
 * DNA Header
 * File:         consensuspress.php
 * Version:      2.1.0 (Sprint 8 — WordPress.org Packaging)
 * Purpose:      Plugin bootstrap: constants, activation, init, AJAX hooks
 * Author:       C-C (Session 02, Sprint 1) | Modified: C-C (Session 04, Sprint 2) | Modified: C-C (Session 05, Sprint 3) | Modified: C-C (Session 07, Sprint 4) | Modified: C-C (Session 07, Sprint 5) | Modified: C-C (Session 07, Sprint 6)
 * PHP Version:  7.4+
 * Dependencies: All includes/class-* files
 * Reusable:     No — plugin entry point
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

// =============================================================================
// CONSTANTS
// =============================================================================

define( 'CONSENSUSPRESS_VERSION',    '2.1.0' );
define( 'CONSENSUSPRESS_PLUGIN_DIR', plugin_dir_path( __FILE__ ) );
define( 'CONSENSUSPRESS_PLUGIN_URL', plugin_dir_url( __FILE__ ) );
define( 'CONSENSUSPRESS_PLUGIN_FILE', __FILE__ );

// =============================================================================
// ACTIVATION / DEACTIVATION
// =============================================================================

/**
 * Plugin activation handler.
 *
 * @return void
 */
function consensuspress_activate(): void {
	if ( ! current_user_can( 'activate_plugins' ) ) {
		return;
	}
	// Set defaults on first activation.
	if ( false === get_option( 'consensuspress_api_key' ) ) {
		add_option( 'consensuspress_api_key', '' );
	}
	if ( false === get_option( 'consensuspress_post_status' ) ) {
		add_option( 'consensuspress_post_status', 'draft' );
	}
	if ( false === get_option( 'consensuspress_unsplash_key' ) ) {
		add_option( 'consensuspress_unsplash_key', '' );
	}
}
register_activation_hook( CONSENSUSPRESS_PLUGIN_FILE, 'consensuspress_activate' );

/**
 * Plugin deactivation handler.
 *
 * @return void
 */
function consensuspress_deactivate(): void {
	if ( ! current_user_can( 'activate_plugins' ) ) {
		return;
	}
	// No cleanup required on deactivation (options persist for reactivation).
}
register_deactivation_hook( CONSENSUSPRESS_PLUGIN_FILE, 'consensuspress_deactivate' );

// =============================================================================
// INITIALISATION
// =============================================================================

/**
 * Plugin init: load classes and register hooks.
 *
 * @return void
 */
function consensuspress_init(): void {
	// Sprint 1 — Foundation.
	require_once CONSENSUSPRESS_PLUGIN_DIR . 'includes/interface-consensuspress-transport.php';
	require_once CONSENSUSPRESS_PLUGIN_DIR . 'includes/class-consensuspress-api.php';
	require_once CONSENSUSPRESS_PLUGIN_DIR . 'includes/class-consensuspress-settings.php';

	// Sprint 2 — Create Mode.
	require_once CONSENSUSPRESS_PLUGIN_DIR . 'includes/class-consensuspress-post-builder.php';
	require_once CONSENSUSPRESS_PLUGIN_DIR . 'includes/class-consensuspress-create.php';

	// Sprint 3 — Async + Meta Box.
	require_once CONSENSUSPRESS_PLUGIN_DIR . 'includes/class-consensuspress-async.php';
	require_once CONSENSUSPRESS_PLUGIN_DIR . 'includes/class-consensuspress-meta-box.php';

	// Sprint 4 — Rescue Mode.
	require_once CONSENSUSPRESS_PLUGIN_DIR . 'includes/class-consensuspress-rescue.php';

	// Sprint 5 — Usage Tracking.
	require_once CONSENSUSPRESS_PLUGIN_DIR . 'includes/class-consensuspress-usage.php';

	// Boot settings page.
	$settings = new ConsensusPress_Settings();
	$settings->init();

	// Boot Create mode.
	$create = new ConsensusPress_Create();
	$create->init();

	// Boot Async processor.
	$async = new ConsensusPress_Async();
	$async->init();

	// Boot Meta Box.
	$meta_box = new ConsensusPress_Meta_Box();
	$meta_box->init();

	// Boot Rescue mode.
	$rescue = new ConsensusPress_Rescue();
	$rescue->init();

	// Boot Usage tracking.
	$usage = new ConsensusPress_Usage();
	$usage->init();
}
add_action( 'plugins_loaded', 'consensuspress_init' );

// =============================================================================
// AJAX HOOKS
// =============================================================================

// Sprint 1 — Test connection (settings page).
add_action( 'wp_ajax_consensuspress_test_connection', 'consensuspress_ajax_test_connection' );

// Sprint 2/3 — Create post (async, Create page).
add_action( 'wp_ajax_consensuspress_create_post', 'consensuspress_ajax_create_post', 10 );

// Sprint 3 — Poll job status (polling loop).
add_action( 'wp_ajax_consensuspress_poll_job', 'consensuspress_ajax_poll_job', 10 );

// Sprint 4 — Rescue post.
add_action( 'wp_ajax_consensuspress_rescue_post',     'consensuspress_ajax_rescue_post' );
add_action( 'wp_ajax_consensuspress_poll_rescue_job', 'consensuspress_ajax_poll_rescue_job' );

// =============================================================================
// AJAX HANDLERS
// =============================================================================

/**
 * AJAX handler for settings page connection test.
 *
 * @return void Sends JSON response via wp_send_json_success/error.
 */
function consensuspress_ajax_test_connection(): void {
	check_ajax_referer( 'consensuspress_test_connection', 'nonce' );

	if ( ! current_user_can( 'manage_options' ) ) {
		wp_send_json_error( array( 'message' => 'Insufficient permissions.' ) );
	}

	$api_key = sanitize_text_field( wp_unslash( $_POST['api_key'] ?? '' ) );

	if ( empty( $api_key ) ) {
		wp_send_json_error( array( 'message' => 'API key is required.' ) );
	}

	$api    = new ConsensusPress_API( $api_key );
	$result = $api->query( 'Connection test query', 'create' );

	if ( $result['success'] ) {
		wp_send_json_success( array( 'message' => 'Connection successful.' ) );
	} else {
		wp_send_json_error( $result['error'] );
	}
}

/**
 * AJAX handler for Create Post form submission.
 *
 * Schedules an async WP-Cron job and returns the job_id immediately.
 * The JS polling loop calls consensuspress_ajax_poll_job() to check status.
 *
 * @return void Sends JSON response via wp_send_json_success/error.
 */
function consensuspress_ajax_create_post(): void {
	check_ajax_referer( 'consensuspress_create_post', 'nonce' );

	if ( ! current_user_can( 'edit_posts' ) ) {
		wp_send_json_error( array( 'message' => 'Insufficient permissions.' ) );
	}

	// Sprint 5 — Quota gate.
	$usage = new ConsensusPress_Usage();
	$quota = $usage->check_quota( 'create' );
	if ( ! $quota['allowed'] ) {
		wp_send_json_error( array(
			'message'     => $quota['message'],
			'upgrade_url' => 'https://seekrates-ai.com/pricing',
		) );
	}

	$topic   = sanitize_text_field( wp_unslash( $_POST['topic'] ?? '' ) );
	$context = sanitize_textarea_field( wp_unslash( $_POST['context'] ?? '' ) );

	// Validate topic length (min 10 chars).
	if ( strlen( $topic ) < 10 ) {
		wp_send_json_error( array( 'message' => 'Topic must be at least 10 characters.' ) );
	}

	// Validate API key is configured.
	$api_key = get_option( 'consensuspress_api_key', '' );
	if ( empty( $api_key ) ) {
		wp_send_json_error( array( 'message' => 'API key not configured. Please visit Settings.' ) );
	}

	// Schedule async job — returns immediately with job_id.
	$async  = new ConsensusPress_Async();
	$job_id = $async->schedule_job( $topic, $context );

	wp_send_json_success( array( 'job_id' => $job_id ) );
}

/**
 * AJAX polling endpoint for async job status.
 *
 * Called by consensuspress-create.js every 5 seconds after
 * job creation. Returns status: pending|processing|complete|failed.
 *
 * @return void Sends JSON via wp_send_json_success.
 */
function consensuspress_ajax_poll_job(): void {
	check_ajax_referer( 'consensuspress_poll_job', 'nonce' );

	if ( ! current_user_can( 'edit_posts' ) ) {
		wp_send_json_error( array( 'message' => 'Insufficient permissions.' ) );
	}

	$job_id = sanitize_text_field( wp_unslash( $_POST['job_id'] ?? '' ) );

	if ( empty( $job_id ) ) {
		wp_send_json_error( array( 'message' => 'Job ID is required.' ) );
	}

	$async  = new ConsensusPress_Async();
	$status = $async->get_job_status( $job_id );

	wp_send_json_success( $status );
}

/**
 * AJAX handler for Rescue form submission.
 *
 * Validates nonce, capability, and post_id. Extracts post content,
 * schedules async rescue job, returns job_id immediately.
 *
 * @return void Sends JSON via wp_send_json_success/error.
 */
function consensuspress_ajax_rescue_post(): void {
	check_ajax_referer( 'consensuspress_rescue_post', 'nonce' );

	if ( ! current_user_can( 'edit_posts' ) ) {
		wp_send_json_error( array( 'message' => 'Insufficient permissions.' ) );
	}

	// Sprint 5 — Quota gate.
	$usage = new ConsensusPress_Usage();
	$quota = $usage->check_quota( 'rescue' );
	if ( ! $quota['allowed'] ) {
		wp_send_json_error( array(
			'message'     => $quota['message'],
			'upgrade_url' => 'https://seekrates-ai.com/pricing',
		) );
	}

	$post_id = absint( $_POST['post_id'] ?? 0 );

	if ( 0 === $post_id ) {
		wp_send_json_error( array( 'message' => 'Please select a post to rescue.' ) );
	}

	// Validate API key is configured.
	$api_key = get_option( 'consensuspress_api_key', '' );
	if ( empty( $api_key ) ) {
		wp_send_json_error( array( 'message' => 'API key not configured. Please visit Settings.' ) );
	}

	// Extract post content.
	$rescue       = new ConsensusPress_Rescue();
	$content_html = $rescue->extract_post_content( $post_id );

	if ( empty( $content_html ) ) {
		wp_send_json_error( array( 'message' => 'Could not extract content from the selected post.' ) );
	}

	// Schedule async rescue job.
	$async  = new ConsensusPress_Async();
	$job_id = $async->schedule_rescue_job( $post_id, $content_html );

	wp_send_json_success( array( 'job_id' => $job_id ) );
}

/**
 * AJAX poll handler for rescue job status.
 *
 * Identical logic to consensuspress_ajax_poll_job() — only nonce action differs.
 *
 * @return void Sends JSON via wp_send_json_success/error.
 */
function consensuspress_ajax_poll_rescue_job(): void {
	check_ajax_referer( 'consensuspress_poll_rescue_job', 'nonce' );

	if ( ! current_user_can( 'edit_posts' ) ) {
		wp_send_json_error( array( 'message' => 'Insufficient permissions.' ) );
	}

	$job_id = sanitize_text_field( wp_unslash( $_POST['job_id'] ?? '' ) );

	if ( empty( $job_id ) ) {
		wp_send_json_error( array( 'message' => 'Invalid job ID.' ) );
	}

	$async  = new ConsensusPress_Async();
	$status = $async->get_job_status( $job_id );

	wp_send_json_success( $status );
}