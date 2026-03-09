<?php
/**
 * DNA Header
 *
 * File:         includes/class-consensuspress-settings.php
 * Version:      1.1.0
 * Purpose:      Settings page handler — API key input, post status option
 * Author:       C-C (Session 02, Sprint 1)
 * Spec:         sprint1_d1_d7.yaml D1 class_consensuspress_settings
 * PHP Version:  7.4+
 * Dependencies: WordPress admin functions
 * Reusable:     No — WordPress admin page handler
 *
 * @package ConsensusPress
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class ConsensusPress_Settings
 *
 * Registers the plugin top-level menu and settings page.
 */
class ConsensusPress_Settings {

	/**
	 * Register admin menu and settings hooks.
	 *
	 * @return void
	 */
	public function init(): void {
		add_action( 'admin_menu', array( $this, 'register_menu' ) );
		add_action( 'admin_init', array( $this, 'register_settings' ) );
	}

	/**
	 * Register the top-level admin menu and settings submenu.
	 *
	 * @return void
	 */
	public function register_menu(): void {
		add_menu_page(
			__( 'ConsensusPress', 'consensuspress' ),
			__( 'ConsensusPress', 'consensuspress' ),
			'manage_options',
			'consensuspress',
			array( $this, 'render_page' ),
			'dashicons-superhero',
			30
		);

		add_submenu_page(
			'consensuspress',
			__( 'Settings', 'consensuspress' ),
			__( 'Settings', 'consensuspress' ),
			'manage_options',
			'consensuspress',
			array( $this, 'render_page' )
		);
	}

	/**
	 * Register plugin settings with WordPress Settings API.
	 *
	 * @return void
	 */
	public function register_settings(): void {
		register_setting(
			'consensuspress_settings',
			'consensuspress_api_key',
			array(
				'sanitize_callback' => 'sanitize_text_field',
				'default'           => '',
			)
		);

		register_setting(
			'consensuspress_settings',
			'consensuspress_post_status',
			array(
				'sanitize_callback' => 'sanitize_text_field',
				'default'           => 'draft',
			)
		);

		register_setting(
			'consensuspress_settings',
			'consensuspress_unsplash_key',
			array(
				'sanitize_callback' => 'sanitize_text_field',
				'default'           => '',
			)
		);
	}

	/**
	 * Return current API key from wp_options.
	 *
	 * @return string API key or empty string.
	 */
	public function get_api_key(): string {
		return (string) get_option( 'consensuspress_api_key', '' );
	}

	/**
	 * Return current default post status from wp_options.
	 *
	 * @return string Post status slug ('draft' or 'publish').
	 */
	public function get_post_status(): string {
		return (string) get_option( 'consensuspress_post_status', 'draft' );
	}

	/**
	 * Return current Unsplash API key from wp_options.
	 *
	 * @return string Unsplash key or empty string.
	 */
	public function get_unsplash_key(): string {
		return (string) get_option( 'consensuspress_unsplash_key', '' );
	}

	/**
	 * Render Unsplash API key input field.
	 *
	 * @return void
	 */
	public function render_unsplash_key_field(): void {
		$key = get_option( 'consensuspress_unsplash_key', '' );
		echo '<input type="password" id="consensuspress_unsplash_key"
			name="consensuspress_unsplash_key"
			value="' . esc_attr( $key ) . '"
			class="regular-text" autocomplete="off" />';
		echo '<p class="description">' .
			esc_html__( 'Unsplash Access Key for featured image fetch. Leave empty to skip.', 'consensuspress' ) .
			'</p>';
	}

	/**
	 * Render the settings admin page.
	 *
	 * @return void
	 */
	public function render_page(): void {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}
		require_once CONSENSUSPRESS_PLUGIN_DIR . 'admin/views/settings.php';
	}
}
