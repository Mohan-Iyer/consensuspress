<?php
/**
 * DNA Header
 * File:         admin/views/meta-box.php
 * Version:      1.0.0
 * Purpose:      Consensus meta box template — displays score, risk, verdict on post edit screen
 * Author:       C-C (Session 05, Sprint 3)
 * Spec:         sprint3_d1_d7.yaml D1 admin_views_meta_box
 * PHP Version:  7.4+
 * Dependencies: WordPress admin functions, post meta written by ConsensusPress_Post_Builder
 * Reusable:     No — template, loaded by ConsensusPress_Meta_Box::render_meta_box()
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Template variables provided by ConsensusPress_Meta_Box::render_meta_box():
 *
 * @var int    $post_id         Current post ID.
 * @var string $score           Consensus score (0-100) or empty string.
 * @var string $mode            'create' | 'rescue' or empty string.
 * @var string $champion        Champion provider name or empty string.
 * @var string $agreement_level 'HIGH' | 'MODERATE' | 'LOW' or empty string.
 * @var string $oracle_risk     Risk level or empty string.
 * @var string $created_at      MySQL datetime or empty string.
 */

// Score CSS class.
$score_int = (int) $score;
if ( $score_int >= 80 ) {
	$score_class = 'cp-score-high';
} elseif ( $score_int >= 60 ) {
	$score_class = 'cp-score-moderate';
} else {
	$score_class = 'cp-score-low';
}
?>
<div class="cp-meta-box">

	<?php if ( ! empty( $score ) ) : ?>

		<div class="cp-row">
			<span class="cp-label"><?php esc_html_e( 'Consensus Score', 'consensuspress' ); ?></span>
			<span class="cp-value <?php echo esc_attr( $score_class ); ?>">
				<?php echo esc_html( $score ); ?>/100
			</span>
		</div>

		<?php if ( ! empty( $agreement_level ) ) : ?>
		<div class="cp-row">
			<span class="cp-label"><?php esc_html_e( 'Agreement', 'consensuspress' ); ?></span>
			<span class="cp-value"><?php echo esc_html( $agreement_level ); ?></span>
		</div>
		<?php endif; ?>

		<?php if ( ! empty( $oracle_risk ) ) : ?>
		<div class="cp-row">
			<span class="cp-label"><?php esc_html_e( 'Oracle Risk', 'consensuspress' ); ?></span>
			<span class="cp-value"><?php echo esc_html( ucfirst( $oracle_risk ) ); ?></span>
		</div>
		<?php endif; ?>

		<?php if ( ! empty( $champion ) ) : ?>
		<div class="cp-row">
			<span class="cp-label"><?php esc_html_e( 'Champion', 'consensuspress' ); ?></span>
			<span class="cp-value"><?php echo esc_html( $champion ); ?></span>
		</div>
		<?php endif; ?>

		<?php if ( ! empty( $mode ) ) : ?>
		<div class="cp-row">
			<span class="cp-label"><?php esc_html_e( 'Mode', 'consensuspress' ); ?></span>
			<span class="cp-value"><?php echo esc_html( ucfirst( $mode ) ); ?></span>
		</div>
		<?php endif; ?>

		<?php if ( ! empty( $created_at ) ) : ?>
		<div class="cp-row">
			<span class="cp-label"><?php esc_html_e( 'Generated', 'consensuspress' ); ?></span>
			<span class="cp-value"><?php echo esc_html( date_i18n( get_option( 'date_format' ), strtotime( $created_at ) ) ); ?></span>
		</div>
		<?php endif; ?>

	<?php else : ?>

		<?php require CONSENSUSPRESS_PLUGIN_DIR . 'admin/views/meta-box-empty.php'; ?>

	<?php endif; ?>

</div>
