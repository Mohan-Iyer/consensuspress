<?php
/**
 * DNA Header
 * File:         tests/test-api-client.php
 * Version:      1.1.0
 * Purpose:      PHPUnit tests for ConsensusPress_API v1.1.0.
 *               Sprint 7 addition: T&C gate handler test.
 *               Existing tests preserved from Sprint 1.
 * Author:       C-C (Session 02, Sprint 1) | Modified: C-C (Session 09, Sprint 7)
 * Spec:         docs/sprint_7_D1_d7_instructions.yaml D7 php_scanner_gates
 * PHP Version:  7.4+
 * Dependencies: tests/bootstrap.php, class-consensuspress-mock-transport.php
 * Changes v1.1.0:
 *   - test_query_returns_needs_tc_acceptance_error() ADDED (Sprint 7)
 */

if ( ! defined( 'ABSPATH' ) ) {
	define( 'ABSPATH', dirname( __DIR__ ) . '/plugin/consensuspress/' );
}

use PHPUnit\Framework\TestCase;

/**
 * @covers ConsensusPress_API
 */
class Test_ConsensusPress_API extends TestCase {

	// =========================================================================
	// EXISTING TESTS — PRESERVED (Sprint 1)
	// =========================================================================

	/**
	 * @test
	 */
	public function test_query_returns_success_on_200_with_success_body(): void {
		$mock = new ConsensusPress_Mock_Transport(
			json_encode( array(
				'success' => true,
				'data'    => array(
					'query'          => 'test query',
					'consensus'      => array(
						'champion'               => 'openai',
						'champion_score'         => 80,
						'convergence_percentage' => 85.0,
						'consensus_confidence'   => 'HIGH',
						'reached'                => true,
						'consensus_text'         => 'Test consensus text for validation.',
						'consensus_panel'        => '',
						'agreement_percentage'   => 85.0,
						'divergence_highlight'   => '',
						'dissenting_provider'    => '',
					),
					'providers'      => array(
						array(
							'provider'   => 'openai',
							'answer'     => 'Test answer from openai.',
							'score'      => 80,
							'confidence' => 0.85,
							'is_refusal' => false,
							'status'     => 'success',
						),
					),
					'divergence'     => null,
					'risk_analysis'  => null,
					'correlation_id' => 'test-correlation-id',
					'tier'           => 'acolyte',
				),
			) ),
			200
		);

		$api    = new ConsensusPress_API( 'test-key', $mock );
		$result = $api->query( 'test query for validation', 'create' );

		$this->assertTrue( $result['success'] );
		$this->assertNotNull( $result['data'] );
		$this->assertNull( $result['error'] );
		$this->assertSame( 200, $result['http_status'] );
	}

	/**
	 * @test
	 */
	public function test_query_returns_auth_error_on_401(): void {
		$mock = new ConsensusPress_Mock_Transport( json_encode( array( 'detail' => 'Unauthorized' ) ), 401 );

		$api    = new ConsensusPress_API( 'bad-key', $mock );
		$result = $api->query( 'test query string here', 'create' );

		$this->assertFalse( $result['success'] );
		$this->assertSame( 'auth_error', $result['error']['code'] );
		$this->assertSame( 401, $result['http_status'] );
	}

	/**
	 * @test
	 */
	public function test_query_returns_quota_error_on_402(): void {
		$mock = new ConsensusPress_Mock_Transport( json_encode( array( 'detail' => 'Monthly query limit reached.' ) ), 402
		);

		$api    = new ConsensusPress_API( 'valid-key', $mock );
		$result = $api->query( 'test query string here', 'create' );

		$this->assertFalse( $result['success'] );
		$this->assertSame( 'quota_exceeded', $result['error']['code'] );
		$this->assertSame( 402, $result['http_status'] );
	}

	// =========================================================================
	// NEW TEST — T&C gate (Sprint 7 v1.1.0 addition)
	// =========================================================================

	/**
	 * @test
	 * Verifies T&C gate: HTTP 200 with needs_tc_acceptance body is handled
	 * as a non-error failure (not a 4xx status), per D4 api_php_patch spec.
	 * Must return success=false, code='needs_tc_acceptance', tc_url populated.
	 */
	public function test_query_returns_needs_tc_acceptance_error(): void {
		$tc_url = 'https://seekrates-ai.com/website-t-c/';
		$mock   = new ConsensusPress_Mock_Transport( json_encode( array(
				'status'  => 'needs_tc_acceptance',
				'message' => 'Please accept Terms & Conditions.',
				'tc_url'  => $tc_url,
			), 200 )
		);

		$api    = new ConsensusPress_API( 'valid-key', $mock );
		$result = $api->query( 'test query string here', 'create' );

		// Must be false — T&C not accepted.
		$this->assertFalse( $result['success'] );

		// Error code must be 'needs_tc_acceptance'.
		$this->assertSame( 'needs_tc_acceptance', $result['error']['code'] );

		// tc_url must be present and populated.
		$this->assertArrayHasKey( 'tc_url', $result['error'] );
		$this->assertNotEmpty( $result['error']['tc_url'] );

		// HTTP status must be 200 (it's a 200 gate — not a 4xx).
		$this->assertSame( 200, $result['http_status'] );

		// Data must be null — no consensus data with T&C gate.
		$this->assertNull( $result['data'] );
	}

	/**
	 * @test
	 * Verifies T&C gate fallback tc_url when response body has no tc_url key.
	 */
	public function test_tc_gate_falls_back_to_default_tc_url_when_missing(): void {
		$mock = new ConsensusPress_Mock_Transport( json_encode( array(
				'status'  => 'needs_tc_acceptance',
				'message' => 'Please accept Terms & Conditions.',
				// tc_url intentionally absent.
			), 200 )
		);

		$api    = new ConsensusPress_API( 'valid-key', $mock );
		$result = $api->query( 'test query string here', 'create' );

		$this->assertFalse( $result['success'] );
		$this->assertSame( 'needs_tc_acceptance', $result['error']['code'] );
		// Must fall back to default URL.
		$this->assertStringContainsString( 'seekrates-ai.com', $result['error']['tc_url'] );
	}
}