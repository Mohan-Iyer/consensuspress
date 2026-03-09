<?php
/**
 * DNA Header
 * File:         tests/test-async.php
 * Version:      1.1.0
 * Purpose:      Test async job scheduling, processing, and status retrieval
 * Author:       C-C (Session 05, Sprint 3)
 * Spec:         sprint3_d1_d7.yaml D2 test_async
 * PHP Version:  7.4+
 * Test Count:   8
 * Fixture:      consensuspress_mock_api.yaml fixture_create_success, fixture_server_error
 */

use PHPUnit\Framework\TestCase;

/**
 * Tests for ConsensusPress_Async job scheduling and processing.
 */
class ConsensusPress_Async_Test extends TestCase {

	// =========================================================================
	// FIXTURES
	// =========================================================================

	/**
	 * fixture_create_success — Sprint 7 engine response shape.
	 * Matches ConsensusResult.model_dump() + query/mode injected by endpoint.
	 * Required keys validated by ConsensusPress_Post_Builder::validate_api_data():
	 *   top-level: consensus, providers, correlation_id
	 *   consensus sub-keys: champion, champion_score, convergence_percentage,
	 *                       consensus_confidence, reached, consensus_text
	 *
	 * @var array
	 */
	private const FIXTURE_CREATE_SUCCESS = array(
		'query'          => 'AI consensus testing topic',
		'correlation_id' => 'test-corr-001',
		'consensus'      => array(
			'reached'                => true,
			'convergence_percentage' => 87.5,
			'consensus_confidence'   => 'HIGH',
			'champion'               => 'openai',
			'champion_score'         => 92,
			'consensus_text'         => 'Five AI models reached strong agreement on AI consensus validation strategies. Cross-model validation produces higher-quality outputs than single-model approaches.',
		),
		'providers'      => array(
			array( 'provider' => 'openai',  'score' => 92, 'confidence' => 0.92, 'is_refusal' => false, 'response_text' => 'AI consensus produces validated outputs.' ),
			array( 'provider' => 'claude',  'score' => 88, 'confidence' => 0.88, 'is_refusal' => false, 'response_text' => 'Cross-model validation improves accuracy.' ),
			array( 'provider' => 'gemini',  'score' => 85, 'confidence' => 0.85, 'is_refusal' => false, 'response_text' => 'Consensus methodology reduces hallucinations.' ),
			array( 'provider' => 'mistral', 'score' => 82, 'confidence' => 0.82, 'is_refusal' => false, 'response_text' => 'Multiple models agree on core principles.' ),
			array( 'provider' => 'cohere',  'score' => 80, 'confidence' => 0.80, 'is_refusal' => false, 'response_text' => 'Validation across providers ensures quality.' ),
		),
		'divergence'     => array(
			'common_themes' => array( 'Cross-model validation', 'Hallucination reduction', 'Quality improvement' ),
			'outliers'      => array( 'Some providers weight recency differently' ),
		),
		'risk_analysis'  => array(
			'oracle_recommendation' => 'Proceed with confidence. Low risk of factual error.',
		),
	);

	/**
	 * fixture_server_error — mirrors consensuspress_mock_api.yaml fixture_server_error.
	 *
	 * @var array
	 */
	private const FIXTURE_SERVER_ERROR = array(
		'success'     => false,
		'http_status' => 500,
		'error'       => array(
			'code'    => 'server_error',
			'message' => 'Internal server error. Please try again.',
		),
		'data'        => null,
	);

	// =========================================================================
	// SETUP
	// =========================================================================

	protected function setUp(): void {
		cp_test_reset_globals();
	}

	// =========================================================================
	// TESTS
	// =========================================================================

	/**
	 * Test that schedule_job() returns a non-empty string (UUID format).
	 */
	public function test_schedule_job_returns_uuid(): void {
		$async  = new ConsensusPress_Async();
		$job_id = $async->schedule_job( 'Valid topic here now', '' );

		$this->assertIsString( $job_id );
		$this->assertGreaterThan( 10, strlen( $job_id ) );
	}

	/**
	 * Test that schedule_job() stores correct data in transient.
	 */
	public function test_job_stored_in_transient(): void {
		$async  = new ConsensusPress_Async();
		$job_id = $async->schedule_job( 'Valid topic here now', 'some context' );

		$key      = ConsensusPress_Async::JOB_TRANSIENT_PREFIX . $job_id;
		$job_data = get_transient( $key );

		$this->assertIsArray( $job_data );
		$this->assertSame( 'pending', $job_data['status'] );
		$this->assertSame( 'Valid topic here now', $job_data['topic'] );
		$this->assertSame( 'some context', $job_data['context'] );
		$this->assertNotEmpty( $job_data['created'] );
	}

