<?php
/**
 * DNA Header
 *
 * File:         tests/test-settings.php
 * Version:      1.0.0
 * Purpose:      PHPUnit tests for ConsensusPress_Settings
 * Author:       C-C (Session 02, Sprint 1)
 * Spec:         sprint1_d1_d7.yaml D5
 * PHP Version:  7.4+
 * Dependencies: bootstrap.php
 * Reusable:     No — test file
 *
 * @package ConsensusPress
 */

declare(strict_types=1);

use PHPUnit\Framework\TestCase;

class Test_Settings extends TestCase {

	private ConsensusPress_Settings $settings;

	protected function setUp(): void {
		cp_test_reset_globals();
		$this->settings = new ConsensusPress_Settings();
	}

	public function test_get_api_key_returns_option_value(): void {
		$GLOBALS['_cp_test_options']['consensuspress_api_key'] = 'my-real-key';

		$this->assertSame( 'my-real-key', $this->settings->get_api_key() );
	}

	public function test_get_api_key_returns_empty_string_when_not_set(): void {
		unset( $GLOBALS['_cp_test_options']['consensuspress_api_key'] );

		$this->assertSame( '', $this->settings->get_api_key() );
	}

	public function test_get_post_status_returns_draft_by_default(): void {
		unset( $GLOBALS['_cp_test_options']['consensuspress_post_status'] );

		$this->assertSame( 'draft', $this->settings->get_post_status() );
	}

	public function test_get_post_status_returns_stored_value(): void {
		$GLOBALS['_cp_test_options']['consensuspress_post_status'] = 'publish';

		$this->assertSame( 'publish', $this->settings->get_post_status() );
	}
}
