<?php
/**
 * DNA Header
 *
 * File:         tests/test-diff.php
 * Version:      1.1.0
 * Purpose:      PHPUnit tests for diff viewer and original_post_id storage
 * Author:       C-C (Session 07, Sprint 4)
 * Spec:         sprint_4_d1_d7_instructions.yaml D4 test_diff scenarios
 * PHP Version:  7.4+
 * Dependencies: bootstrap.php
 * Reusable:     No — test file
 *
 * @package ConsensusPress
 */

declare(strict_types=1);

use PHPUnit\Framework\TestCase;

/**
 * Class Test_Diff
 *
 * Tests for diff viewer template logic and original_post_id meta storage.
 */
class Test_Diff extends TestCase {

	protected function setUp(): void {
		cp_test_reset_globals();
	}

	// =========================================================================
	// process_job() rescue branch — original_post_id meta storage
	// =========================================================================

	/**
	 * process_job() rescue branch stores _consensuspress_original_post_id on draft.
	 */
	public function test_original_post_id_stored_on_rescued_draft(): void {
		// Seed a rescue job.
		$async  = new ConsensusPress_Async();
		$job_id = $async->schedule_rescue_job( 42, '<p>rescue content</p>' );

		// Seed a successful API mock response — Sprint 7 engine shape in server envelope.
		$GLOBALS['_cp_mock_api_response'] = array(
			'http_status' => 200,
			'_raw_body'   => array(
				'success' => true,
				'data'    => array(
					'query'          => 'Rescued post content for AI optimisation',
					'correlation_id' => 'test-corr-rescue-001',
					'consensus'      => array(
						'reached'                => true,
						'convergence_percentage' => 82.0,
						'consensus_confidence'   => 'HIGH',
						'champion'               => 'openai',
						'champion_score'         => 88,
						'consensus_text'         => 'Rescued content restructured for AI-search resilience.',
					),
					'providers'      => array(
						array( 'provider' => 'openai',  'score' => 88, 'confidence' => 0.88, 'is_refusal' => false, 'response_text' => 'Restructured content performs better in AI search.' ),
						array( 'provider' => 'claude',  'score' => 84, 'confidence' => 0.84, 'is_refusal' => false, 'response_text' => 'Schema markup improves citation rate.' ),
						array( 'provider' => 'gemini',  'score' => 82, 'confidence' => 0.82, 'is_refusal' => false, 'response_text' => 'FAQ sections drive AI-search visibility.' ),
						array( 'provider' => 'mistral', 'score' => 79, 'confidence' => 0.79, 'is_refusal' => false, 'response_text' => 'Consensus validation reduces hallucination risk.' ),
						array( 'provider' => 'cohere',  'score' => 77, 'confidence' => 0.77, 'is_refusal' => false, 'response_text' => 'Entity clarity drives consistent citations.' ),
					),
					'divergence'     => array(
						'common_themes' => array( 'Structure matters', 'Entity clarity' ),
						'outliers'      => array(),
					),
					'risk_analysis'  => array(
						'oracle_recommendation' => 'Proceed. Rescue content has strong cross-model agreement.',
					),
				),
			),
		);

		$async->process_job( $job_id );

		$post_id = $GLOBALS['_cp_last_post_id'];

		$this->assertSame(
			42,
			$GLOBALS['_cp_post_meta'][ $post_id ]['_consensuspress_original_post_id']
		);
	}

	/**
	 * process_job() create branch does NOT store _consensuspress_original_post_id.
	 */
	public function test_original_post_id_not_stored_on_create_draft(): void {
		$async  = new ConsensusPress_Async();
		$job_id = $async->schedule_job( 'Topic for creation test', '' );

		$GLOBALS['_cp_mock_api_response'] = array(
			'http_status' => 200,
			'_raw_body'   => array(
				'success' => true,
				'data'    => array(
					'query'          => 'Topic for creation test',
					'correlation_id' => 'test-corr-create-002',
					'consensus'      => array(
						'reached'                => true,
						'convergence_percentage' => 78.0,
						'consensus_confidence'   => 'HIGH',
						'champion'               => 'claude',
						'champion_score'         => 85,
						'consensus_text'         => 'Created content from topic with strong consensus agreement.',
					),
					'providers'      => array(
						array( 'provider' => 'openai',  'score' => 84, 'confidence' => 0.84, 'is_refusal' => false, 'response_text' => 'Topic analysis complete.' ),
						array( 'provider' => 'claude',  'score' => 85, 'confidence' => 0.85, 'is_refusal' => false, 'response_text' => 'Content validated across models.' ),
						array( 'provider' => 'gemini',  'score' => 80, 'confidence' => 0.80, 'is_refusal' => false, 'response_text' => 'Consensus reached on key points.' ),
						array( 'provider' => 'mistral', 'score' => 75, 'confidence' => 0.75, 'is_refusal' => false, 'response_text' => 'Agreement on structure and approach.' ),
						array( 'provider' => 'cohere',  'score' => 72, 'confidence' => 0.72, 'is_refusal' => false, 'response_text' => 'Cross-validation confirms accuracy.' ),
					),
					'divergence'     => array(
						'common_themes' => array( 'Core topic agreement' ),
						'outliers'      => array(),
					),
					'risk_analysis'  => array(
						'oracle_recommendation' => 'Low risk. Proceed with confidence.',
					),
				),
			),
		);

		$async->process_job( $job_id );

		$post_id = $GLOBALS['_cp_last_post_id'];

		$this->assertArrayNotHasKey(
			'_consensuspress_original_post_id',
			$GLOBALS['_cp_post_meta'][ $post_id ] ?? array()
		);
	}
}