	/**
	 * Test happy path: process_job() completes successfully.
	 */
	public function test_process_job_happy_path(): void {
		// Arrange: seed transient as pending + mock transport.
		$job_id  = 'test-job-happy-' . uniqid();
		$job_key = ConsensusPress_Async::JOB_TRANSIENT_PREFIX . $job_id;

		set_transient( $job_key, array(
			'status'   => 'pending',
			'topic'    => 'AI consensus testing topic',
			'context'  => '',
			'created'  => '2026-03-01 09:00:00',
			'post_id'  => 0,
			'edit_url' => '',
			'error'    => null,
		), ConsensusPress_Async::JOB_EXPIRY );

		// Seed API response — body must match server envelope: {success, data}.
		// data contains the Sprint 7 engine shape (ConsensusResult.model_dump()).
		$GLOBALS['_cp_mock_api_response'] = array(
			'http_status' => 200,
			'_raw_body'   => array(
				'success' => true,
				'data'    => self::FIXTURE_CREATE_SUCCESS,
			),
		);

		// Act.
		$async = new ConsensusPress_Async();
		$async->process_job( $job_id );

		// Assert.
		$final = get_transient( $job_key );
		$this->assertSame( 'complete', $final['status'] );
		$this->assertGreaterThan( 0, $final['post_id'] );
		$this->assertNotEmpty( $final['edit_url'] );
		$this->assertNotEmpty( $GLOBALS['_cp_wp_insert_post_calls'] );
	}

	/**
	 * Test that process_job() marks job failed on API error.
	 */
	public function test_process_job_api_failure(): void {
		$job_id  = 'test-job-fail-' . uniqid();
		$job_key = ConsensusPress_Async::JOB_TRANSIENT_PREFIX . $job_id;

		set_transient( $job_key, array(
			'status'   => 'pending',
			'topic'    => 'AI consensus testing topic',
			'context'  => '',
			'created'  => '2026-03-01 09:00:00',
			'post_id'  => 0,
			'edit_url' => '',
			'error'    => null,
		), ConsensusPress_Async::JOB_EXPIRY );

		// Seed 500 error response.
		$GLOBALS['_cp_mock_api_response'] = array(
			'http_status' => 500,
			'_raw_body'   => array( 'message' => 'Internal server error.', 'error' => 'server_error' ),
		);

		$async = new ConsensusPress_Async();
		$async->process_job( $job_id );

		$final = get_transient( $job_key );
		$this->assertSame( 'failed', $final['status'] );
		$this->assertNotEmpty( $final['error'] );
		$this->assertEmpty( $GLOBALS['_cp_wp_insert_post_calls'] );
	}

	/**
	 * Test idempotency guard: processing-status jobs are not re-processed.
	 */
	public function test_process_job_idempotent(): void {
		$job_id  = 'test-job-idem-' . uniqid();
		$job_key = ConsensusPress_Async::JOB_TRANSIENT_PREFIX . $job_id;

		// Seed as already processing.
		set_transient( $job_key, array(
			'status'   => 'processing',
			'topic'    => 'Some topic already running',
			'context'  => '',
			'created'  => '2026-03-01 09:00:00',
			'post_id'  => 0,
			'edit_url' => '',
			'error'    => null,
		), ConsensusPress_Async::JOB_EXPIRY );

		$async = new ConsensusPress_Async();
		$async->process_job( $job_id );

		// wp_insert_post must NOT have been called.
		$this->assertEmpty( $GLOBALS['_cp_wp_insert_post_calls'] );

		// Status must remain 'processing'.
		$final = get_transient( $job_key );
		$this->assertSame( 'processing', $final['status'] );
	}

	/**
	 * Test get_job_status() returns correct shape for pending job.
	 */
	public function test_get_job_status_pending(): void {
		$job_id  = 'test-job-status-pending-' . uniqid();
		$job_key = ConsensusPress_Async::JOB_TRANSIENT_PREFIX . $job_id;

		set_transient( $job_key, array(
			'status'   => 'pending',
			'topic'    => 'Some topic',
			'context'  => '',
			'created'  => '2026-03-01 09:00:00',
			'post_id'  => 0,
			'edit_url' => '',
			'error'    => null,
		), ConsensusPress_Async::JOB_EXPIRY );

		$async  = new ConsensusPress_Async();
		$status = $async->get_job_status( $job_id );

		$this->assertSame( 'pending', $status['status'] );
		$this->assertSame( 0, $status['post_id'] );
	}

	/**
	 * Test get_job_status() returns complete with post_id and edit_url.
	 */
	public function test_get_job_status_complete(): void {
		$job_id  = 'test-job-status-complete-' . uniqid();
		$job_key = ConsensusPress_Async::JOB_TRANSIENT_PREFIX . $job_id;

		set_transient( $job_key, array(
			'status'   => 'complete',
			'topic'    => 'Finished topic',
			'context'  => '',
			'created'  => '2026-03-01 09:00:00',
			'post_id'  => 42,
			'edit_url' => '/wp-admin/post.php?post=42&action=edit',
			'error'    => null,
		), ConsensusPress_Async::JOB_EXPIRY );

		$async  = new ConsensusPress_Async();
		$status = $async->get_job_status( $job_id );

		$this->assertSame( 'complete', $status['status'] );
		$this->assertSame( 42, $status['post_id'] );
		$this->assertNotEmpty( $status['edit_url'] );
	}

	/**
	 * Test get_job_status() returns not_found for nonexistent job.
	 */
	public function test_get_job_status_not_found(): void {
		$async  = new ConsensusPress_Async();
		$status = $async->get_job_status( 'nonexistent-job-id-xyz' );

		$this->assertSame( 'not_found', $status['status'] );
	}
}