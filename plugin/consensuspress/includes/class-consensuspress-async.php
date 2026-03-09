<?php
/**
 * DNA Header
 * File:         includes/class-consensuspress-async.php
 * Version:      1.2.0
 * Purpose:      WP-Cron async job scheduler and processor for consensus API calls
 * Author:       C-C (Session 05, Sprint 3) | Modified: C-C (Session 07, Sprint 4) | Modified: C-C (Session 07, Sprint 5)
 * Spec:         sprint3_d1_d7.yaml D1 class_consensuspress_async | sprint_4_d1_d7_instructions.yaml | sprint_5_d1_d7_instructions.yaml
 * PHP Version:  7.4+
 * Dependencies: class-consensuspress-api.php, class-consensuspress-post-builder.php
 * Reusable:     Yes — called by Create (Sprint 3) and Rescue (Sprint 4)
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Handles async scheduling and processing of consensus API jobs via WP-Cron.
 *
 * Jobs are stored as transients keyed by UUID. The JS polling loop
 * calls consensuspress_ajax_poll_job() to read status until complete.
 */
class ConsensusPress_Async {

	/**
	 * Transient key prefix for all jobs.
	 * Underscore prefix hides from options UI.
	 *
	 * @var string
	 */
	const JOB_TRANSIENT_PREFIX = '_cp_job_';

	/**
	 * Transient TTL in seconds (1 hour). Jobs auto-expire.
	 *
	 * @var int
	 */
	const JOB_EXPIRY = 3600;

	/**
	 * WP-Cron action hook name.
	 * Must match add_action call in init().
	 *
	 * @var string
	 */
	const CRON_HOOK = 'consensuspress_process_job';

	/**
	 * Valid job status values.
	 *
	 * @var array<string>
	 */
	const JOB_STATUSES = array( 'pending', 'processing', 'complete', 'failed' );

	// =========================================================================
	// PUBLIC METHODS
	// =========================================================================

	/**
	 * Register WP-Cron hook for background job processing.
	 *
	 * @return void
	 */
	public function init(): void {
		add_action( self::CRON_HOOK, array( $this, 'process_job' ) );
	}

	/**
	 * Schedule an async consensus job for background processing.
	 *
	 * Stores job data in transient, schedules WP-Cron event, and
	 * triggers immediate cron spawn via non-blocking loopback request.
	 *
	 * @param string $topic   Sanitised topic string (min 10 chars).
	 * @param string $context Optional sanitised context string.
	 * @return string Unique job ID (UUID v4).
	 */
	public function schedule_job( string $topic, string $context ): string {
		$job_id = wp_generate_uuid4();

		$job_data = array(
			'status'  => 'pending',
			'topic'   => $topic,
			'context' => $context,
			'created' => current_time( 'mysql' ),
			'post_id' => 0,
			'edit_url' => '',
			'error'   => null,
		);

		set_transient( self::JOB_TRANSIENT_PREFIX . $job_id, $job_data, self::JOB_EXPIRY );
		wp_schedule_single_event( time(), self::CRON_HOOK, array( $job_id ) );
		$this->spawn_cron();

		return $job_id;
	}

	/**
	 * Schedule an async rescue job for background processing.
	 *
	 * Stores original post_id so rescued draft can reference it for diff viewer.
	 * Returns job UUID for client polling via get_job_status().
	 *
	 * @param int    $post_id      WordPress post ID of the original post to rescue.
	 * @param string $content_html Extracted post content HTML (max 50,000 chars).
	 * @return string UUID job identifier.
	 */
	public function schedule_rescue_job( int $post_id, string $content_html ): string {
		$job_id   = wp_generate_uuid4();
		$job_data = array(
			'status'       => 'pending',
			'mode'         => 'rescue',
			'post_id'      => $post_id,
			'content_html' => $content_html,
			'created'      => current_time( 'mysql' ),
			'edit_url'     => '',
			'error'        => null,
		);
		set_transient( self::JOB_TRANSIENT_PREFIX . $job_id, $job_data, self::JOB_EXPIRY );
		wp_schedule_single_event( time(), self::CRON_HOOK, array( $job_id ) );
		$this->spawn_cron();
		return $job_id;
	}

