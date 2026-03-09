<?php
/**
 * DNA Header
 *
 * File:         admin/views/rescue.php
 * Version:      1.0.0
 * Purpose:      Rescue Mode admin page template — post selector dropdown
 * Author:       C-C (Session 07, Sprint 4)
 * Spec:         sprint_4_d1_d7_instructions.yaml D1 view_rescue
 * PHP Version:  7.4+
 * Dependencies: class-consensuspress-rescue.php (provides $posts)
 * Reusable:     No — admin view only
 *
 * @package ConsensusPress
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
?>
<div class="wrap">
	<h1><?php esc_html_e( 'Rescue Post', 'consensuspress' ); ?></h1>
	<p><?php esc_html_e( 'Select a published post to restructure for AI-search survival. A new draft will be created — the original post is never modified.', 'consensuspress' ); ?></p>

	<div class="consensuspress-rescue-form">
		<table class="form-table">
			<tr>
				<th scope="row">
					<label for="cp-rescue-post-select"><?php esc_html_e( 'Select Post', 'consensuspress' ); ?></label>
				</th>
				<td>
					<select id="cp-rescue-post-select" name="post_id">
						<option value="0"><?php esc_html_e( '— Choose a post —', 'consensuspress' ); ?></option>
						<?php foreach ( $posts as $post_id => $post_title ) : ?>
							<option value="<?php echo esc_attr( (string) $post_id ); ?>">
								<?php echo esc_html( $post_title ); ?>
							</option>
						<?php endforeach; ?>
					</select>
				</td>
			</tr>
		</table>

		<p class="submit">
			<button type="button" id="cp-rescue-submit" class="button button-primary">
				<?php esc_html_e( 'Rescue Post', 'consensuspress' ); ?>
			</button>
		</p>

		<div id="cp-rescue-status" class="consensuspress-rescue-status" style="display:none;"></div>
	</div>
</div>
