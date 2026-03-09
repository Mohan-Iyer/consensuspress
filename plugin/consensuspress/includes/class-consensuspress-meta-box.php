<?php
/**
 * DNA Header
 *
 * File:         includes/class-consensuspress-meta-box.php
 * Version:      1.1.0
 * Purpose:      Consensus meta box on post edit screen — score, risk, mode display
 * Author:       C-C (Session 05, Sprint 3)
 * Spec:         sprint3_d1_d7.yaml D1 class_consensuspress_meta_box
 * PHP Version:  7.4+
 * Dependencies: class-consensuspress-post-builder.php (CONSENSUS_META_KEYS)
 * Reusable:     No — WordPress admin meta box handler
 *
 * @package ConsensusPress
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class ConsensusPress_Meta_Box
 *
 * Registers and renders a consensus data meta box on the post edit screen.
 * Reads from the 6 CONSENSUS_META_KEYS written by ConsensusPress_Post_Builder.
 */
class ConsensusPress_Meta_Box {

	/**
	 * Register the meta box hook.
	 *
	 * @return void
	 */
	public function init(): void {
		add_action( 'add_meta_boxes', array( $this, 'register_meta_box' ) );
	}

	/**
	 * Register the consensus meta box on the post edit screen.
	 *
	 * @return void
	 */
	public function register_meta_box(): void {
		add_meta_box(
			'consensuspress-consensus',
			__( 'ConsensusPress Data', 'consensuspress' ),
			array( $this, 'render_meta_box' ),
			'post',
			'side',
			'default'
		);
	}

	/**
	 * Render the consensus meta box.
	 *
	 * @param WP_Post $post Current post object.
	 * @return void
	 */
	public function render_meta_box( $post ): void {
		// BUG-10-03 fix: key names updated to match Sprint 7 post-builder v2.0.0.
		// Sprint 3 used 'champion', 'agreement', 'oracle_risk'.
		// Sprint 7 renamed to 'champion_provider', 'agreement_level', 'oracle_risk_level'.
		$score           = get_post_meta( $post->ID, ConsensusPress_Post_Builder::CONSENSUS_META_KEYS['score'],             true );
		$mode            = get_post_meta( $post->ID, ConsensusPress_Post_Builder::CONSENSUS_META_KEYS['mode'],              true );
		$champion        = get_post_meta( $post->ID, ConsensusPress_Post_Builder::CONSENSUS_META_KEYS['champion_provider'], true );
		$agreement_level = get_post_meta( $post->ID, ConsensusPress_Post_Builder::CONSENSUS_META_KEYS['agreement_level'],   true );
		$oracle_risk     = get_post_meta( $post->ID, ConsensusPress_Post_Builder::CONSENSUS_META_KEYS['oracle_risk_level'], true );
		$created_at      = get_post_meta( $post->ID, ConsensusPress_Post_Builder::CONSENSUS_META_KEYS['created_at'],        true );

		if ( empty( $score ) && empty( $mode ) ) {
			require_once CONSENSUSPRESS_PLUGIN_DIR . 'admin/views/meta-box-empty.php';
			return;
		}

		$score_class = $this->get_score_class( (int) round( (float) $score ) );

		require CONSENSUSPRESS_PLUGIN_DIR . 'admin/views/meta-box.php';
	}

	/**
	 * Return a CSS class string for a given consensus score integer.
	 *
	 * Extracted from the view template so tests can verify score classification
	 * logic independently of the template rendering.
	 *
	 * @param int $score Consensus score 0-100.
	 * @return string CSS class: 'cp-score-high' | 'cp-score-moderate' | 'cp-score-low'
	 */
	private function get_score_class( int $score ): string {
		if ( $score >= 80 ) {
			return 'cp-score-high';
		}
		if ( $score >= 60 ) {
			return 'cp-score-moderate';
		}
		return 'cp-score-low';
	}
}