<?php
/**
 * DNA Header
 * File:         uninstall.php
 * Version:      1.0.0
 * Purpose:      Clean up all plugin data on uninstall (wp_options, post_meta keys)
 * Author:       C-C (Session 07, Sprint 6)
 * Spec:         WordPress.org requirement — runs on plugin deletion (not deactivation)
 * PHP Version:  7.4+
 * Dependencies: WordPress core
 * Reusable:     No — plugin uninstall hook
 */

// Prevent direct file access and non-uninstall calls.
if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
	exit;
}

// =============================================================================
// OPTION CLEANUP
// Remove all plugin options from wp_options table.
// =============================================================================

delete_option( 'consensuspress_api_key' );
delete_option( 'consensuspress_post_status' );
delete_option( 'consensuspress_usage' );

// =============================================================================
// POST META CLEANUP
// Remove consensus metadata from all posts that have it.
// Covers: consensus score, oracle risk, mode, original_post_id (rescue).
// =============================================================================

$meta_keys = array(
	'_consensuspress_consensus_score',
	'_consensuspress_oracle_risk',
	'_consensuspress_verdict',
	'_consensuspress_mode',
	'_consensuspress_original_post_id',
	'_consensuspress_focus_keyword',
	'_consensuspress_meta_description',
	'_consensuspress_schema_faq',
	'_consensuspress_schema_article',
);

foreach ( $meta_keys as $meta_key ) {
	delete_post_meta_by_key( $meta_key );
}
