<?php
/**
 * DNA Header
 * File:         admin/views/meta-box-empty.php
 * Version:      1.0.0
 * Purpose:      Consensus meta box empty state — shown on non-ConsensusPress posts
 * Author:       C-C (Session 05, Sprint 3)
 * Spec:         sprint3_d1_d7.yaml D1 admin_views_meta_box_empty
 * PHP Version:  7.4+
 * Dependencies: none
 * Reusable:     No — partial template, included by meta-box.php
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
?>
<p class="cp-meta-box-empty">
	<?php esc_html_e( 'No consensus data. This post was not created or rescued by ConsensusPress.', 'consensuspress' ); ?>
</p>
<p>
	<a href="<?php echo esc_url( admin_url( 'admin.php?page=consensuspress-create' ) ); ?>">
		<?php esc_html_e( 'Create a new AI-consensus post →', 'consensuspress' ); ?>
	</a>
</p>
