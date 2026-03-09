<?php
/**
 * DNA Header
 *
 * File:         tests/test-rescue.php
 * Version:      1.0.0
 * Purpose:      PHPUnit tests for ConsensusPress_Rescue and rescue AJAX handler
 * Author:       C-C (Session 07, Sprint 4)
 * Spec:         sprint_4_d1_d7_instructions.yaml D4 test scenarios
 * PHP Version:  7.4+
 * Dependencies: bootstrap.php
 * Reusable:     No — test file
 *
 * @package ConsensusPress
 */

declare(strict_types=1);

use PHPUnit\Framework\TestCase;

/**
 * Class Test_Rescue
 *
 * Tests for Rescue Mode: post extraction, schedule_rescue_job, AJAX handler.
 */
class Test_Rescue extends TestCase {

	/** @var ConsensusPress_Rescue */
	private ConsensusPress_Rescue $rescue;

	/** @var ConsensusPress_Async */
	private ConsensusPress_Async $async;

	protected function setUp(): void {
		cp_test_reset_globals();
		$this->rescue = new ConsensusPress_Rescue();
		$this->async  = new ConsensusPress_Async();
	}

	// =========================================================================
	// extract_post_content() tests
	// =========================================================================

	/**
	 * extract_post_content() returns empty string when post not found.
	 */
	public function test_extract_post_content_returns_empty_when_post_not_found(): void {
		// No post in _cp_post_override — get_post() returns null.
		$result = $this->rescue->extract_post_content( 999 );
		$this->assertSame( '', $result );
	}

	/**
	 * extract_post_content() returns empty string when post is not published.
	 */
	public function test_extract_post_content_returns_empty_for_draft_post(): void {
		$post             = new WP_Post( 1, 'Draft Post' );
		$post->post_status  = 'draft';
		$post->post_content = '<p>Draft content.</p>';
		$GLOBALS['_cp_post_override'][1] = $post;

		$result = $this->rescue->extract_post_content( 1 );
		$this->assertSame( '', $result );
	}

	/**
	 * extract_post_content() returns content for a published post.
	 */
	public function test_extract_post_content_returns_content_for_published_post(): void {
		$post               = new WP_Post( 2, 'Published Post' );
		$post->post_status  = 'publish';
		$post->post_content = '<p>Hello world.</p>';
		$GLOBALS['_cp_post_override'][2] = $post;

		$result = $this->rescue->extract_post_content( 2 );
		$this->assertSame( '<p>Hello world.</p>', $result );
	}

	/**
	 * extract_post_content() truncates content at MAX_CONTENT_LENGTH.
	 */
	public function test_extract_post_content_truncates_at_max_length(): void {
		$long_content = str_repeat( 'a', 60000 );
		$post               = new WP_Post( 3, 'Long Post' );
		$post->post_status  = 'publish';
		$post->post_content = $long_content;
		$GLOBALS['_cp_post_override'][3] = $post;

		$result = $this->rescue->extract_post_content( 3 );
		$this->assertSame( ConsensusPress_Rescue::MAX_CONTENT_LENGTH, strlen( $result ) );
	}

	// =========================================================================
	// get_published_posts() tests
	// =========================================================================

	/**
	 * get_published_posts() returns empty array when no posts.
	 */
	public function test_get_published_posts_returns_empty_array_when_no_posts(): void {
		$GLOBALS['_cp_mock_posts'] = array();
		$result = $this->rescue->get_published_posts();
		$this->assertSame( array(), $result );
	}

	/**
	 * get_published_posts() returns id => title map.
	 */
	public function test_get_published_posts_returns_id_title_map(): void {
		$post1 = new WP_Post( 10, 'First Post' );
		$post2 = new WP_Post( 20, 'Second Post' );
		$GLOBALS['_cp_mock_posts'] = array( $post1, $post2 );

		$result = $this->rescue->get_published_posts();
		$this->assertSame( array( 10 => 'First Post', 20 => 'Second Post' ), $result );
	}

	// =========================================================================
	// schedule_rescue_job() tests
	// =========================================================================

	/**
	 * schedule_rescue_job() returns a non-empty string UUID.
	 */
	public function test_schedule_rescue_job_returns_uuid(): void {
		$job_id = $this->async->schedule_rescue_job( 1, '<p>test content</p>' );
		$this->assertIsString( $job_id );
		$this->assertNotEmpty( $job_id );
	}

	/**
	 * schedule_rescue_job() stores correct job data in transient.
	 */
	public function test_schedule_rescue_job_stores_correct_job_data(): void {
		$job_id = $this->async->schedule_rescue_job( 42, '<p>rescue me</p>' );

		$job_data = get_transient( ConsensusPress_Async::JOB_TRANSIENT_PREFIX . $job_id );

		$this->assertSame( 'pending', $job_data['status'] );
		$this->assertSame( 'rescue', $job_data['mode'] );
		$this->assertSame( 42, $job_data['post_id'] );
		$this->assertSame( '<p>rescue me</p>', $job_data['content_html'] );
	}
}
