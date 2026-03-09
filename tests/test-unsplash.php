<?php
/**
 * DNA Header
 *
 * File:         tests/test-unsplash.php
 * Version:      1.0.0
 * Purpose:      Unit tests for ConsensusPress_Post_Builder::fetch_unsplash_image()
 * Author:       C-C (Session 12, Sprint 8)
 * Spec:         sprint_8_d1_d7_instructions.yaml Phase 2 — Unsplash tests
 * PHP Version:  7.4+
 * Test Count:   8
 * Dependencies: bootstrap.php (wp_remote_get stub, cp_test_reset_globals)
 *
 * @package ConsensusPress
 */

use PHPUnit\Framework\TestCase;

/**
 * Tests for fetch_unsplash_image() via ConsensusPress_Post_Builder.
 *
 * fetch_unsplash_image() is private — accessed via Closure::bind() pattern.
 */
class ConsensusPress_Unsplash_Test extends TestCase {

	// =========================================================================
	// HELPERS
	// =========================================================================

	/**
	 * Return a Closure bound to a fresh post-builder instance that calls
	 * fetch_unsplash_image() with the given keyword.
	 *
	 * @param string $keyword Focus keyword.
	 * @return mixed Return value of fetch_unsplash_image().
	 */
	private function call_fetch( string $keyword ) {
		$builder = new ConsensusPress_Post_Builder();
		$closure = Closure::bind( function( $kw ) {
			return $this->fetch_unsplash_image( $kw );
		}, $builder, ConsensusPress_Post_Builder::class );
		return $closure( $keyword );
	}

	// =========================================================================
	// SETUP
	// =========================================================================

	protected function setUp(): void {
		cp_test_reset_globals();
	}

	// =========================================================================
	// TESTS
	// =========================================================================

	/**
	 * Returns empty array when no Unsplash key is configured.
	 */
	public function test_returns_empty_when_no_key(): void {
		// Ensure unsplash key absent.
		$GLOBALS['_cp_test_options']['consensuspress_unsplash_key'] = '';

		$result = $this->call_fetch( 'machine learning' );

		$this->assertSame( array(), $result );
		// wp_remote_get must NOT have been called.
		$this->assertEmpty( $GLOBALS['_cp_remote_get_calls'] );
	}

	/**
	 * Returns empty array when keyword is empty string.
	 */
	public function test_returns_empty_when_keyword_empty(): void {
		$GLOBALS['_cp_test_options']['consensuspress_unsplash_key'] = 'valid-test-key';

		$result = $this->call_fetch( '' );

		$this->assertSame( array(), $result );
		$this->assertEmpty( $GLOBALS['_cp_remote_get_calls'] );
	}

	/**
	 * Returns empty array when wp_remote_get returns WP_Error.
	 */
	public function test_returns_empty_on_wp_error(): void {
		$GLOBALS['_cp_test_options']['consensuspress_unsplash_key'] = 'valid-test-key';
		$GLOBALS['_cp_remote_get_fail']                             = true;

		$result = $this->call_fetch( 'machine learning' );

		$this->assertSame( array(), $result );
	}

	/**
	 * Returns empty array when HTTP status is not 200 (e.g. 401 Unauthorized).
	 */
	public function test_returns_empty_on_non_200_status(): void {
		$GLOBALS['_cp_test_options']['consensuspress_unsplash_key'] = 'valid-test-key';
		$GLOBALS['_cp_remote_get_status']                           = 401;

		$result = $this->call_fetch( 'machine learning' );

		$this->assertSame( array(), $result );
	}

	/**
	 * Returns empty array when response body has no urls.regular key.
	 */
	public function test_returns_empty_on_missing_url_in_body(): void {
		$GLOBALS['_cp_test_options']['consensuspress_unsplash_key'] = 'valid-test-key';
		$GLOBALS['_cp_remote_get_body']                             = json_encode( array( 'id' => 'abc' ) );

		$result = $this->call_fetch( 'machine learning' );

		$this->assertSame( array(), $result );
	}

	/**
	 * Returns correct array shape on successful response.
	 */
	public function test_returns_correct_shape_on_success(): void {
		$GLOBALS['_cp_test_options']['consensuspress_unsplash_key'] = 'valid-test-key';

		$result = $this->call_fetch( 'machine learning' );

		$this->assertArrayHasKey( 'url',         $result );
		$this->assertArrayHasKey( 'alt',         $result );
		$this->assertArrayHasKey( 'attribution', $result );
	}

	/**
	 * Returns correct url, alt and attribution values from mock response.
	 */
	public function test_returns_correct_values_from_response(): void {
		$GLOBALS['_cp_test_options']['consensuspress_unsplash_key'] = 'valid-test-key';

		$result = $this->call_fetch( 'machine learning' );

		$this->assertSame( 'https://images.unsplash.com/photo-test', $result['url'] );
		$this->assertSame( 'A test photo description',               $result['alt'] );
		$this->assertStringContainsString( 'Test Photographer',       $result['attribution'] );
		$this->assertStringContainsString( 'Unsplash',                $result['attribution'] );
	}

	/**
	 * Request URL contains keyword and client_id from Unsplash key.
	 */
	public function test_request_url_contains_keyword_and_key(): void {
		$GLOBALS['_cp_test_options']['consensuspress_unsplash_key'] = 'my-unsplash-key-123';

		$this->call_fetch( 'neural networks' );

		$this->assertNotEmpty( $GLOBALS['_cp_remote_get_calls'] );
		$call = $GLOBALS['_cp_remote_get_calls'][0];
		$this->assertStringContainsString( 'unsplash.com', $call['url'] );
		$this->assertStringContainsString( 'my-unsplash-key-123', $call['url'] );
		$this->assertStringContainsString( 'neural', $call['url'] );
	}
}