	/**
	 * WP-Cron callback: process a scheduled consensus job.
	 *
	 * Idempotent — safe to call multiple times (checks status before processing).
	 * Handles both 'create' (Sprint 3) and 'rescue' (Sprint 4) modes.
	 * Calls Seekrates API, creates WordPress draft, updates job transient.
	 *
	 * @param string $job_id UUID job identifier from schedule_job() or schedule_rescue_job().
	 * @return void
	 */
	public function process_job( string $job_id ): void {
		$job_data = get_transient( self::JOB_TRANSIENT_PREFIX . $job_id );

		// Idempotent guard — only process pending jobs.
		if ( ! $job_data || 'pending' !== $job_data['status'] ) {
			return;
		}

		// Mark as processing.
		$job_data['status'] = 'processing';
		set_transient( self::JOB_TRANSIENT_PREFIX . $job_id, $job_data, self::JOB_EXPIRY );

		// Validate API key.
		$api_key = get_option( 'consensuspress_api_key', '' );
		if ( empty( $api_key ) ) {
			$job_data['status'] = 'failed';
			$job_data['error']  = array(
				'code'    => 'missing_api_key',
				'message' => 'API key not configured. Please visit Settings.',
			);
			set_transient( self::JOB_TRANSIENT_PREFIX . $job_id, $job_data, self::JOB_EXPIRY );
			return;
		}

		// Sprint 4 — Branch on mode.
		$mode = $job_data['mode'] ?? 'create';
		if ( 'rescue' === $mode ) {
			$query   = $job_data['content_html'];
			$context = '';
		} else {
			$query   = $job_data['topic'];
			$context = $job_data['context'];
		}

		// Call Seekrates API.
		$api    = new ConsensusPress_API( $api_key );
		$result = $api->query( $query, $mode, $context );

		if ( ! $result['success'] ) {
			// Sprint 5 — Sync quota state from server on 402.
			if ( 402 === ( $result['http_status'] ?? 0 ) ) {
				( new ConsensusPress_Usage() )->handle_quota_exceeded( $result );
			}
			$job_data['status'] = 'failed';
			$job_data['error']  = $result['error'] ?? array(
				'code'    => 'api_error',
				'message' => 'Consensus API call failed.',
			);
			set_transient( self::JOB_TRANSIENT_PREFIX . $job_id, $job_data, self::JOB_EXPIRY );
			return;
		}

		// Build WordPress draft.
		$builder = new ConsensusPress_Post_Builder();
		$draft   = $builder->create_draft( $result['data'], $mode );

		// Sprint 4 — Capture original post_id before success block overwrites job_data['post_id'].
		$original_post_id = ( 'rescue' === $mode ) ? absint( $job_data['post_id'] ) : 0;

		if ( $draft['success'] ) {
			$job_data['status']   = 'complete';
			$job_data['post_id']  = (int) $draft['post_id'];
			$job_data['edit_url'] = (string) $draft['edit_url'];

			// Sprint 5 — Record credit usage after successful draft creation.
			( new ConsensusPress_Usage() )->record_usage( $mode );

			// Sprint 4 — Store original post_id on rescued draft for diff viewer.
			if ( 'rescue' === $mode ) {
				update_post_meta(
					(int) $draft['post_id'],
					'_consensuspress_original_post_id',
					$original_post_id
				);
			}
		} else {
			$job_data['status'] = 'failed';
			$job_data['error']  = array(
				'code'    => 'draft_error',
				'message' => $draft['message'] ?? 'Failed to create WordPress draft.',
			);
		}

		set_transient( self::JOB_TRANSIENT_PREFIX . $job_id, $job_data, self::JOB_EXPIRY );
	}

	/**
	 * Get current status of a scheduled job.
	 *
	 * @param string $job_id UUID job identifier.
	 * @return array{
	 *     status:   string,
	 *     post_id:  int,
	 *     edit_url: string,
	 *     error:    array{code: string, message: string}|null
	 * }
	 */
	public function get_job_status( string $job_id ): array {
		$job_data = get_transient( self::JOB_TRANSIENT_PREFIX . $job_id );

		if ( ! $job_data ) {
			return array(
				'status'   => 'not_found',
				'post_id'  => 0,
				'edit_url' => '',
				'error'    => null,
			);
		}

		return array(
			'status'   => (string) $job_data['status'],
			'post_id'  => (int) ( $job_data['post_id'] ?? 0 ),
			'edit_url' => (string) ( $job_data['edit_url'] ?? '' ),
			'error'    => $job_data['error'] ?? null,
		);
	}

	/**
	 * Delete job transient. Called after client confirms result received.
	 *
	 * @param string $job_id UUID job identifier.
	 * @return void
	 */
	public function cleanup_job( string $job_id ): void {
		delete_transient( self::JOB_TRANSIENT_PREFIX . $job_id );
	}

	// =========================================================================
	// PRIVATE METHODS
	// =========================================================================

	/**
	 * Trigger WP-Cron via non-blocking loopback request.
	 *
	 * Fires wp-cron.php immediately so the job processes without
	 * waiting for the next organic page load. Non-blocking — does
	 * not wait for response. Falls back gracefully if host blocks
	 * loopback requests (job will process on next page load instead).
	 *
	 * @return void
	 */
	private function spawn_cron(): void {
		$cron_url = site_url( 'wp-cron.php?doing_wp_cron' );
		wp_remote_post(
			$cron_url,
			array(
				'timeout'   => 0.01,
				'blocking'  => false,
				'sslverify' => apply_filters( 'https_local_ssl_verify', false ),
			)
		);
	}
}