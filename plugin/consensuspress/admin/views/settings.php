<?php
/**
 * DNA Header
 * File:         admin/views/settings.php
 * Version:      1.0.0
 * Purpose:      Settings page template — API key input + connection test
 * Author:       C-C (Session 02, Sprint 1)
 * Spec:         sprint1_d1_d7.yaml D1 admin_views_settings
 * PHP Version:  7.4+
 * Dependencies: WordPress admin functions, consensuspress-admin.js
 * Reusable:     No — template
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
?>
<div class="wrap">
	<h1><?php esc_html_e( 'ConsensusPress Settings', 'consensuspress' ); ?></h1>

	<div class="consensuspress-settings-section">
		<form method="post" action="options.php">
			<?php settings_fields( 'consensuspress_settings' ); ?>
			<?php do_settings_sections( 'consensuspress' ); ?>

			<table class="form-table" role="presentation">
				<tr>
					<th scope="row">
						<label for="consensuspress_api_key">
							<?php esc_html_e( 'Seekrates AI API Key', 'consensuspress' ); ?>
						</label>
					</th>
					<td>
						<input
							type="password"
							id="consensuspress_api_key"
							name="consensuspress_api_key"
							value="<?php echo esc_attr( get_option( 'consensuspress_api_key', '' ) ); ?>"
							class="regular-text"
							autocomplete="off"
						/>
						<p class="description">
							<?php
							printf(
								/* translators: %s = URL to Seekrates pricing page */
								esc_html__( 'Get your API key at %s', 'consensuspress' ),
								'<a href="' . esc_url( 'https://seekrates-ai.com/pricing' ) . '" target="_blank" rel="noopener noreferrer">seekrates-ai.com/pricing</a>'
							);
							?>
						</p>
					</td>
				</tr>
				<tr>
					<th scope="row">
						<label for="consensuspress_post_status">
							<?php esc_html_e( 'Default Post Status', 'consensuspress' ); ?>
						</label>
					</th>
					<td>
						<select id="consensuspress_post_status" name="consensuspress_post_status">
							<option value="draft" <?php selected( get_option( 'consensuspress_post_status', 'draft' ), 'draft' ); ?>>
								<?php esc_html_e( 'Draft', 'consensuspress' ); ?>
							</option>
							<option value="pending" <?php selected( get_option( 'consensuspress_post_status', 'draft' ), 'pending' ); ?>>
								<?php esc_html_e( 'Pending Review', 'consensuspress' ); ?>
							</option>
						</select>
						<p class="description">
							<?php esc_html_e( 'Status assigned to newly created AI-consensus posts.', 'consensuspress' ); ?>
						</p>
					</td>
				</tr>
			</table>

			<?php submit_button( __( 'Save Settings', 'consensuspress' ) ); ?>
		</form>
	</div>

	<div class="consensuspress-settings-section">
		<h2><?php esc_html_e( 'Test Connection', 'consensuspress' ); ?></h2>
		<p><?php esc_html_e( 'Verify your API key connects to the Seekrates AI consensus engine.', 'consensuspress' ); ?></p>
		<button type="button" id="consensuspress-test-connection" class="button button-secondary">
			<?php esc_html_e( 'Test Connection', 'consensuspress' ); ?>
		</button>
		<div id="consensuspress-test-result" style="display:none; margin-top: 10px;"></div>
	</div>
</div>
