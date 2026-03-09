<?php
/**
 * DNA Header
 * File:         tests/test-post-builder.php
 * Version:      2.0.0
 * Purpose:      PHPUnit tests for ConsensusPress_Post_Builder v2.0.0.
 *               Updated for real engine field paths (HAL-008 resolution).
 *               Old assumed v1.0 mock field paths replaced throughout.
 * Author:       C-C (Session 03, Sprint 2) | Modified: C-C (Session 09, Sprint 7)
 * Spec:         docs/sprint_7_D1_d7_instructions.yaml D5 tests_to_update
 * PHP Version:  7.4+
 * Dependencies: tests/bootstrap.php, class-consensuspress-post-builder.php v2.0.0
 * Test count:   11 tests (unchanged from Sprint 2 count — assertions updated only)
 */

if ( ! defined( 'ABSPATH' ) ) {
	define( 'ABSPATH', dirname( __DIR__ ) . '/plugin/consensuspress/' );
}

use PHPUnit\Framework\TestCase;

/**
 * @covers ConsensusPress_Post_Builder
 */
class Test_ConsensusPress_Post_Builder extends TestCase {

	// =========================================================================
	// FIXTURE HELPERS
	// =========================================================================

	/**
	 * Build a valid fixture_create_success array matching real engine output.
	 * Field paths from consensuspress_mock_api.yaml v2.0.0.
	 *
	 * @return array
	 */
	private function make_valid_fixture(): array {
		return array(
			'query'          => 'What are the most effective AI search optimisation strategies for WordPress blogs in 2026?',
			'mode'           => 'create',
			'correlation_id' => 'a1b2c3d4-e5f6-7890-abcd-ef1234567890',
			'tier'           => 'acolyte',
			'consensus'      => array(
				'champion'               => 'openai',
				'champion_score'         => 82,
				'convergence_percentage' => 87.5,
				'consensus_confidence'   => 'HIGH',
				'reached'                => true,
				'agreement_percentage'   => 87.5,
				'consensus_text'         => 'Five AI models reached 87.5% agreement that WordPress blogs must adopt three complementary optimisation strategies to survive the AI search transition. GEO ensures content appears in AI-generated summaries. LLMO structures content for extraction by language models. AEO targets direct-answer placements in Perplexity, SGE, and ChatGPT search. All five providers agreed that structured data is the single highest-impact technical implementation.',
				'consensus_panel'        => '<p>HTML panel — not used for blog</p>',
				'divergence_highlight'   => '',
				'dissenting_provider'    => '',
			),
			'providers'      => array(
				array(
					'provider'   => 'openai',
					'answer'     => 'AI search optimisation for WordPress requires GEO, LLMO, and AEO strategies working together.',
					'score'      => 82,
					'confidence' => 0.88,
					'is_refusal' => false,
					'status'     => 'success',
				),
				array(
					'provider'   => 'claude',
					'answer'     => 'WordPress content strategy for AI search survival requires structured data, semantic architecture, and consensus-validated claims.',
					'score'      => 78,
					'confidence' => 0.85,
					'is_refusal' => false,
					'status'     => 'success',
				),
				array(
					'provider'   => 'gemini',
					'answer'     => 'AI search engines extract content differently from traditional crawlers. FAQ schema is the highest priority.',
					'score'      => 75,
					'confidence' => 0.82,
					'is_refusal' => false,
					'status'     => 'success',
				),
				array(
					'provider'   => 'mistral',
					'answer'     => 'Implement FAQPage schema on all posts and restructure headers as direct questions.',
					'score'      => 71,
					'confidence' => 0.79,
					'is_refusal' => false,
					'status'     => 'success',
				),
				array(
					'provider'   => 'cohere',
					'answer'     => 'WordPress blogs face a fundamental architecture challenge: traditional long-form content does not extract well into AI summaries.',
					'score'      => 68,
					'confidence' => 0.75,
					'is_refusal' => false,
					'status'     => 'success',
				),
			),
			'divergence'     => array(
				'common_themes'      => array(
					'FAQ schema implementation increases AI citation likelihood',
					'40-60 word paragraph structure optimises LLM extraction',
					'Question-format H2 headings match AI query patterns',
					'Multi-model validation reduces hallucination risk',
				),
				'outliers'           => array(
					'Cohere emphasises extraction architecture over authority signals',
					'Mistral uniquely cites consensus methodology itself as an SEO signal',
				),
				'personality_quotes' => array(),
				'article_hook'       => 'Five AI models reached 87.5% agreement.',
				'theme_coverage'     => array(),
			),
			'risk_analysis'  => null,
		);
	}

