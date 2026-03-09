<?php
/**
 * DNA Header
 *
 * File:         admin/views/diff.php
 * Version:      1.0.0
 * Purpose:      Read-only diff viewer template — original vs rescued post side-by-side
 * Author:       C-C (Session 07, Sprint 4)
 * Spec:         sprint_4_d1_d7_instructions.yaml D1 view_diff
 * PHP Version:  7.4+
 * Dependencies: post_meta _consensuspress_original_post_id on rescued draft
 * Reusable:     No — admin meta box view only
 *
 * @package ConsensusPress
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

global $post;

$original_post_id = (int) get_post_meta( $post->ID, '_consensuspress_original_post_id', true );

if ( ! $original_post_id ) {
	return;
}

$original_post = get_post( $original_post_id );

if ( ! $original_post ) {
	return;
}

$original_content = $original_post->post_content;
$rescued_content  = get_the_content();
?>
<div class="cp-diff-viewer">
	<div class="cp-diff-header">
		<div class="cp-diff-label">
			<?php esc_html_e( 'Original Post', 'consensuspress' ); ?>
			<span class="cp-diff-post-title"><?php echo esc_html( $original_post->post_title ); ?></span>
		</div>
		<div class="cp-diff-label">
			<?php esc_html_e( 'Rescued Draft', 'consensuspress' ); ?>
		</div>
	</div>
	<div class="cp-diff-panes">
		<div class="cp-diff-pane cp-diff-pane-original">
			<?php echo wp_kses_post( $original_content ); ?>
		</div>
		<div class="cp-diff-pane cp-diff-pane-rescued">
			<?php echo wp_kses_post( $rescued_content ); ?>
		</div>
	</div>
</div>
