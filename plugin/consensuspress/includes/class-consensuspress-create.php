<?php
/**
 * DNA Header
 *
 * File:         includes/class-consensuspress-create.php
 * Version:      1.0.0
 * Purpose:      Create Mode admin page handler — submenu, assets, render
 * Author:       C-C (Session 03, Sprint 2)
 * Spec:         sprint2_d1_d7.yaml D1 class_consensuspress_create
 * PHP Version:  7.4+
 * Dependencies: class-consensuspress-api.php, class-consensuspress-post-builder.php
 * Reusable:     No — WordPress admin page handler
 *
 * @package ConsensusPress
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class ConsensusPress_Create
 *
 * Handles the Create Post admin page — submenu registration, asset enqueueing,
 * and page rendering.
 */
class ConsensusPress_Create {

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
	 * Register the Create Post submenu under the ConsensusPress menu.
	 *
	 * @return void
	 */
	public function register_submenu(): void {
		add_submenu_page(
			'consensuspress',
			__( 'Create Post', 'consensuspress' ),
			__( 'Create Post', 'consensuspress' ),
			'edit_posts',
			'consensuspress-create',
			array( $this, 'render_page' )
		);
	}

	/**
	 * Enqueue Create page JS and pass localised data.
	 *
	 * @param string $hook Current admin page hook suffix.
	 * @return void
	 */
	public function enqueue_assets( string $hook ): void {
		if ( false === strpos( $hook, 'consensuspress-create' ) ) {
			return;
		}

		wp_enqueue_script(
			'consensuspress-create',
			CONSENSUSPRESS_PLUGIN_URL . 'admin/js/consensuspress-create.js',
			array( 'jquery' ),
			CONSENSUSPRESS_VERSION,
			true
		);

		wp_localize_script(
			'consensuspress-create',
			'consensuspressCreate',
			array(
				'ajax_url'  => admin_url( 'admin-ajax.php' ),
				'nonce'     => wp_create_nonce( 'consensuspress_create_post' ),
				'pollNonce' => wp_create_nonce( 'consensuspress_poll_job' ),
				'i18n'      => array(
					'processing' => __( 'Creating post… this takes 30–60 seconds.', 'consensuspress' ),
					'complete'   => __( 'Post created! Redirecting to draft…', 'consensuspress' ),
					'failed'     => __( 'Creation failed. Please try again.', 'consensuspress' ),
					'timeout'    => __( 'Timed out. Please check your drafts.', 'consensuspress' ),
				),
			)
		);
	}

	/**
	 * Render the Create Post admin page.
	 *
	 * @return void
	 */
	public function render_page(): void {
		if ( ! current_user_can( 'edit_posts' ) ) {
			return;
		}
		require_once CONSENSUSPRESS_PLUGIN_DIR . 'admin/views/create.php';
	}
}