	/**
	 * Build a minimal invalid fixture missing required keys.
	 *
	 * @return array
	 */
	private function make_invalid_fixture(): array {
		return array(
			'query' => 'test',
			// Missing: consensus, providers, correlation_id
		);
	}

	// =========================================================================
	// TESTS — validate_api_data (via create_draft)
	// =========================================================================

	/**
	 * @test
	 */
	public function test_create_draft_returns_error_for_missing_consensus_key(): void {
		$builder = new ConsensusPress_Post_Builder();
		$data    = $this->make_invalid_fixture();

		$result = $builder->create_draft( $data, 'create' );

		$this->assertFalse( $result['success'] );
		$this->assertSame( 0, $result['post_id'] );
		$this->assertStringContainsString( 'Invalid', $result['message'] );
	}

	/**
	 * @test
	 */
	public function test_create_draft_returns_error_when_providers_empty(): void {
		$builder         = new ConsensusPress_Post_Builder();
		$data            = $this->make_valid_fixture();
		$data['providers'] = array();  // Empty providers array.

		$result = $builder->create_draft( $data, 'create' );

		$this->assertFalse( $result['success'] );
	}

	/**
	 * @test
	 */
	public function test_create_draft_returns_error_when_consensus_text_missing(): void {
		$builder = new ConsensusPress_Post_Builder();
		$data    = $this->make_valid_fixture();
		unset( $data['consensus']['consensus_text'] );

		$result = $builder->create_draft( $data, 'create' );

		$this->assertFalse( $result['success'] );
	}

	// =========================================================================
	// TESTS — create_draft success path (new field paths v2.0.0)
	// =========================================================================

	/**
	 * @test
	 */
	public function test_draft_creation_returns_success_with_valid_fixture(): void {
		$builder = new ConsensusPress_Post_Builder();
		$data    = $this->make_valid_fixture();

		$result = $builder->create_draft( $data, 'create' );

		$this->assertTrue( $result['success'] );
		$this->assertGreaterThan( 0, $result['post_id'] );
		$this->assertNotEmpty( $result['edit_url'] );
	}

	/**
	 * @test
	 * Verifies consensus.convergence_percentage (not old consensus_score) drives post meta.
	 */
	public function test_consensus_score_meta_reads_from_convergence_percentage(): void {
		$builder = new ConsensusPress_Post_Builder();
		$data    = $this->make_valid_fixture();
		// consensus.convergence_percentage = 87.5

		$result = $builder->create_draft( $data, 'create' );

		$this->assertTrue( $result['success'] );
		$score = get_post_meta( $result['post_id'], '_consensuspress_score', true );
		$this->assertEquals( 87.5, (float) $score, 'Score must read from consensus.convergence_percentage' );
	}

	/**
	 * @test
	 * Verifies consensus.champion (not old champion.provider) drives champion meta.
	 */
	public function test_champion_provider_meta_reads_from_consensus_champion(): void {
		$builder = new ConsensusPress_Post_Builder();
		$data    = $this->make_valid_fixture();
		// consensus.champion = 'openai'

		$result = $builder->create_draft( $data, 'create' );

		$this->assertTrue( $result['success'] );
		$champion = get_post_meta( $result['post_id'], '_consensuspress_champion_provider', true );
		$this->assertSame( 'openai', $champion, 'Champion must read from consensus.champion' );
	}

