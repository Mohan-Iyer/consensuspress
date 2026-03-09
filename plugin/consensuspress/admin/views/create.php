<?php
/**
 * DNA Header
 * File:         admin/views/create.php
 * Version:      1.0.0
 * Purpose:      Create Post admin page template — topic input form
 * Author:       C-C (Session 03, Sprint 2)
 * Spec:         sprint2_d1_d7.yaml D1 admin_views_create
 * PHP Version:  7.4+
 * Dependencies: WordPress admin functions, consensuspress-create.js
 * Reusable:     No — template
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
?>
<div class="wrap">
	<h1><?php esc_html_e( 'Create AI-Consensus Post', 'consensuspress' ); ?></h1>

	<p class="description">
		<?php esc_html_e( 'Enter a topic and optional context. ConsensusPress will query 5 AI models simultaneously, cross-validate responses, and create a WordPress draft when consensus is reached.', 'consensuspress' ); ?>
	</p>

	<div id="consensuspress-create-form" class="consensuspress-create-form">
		<table class="form-table" role="presentation">
			<tr>
				<th scope="row">
					<label for="cp-topic">
						<?php esc_html_e( 'Topic', 'consensuspress' ); ?>
						<span class="required" aria-hidden="true">*</span>
					</label>
				</th>
				<td>
					<input
						type="text"
						id="cp-topic"
						name="topic"
						class="topic-field large-text"
						maxlength="500"
						placeholder="<?php esc_attr_e( 'e.g. The future of AI in healthcare', 'consensuspress' ); ?>"
						required
					/>
					<p class="description">
						<?php esc_html_e( 'Minimum 10 characters. Be specific for best consensus quality.', 'consensuspress' ); ?>
					</p>
				</td>
			</tr>
			<tr>
				<th scope="row">
					<label for="cp-context">
						<?php esc_html_e( 'Context', 'consensuspress' ); ?>
						<span class="optional"><?php esc_html_e( '(optional)', 'consensuspress' ); ?></span>
					</label>
				</th>
				<td>
					<textarea
						id="cp-context"
						name="context"
						class="context-field large-text"
						maxlength="2000"
						rows="4"
						placeholder="<?php esc_attr_e( 'Additional context, target audience, tone, or specific angles to cover…', 'consensuspress' ); ?>"
					></textarea>
					<p class="description">
						<?php esc_html_e( 'Optional. Helps the consensus engine focus on your specific requirements.', 'consensuspress' ); ?>
					</p>
				</td>
			</tr>
		</table>

		<p class="submit">
			<button type="button" id="cp-create-submit" class="button button-primary button-large">
				<?php esc_html_e( 'Generate Consensus Draft', 'consensuspress' ); ?>
			</button>
		</p>
	</div>

	<div id="consensuspress-create-status" class="consensuspress-create-status" style="display:none;">
		<span class="spinner is-active" style="float:left; margin-right: 10px;"></span>
		<span id="cp-status-message"></span>
	</div>
</div>
