<?php
/**
 * DNA Header
 *
 * File:         includes/class-consensuspress-rescue.php
 * Version:      1.0.0
 * Purpose:      Rescue mode page handler — post selector, content extraction, asset enqueue
 * Author:       C-C (Session 07, Sprint 4)
 * Spec:         sprint_4_d1_d7_instructions.yaml D1 class_consensuspress_rescue
 * PHP Version:  7.4+
 * Dependencies: class-consensuspress-async.php
 * Reusable:     No — WordPress admin page handler
 *
 * @package ConsensusPress
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class ConsensusPress_Rescue
 *
 * Handles the Rescue Mode admin page.
 * Registers submenu, enqueues assets, provides post selector data,
 * and extracts post content for API submission.
 */
class ConsensusPress_Rescue {

	/**
	 * Maximum content length submitted to Seekrates API.
	 * Prevents oversized payloads. 50k chars ≈ 7,000 words.
	 *
	 * @var int
	 */
	const MAX_CONTENT_LENGTH = 50000;

	/**
	 * Register admin submenu and asset hooks.
	 *
	 * @return void
	 */
	public function init(): void {
		add_action( 'admin_menu', array( $this, 'register_submenu' ) );
		add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_assets' ) );
	}

	/**
	 * Register the Rescue Mode submenu page under the ConsensusPress menu.
	 *
	 * @return void
	 */
	public function register_submenu(): void {
		add_submenu_page(
			'consensuspress',
			__( 'Rescue Post', 'consensuspress' ),
			__( 'Rescue Post', 'consensuspress' ),
			'edit_posts',
			'consensuspress-rescue',
			array( $this, 'render_page' )
		);
	}

	/**
	 * Enqueue Rescue page assets on the correct screen.
	 *
	 * @param string $hook Current admin page hook suffix.
	 * @return void
	 */
	public function enqueue_assets( string $hook ): void {
		if ( false === strpos( $hook, 'consensuspress-rescue' ) ) {
			return;
		}

		wp_enqueue_script(
			'consensuspress-rescue',
			CONSENSUSPRESS_PLUGIN_URL . 'admin/js/consensuspress-rescue.js',
			array( 'jquery' ),
			CONSENSUSPRESS_VERSION,
			true
		);

		wp_localize_script(
			'consensuspress-rescue',
			'consensuspressRescue',
			array(
				'ajax_url'  => admin_url( 'admin-ajax.php' ),
				'nonce'     => wp_create_nonce( 'consensuspress_rescue_post' ),
				'pollNonce' => wp_create_nonce( 'consensuspress_poll_rescue_job' ),
				'i18n'      => array(
					'select_post' => __( 'Please select a post to rescue.', 'consensuspress' ),
					'processing'  => __( 'Rescue in progress… this takes 30–60 seconds.', 'consensuspress' ),
					'complete'    => __( 'Rescue complete! Redirecting to draft…', 'consensuspress' ),
					'failed'      => __( 'Rescue failed. Please try again.', 'consensuspress' ),
					'timeout'     => __( 'Timed out. Please check your drafts.', 'consensuspress' ),
				),
			)
		);
	}

	/**
	 * Render the Rescue Mode admin page.
	 *
	 * @return void
	 */
	public function render_page(): void {
		if ( ! current_user_can( 'edit_posts' ) ) {
			return;
		}
		$posts = $this->get_published_posts();
		require_once CONSENSUSPRESS_PLUGIN_DIR . 'admin/views/rescue.php';
	}

	/**
	 * Return published posts for the rescue post selector dropdown.
	 *
	 * @return array<int, string> Associative array: post_id => post_title
	 */
	public function get_published_posts(): array {
		$posts = get_posts( array(
			'post_status'    => 'publish',
			'posts_per_page' => 100,
			'orderby'        => 'date',
			'order'          => 'DESC',
		) );

		$result = array();
		foreach ( $posts as $post ) {
			$result[ $post->ID ] = $post->post_title;
		}
		return $result;
	}

	/**
	 * Extract and sanitise post content HTML for API submission.
	 *
	 * Strips shortcodes. Truncates to MAX_CONTENT_LENGTH chars.
	 * Returns empty string if post not found or not published.
	 *
	 * @param int $post_id WordPress post ID to extract content from.
	 * @return string Sanitised content HTML, max 50,000 chars, or empty string on failure.
	 */
	public function extract_post_content( int $post_id ): string {
		$post = get_post( $post_id );
		if ( ! $post || 'publish' !== $post->post_status ) {
			return '';
		}

		$content = strip_shortcodes( $post->post_content );
		$content = wp_kses_post( $content );

		if ( strlen( $content ) > self::MAX_CONTENT_LENGTH ) {
			$content = substr( $content, 0, self::MAX_CONTENT_LENGTH );
		}

		return $content;
	}
}