	/**
	 * @test
	 * Verifies consensus.consensus_confidence (not old agreement_level) drives agreement meta.
	 */
	public function test_agreement_level_meta_reads_from_consensus_confidence(): void {
		$builder = new ConsensusPress_Post_Builder();
		$data    = $this->make_valid_fixture();
		// consensus.consensus_confidence = 'HIGH'

		$result = $builder->create_draft( $data, 'create' );

		$this->assertTrue( $result['success'] );
		$level = get_post_meta( $result['post_id'], '_consensuspress_agreement_level', true );
		$this->assertSame( 'HIGH', $level, 'Agreement level must read from consensus.consensus_confidence' );
	}

	/**
	 * @test
	 * Verifies risk_analysis null (seeker/acolyte) does not cause errors.
	 */
	public function test_null_risk_analysis_does_not_cause_errors(): void {
		$builder                  = new ConsensusPress_Post_Builder();
		$data                     = $this->make_valid_fixture();
		$data['risk_analysis']    = null;  // seeker/acolyte tier.

		$result = $builder->create_draft( $data, 'create' );

		$this->assertTrue( $result['success'] );
		$risk = get_post_meta( $result['post_id'], '_consensuspress_oracle_risk_level', true );
		$this->assertSame( '', $risk, 'Risk level should be empty string when risk_analysis is null' );
	}

	// =========================================================================
	// TESTS — content pipeline
	// =========================================================================

	/**
	 * @test
	 * Verifies build_content_html produces no Elementor shortcodes.
	 */
	public function test_content_html_contains_no_elementor_shortcodes(): void {
		$builder = new ConsensusPress_Post_Builder();
		$data    = $this->make_valid_fixture();

		$result = $builder->create_draft( $data, 'create' );

		$this->assertTrue( $result['success'] );
		$content = get_post_field( 'post_content', $result['post_id'] );
		$this->assertStringNotContainsString( 'elementor-template', $content );
		$this->assertStringNotContainsString( '[elementor', $content );
	}

	/**
	 * @test
	 * Verifies build_content_html uses home_url() not seekrates-ai.com.
	 */
	public function test_content_html_has_no_seekrates_hardcoded_urls(): void {
		$builder = new ConsensusPress_Post_Builder();
		$data    = $this->make_valid_fixture();

		$result = $builder->create_draft( $data, 'create' );

		$this->assertTrue( $result['success'] );
		$content = get_post_field( 'post_content', $result['post_id'] );
		$this->assertStringNotContainsString( 'seekrates-ai.com', $content );
	}

	/**
	 * @test
	 * Verifies sideload_featured_image is NOT called when featured_image is empty array.
	 */
	public function test_featured_image_skipped_when_empty(): void {
		$builder = new ConsensusPress_Post_Builder();
		$data    = $this->make_valid_fixture();

		// Sprint 7: generate_content_pipeline() returns featured_image = [].
		// Verify post creates successfully without thumbnail.
		$result = $builder->create_draft( $data, 'create' );

		$this->assertTrue( $result['success'] );
		$thumbnail_id = get_post_thumbnail_id( $result['post_id'] );
		$this->assertEmpty( $thumbnail_id, 'No featured image should be set in Sprint 7' );
	}

	/**
	 * @test
	 */
	public function test_rescue_mode_sets_correct_mode_meta(): void {
		$builder = new ConsensusPress_Post_Builder();
		$data    = $this->make_valid_fixture();

		$result = $builder->create_draft( $data, 'rescue' );

		$this->assertTrue( $result['success'] );
		$mode = get_post_meta( $result['post_id'], '_consensuspress_mode', true );
		$this->assertSame( 'rescue', $mode );
	}
}
