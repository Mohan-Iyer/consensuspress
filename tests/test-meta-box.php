<?php
/**
 * DNA Header
 * File:         tests/test-meta-box.php
 * Version:      1.2.0
 * Purpose:      Test meta box registration and render logic
 * Author:       C-C (Session 05, Sprint 3)
 * Spec:         sprint3_d1_d7.yaml D2 test_meta_box
 * PHP Version:  7.4+
 * Test Count:   6
 */

use PHPUnit\Framework\TestCase;

/**
 * Tests for ConsensusPress_Meta_Box registration and rendering.
 */
class ConsensusPress_Meta_Box_Test extends TestCase {

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
	 * Test that init() registers the add_meta_boxes action hook.
	 */
	public function test_meta_box_registered_on_init(): void {
		$source = file_get_contents(
			dirname( __DIR__ ) . '/plugin/consensuspress/includes/class-consensuspress-meta-box.php'
		);
		$this->assertStringContainsString( "add_action( 'add_meta_boxes'", $source );
		$this->assertStringContainsString( "'register_meta_box'", $source );
	}

	/**
	 * Test that get_score_class() returns 'cp-score-high' for score >= 80.
	 */
	public function test_score_class_high(): void {
		$meta_box = new ConsensusPress_Meta_Box();
		$closure  = Closure::bind( function( $score ) {
			return $this->get_score_class( $score );
		}, $meta_box, ConsensusPress_Meta_Box::class );

		$result = $closure( 87.5 );
		$this->assertSame( 'cp-score-high', $result );
	}

	/**
	 * Test that get_score_class() returns 'cp-score-moderate' for score >= 60 < 80.
	 */
	public function test_score_class_moderate(): void {
		$meta_box = new ConsensusPress_Meta_Box();
		$closure  = Closure::bind( function( $score ) {
			return $this->get_score_class( $score );
		}, $meta_box, ConsensusPress_Meta_Box::class );

		$result = $closure( 65.0 );
		$this->assertSame( 'cp-score-moderate', $result );
	}

	/**
	 * Test that get_score_class() returns 'cp-score-low' for score < 60.
	 */
	public function test_score_class_low(): void {
		$meta_box = new ConsensusPress_Meta_Box();
		$closure  = Closure::bind( function( $score ) {
			return $this->get_score_class( $score );
		}, $meta_box, ConsensusPress_Meta_Box::class );

		$result = $closure( 45.0 );
		$this->assertSame( 'cp-score-low', $result );
	}

	/**
	 * Test that render_meta_box() loads empty template for non-CP posts.
	 */
	public function test_render_empty_for_non_cp_post(): void {
		// Arrange: post with no _consensuspress_score meta (returns '').
		$post     = new WP_Post( 1, 'Non-CP Post' );
		$meta_box = new ConsensusPress_Meta_Box();

		// get_post_meta returns '' by default for missing key — which triggers empty state.
		$GLOBALS['_cp_post_meta_override'][1]['_consensuspress_score'] = '';

		// Capture output.
		ob_start();
		$meta_box->render_meta_box( $post );
		$output = ob_get_clean();

		// Empty template outputs the "not created via ConsensusPress" message.
		$this->assertStringContainsString( 'not created or rescued by ConsensusPress', $output );
		// Full meta box div (cp-meta-box without -empty suffix) must NOT appear.
		$this->assertStringNotContainsString( '<div class="cp-meta-box">', $output );
	}

	/**
	 * Test that render_meta_box() loads full template for CP posts.
	 */
	public function test_render_full_for_cp_post(): void {
		// Arrange: post with all 6 consensus meta keys populated.
		$post = new WP_Post( 99, 'Consensus Post' );

		$GLOBALS['_cp_post_meta_override'][99] = array(
			'_consensuspress_score'             => '87.5',
			'_consensuspress_champion_provider' => 'openai',
			'_consensuspress_agreement_level'   => 'HIGH',
			'_consensuspress_oracle_risk_level' => 'low',
			'_consensuspress_mode'              => 'create',
			'_consensuspress_created_at'        => '2026-02-28 09:00:00',
		);

		$meta_box = new ConsensusPress_Meta_Box();

		// Capture output.
		ob_start();
		$meta_box->render_meta_box( $post );
		$output = ob_get_clean();

		// Full template should contain the score and meta box structure.
		$this->assertStringContainsString( 'cp-meta-box', $output );
		$this->assertStringContainsString( '87.5', $output );
		$this->assertStringContainsString( 'openai', $output );
		$this->assertStringContainsString( 'HIGH', $output );
		$this->assertStringContainsString( 'Low', $output );
		$this->assertStringContainsString( 'Create', $output );
		$this->assertStringContainsString( '2026-02-28 09:00:00', $output );
		// Empty message must NOT appear.
		$this->assertStringNotContainsString( 'not created via ConsensusPress', $output );
	}
}