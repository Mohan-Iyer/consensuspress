<?php
/**
 * DNA Header
 *
 * File:         tests/test-usage.php
 * Version:      1.0.0
 * Purpose:      PHPUnit tests for ConsensusPress_Usage (Sprint 5 — 8 tests)
 * Author:       C-C (Session 07, Sprint 5)
 * Spec:         sprint_5_d1_d7_instructions.yaml D5 test_scenarios
 * PHP version:  7.4+
 * Dependencies: tests/bootstrap.php (v1.4.0), class-consensuspress-usage.php
 * Reusable:     No — test file
 *
 * @package ConsensusPress
 */

use PHPUnit\Framework\TestCase;

/**
 * Class ConsensusPress_Usage_Test
 *
 * Covers: ConsensusPress_Usage — 8 test scenarios.
 * Total after Sprint 5: 55 tests.
 */
class ConsensusPress_Usage_Test extends TestCase {

	/**
	 * Reset all globals before each test.
	 */
	protected function setUp(): void {
		cp_test_reset_globals();
	}

	// -------------------------------------------------------------------------
	// Test 1: Default usage on fresh install
	// -------------------------------------------------------------------------

	/**
	 * Fresh install returns free tier defaults.
	 *
	 * D5: test_default_usage_is_free_tier
	 * Setup: No _cp_usage_option seeded — get_option returns empty array.
	 */
	public function test_default_usage_is_free_tier(): void {
		// _cp_usage_option not seeded → get_stored_usage applies defaults.
		$usage = new ConsensusPress_Usage();
		$data  = $usage->get_usage();

		$this->assertSame( 0, $data['credits_used'] );
		$this->assertSame( 3, $data['credits_limit'] );
		$this->assertSame( 'free', $data['tier'] );
		$this->assertArrayHasKey( 'reset_date', $data );
		$this->assertArrayHasKey( 'credits_remaining', $data );
		$this->assertSame( 3, $data['credits_remaining'] );
	}

	// -------------------------------------------------------------------------
	// Test 2: record_usage create = +1 credit
	// -------------------------------------------------------------------------

	/**
	 * record_usage('create') increments credits_used by 1.
	 *
	 * D5: test_record_usage_create_increments_by_one
	 * Setup: credits_used=2, credits_limit=3, tier='free'
	 */
	public function test_record_usage_create_increments_by_one(): void {
		$GLOBALS['_cp_usage_option'] = array(
			'credits_used'  => 2,
			'credits_limit' => 3,
			'tier'          => 'free',
			'reset_date'    => date( 'Y-m-01 00:00:00', strtotime( 'first day of next month' ) ),
			'last_updated'  => date( 'Y-m-d H:i:s' ),
		);

		$usage = new ConsensusPress_Usage();
		$usage->record_usage( 'create' );
		$data = $usage->get_usage();

		$this->assertSame( 3, $data['credits_used'] );
	}

	// -------------------------------------------------------------------------
	// Test 3: record_usage rescue = +2 credits
	// -------------------------------------------------------------------------

	/**
	 * record_usage('rescue') increments credits_used by 2.
	 *
	 * D5: test_record_usage_rescue_increments_by_two
	 * Setup: credits_used=0, credits_limit=10, tier='starter'
	 */
	public function test_record_usage_rescue_increments_by_two(): void {
		$GLOBALS['_cp_usage_option'] = array(
			'credits_used'  => 0,
			'credits_limit' => 10,
			'tier'          => 'starter',
			'reset_date'    => date( 'Y-m-01 00:00:00', strtotime( 'first day of next month' ) ),
			'last_updated'  => date( 'Y-m-d H:i:s' ),
		);

		$usage = new ConsensusPress_Usage();
		$usage->record_usage( 'rescue' );
		$data = $usage->get_usage();

		$this->assertSame( 2, $data['credits_used'] );
	}

	// -------------------------------------------------------------------------
	// Test 4: check_quota allowed when credits remain
	// -------------------------------------------------------------------------

	/**
	 * check_quota('create') returns allowed=true when credits remain.
	 *
	 * D5: test_check_quota_allowed_when_credits_remain
	 * Setup: credits_used=2, credits_limit=10, tier='starter'
	 */
	public function test_check_quota_allowed_when_credits_remain(): void {
		$GLOBALS['_cp_usage_option'] = array(
			'credits_used'  => 2,
			'credits_limit' => 10,
			'tier'          => 'starter',
			'reset_date'    => date( 'Y-m-01 00:00:00', strtotime( 'first day of next month' ) ),
			'last_updated'  => date( 'Y-m-d H:i:s' ),
		);

		$usage = new ConsensusPress_Usage();
		$quota = $usage->check_quota( 'create' );

		$this->assertTrue( $quota['allowed'] );
		$this->assertSame( 8, $quota['credits_remaining'] );
		$this->assertSame( '', $quota['message'] );
	}

	// -------------------------------------------------------------------------
	// Test 5: check_quota blocked when exhausted
	// -------------------------------------------------------------------------

	/**
	 * check_quota('create') returns allowed=false when at limit.
	 *
	 * D5: test_check_quota_blocked_when_exhausted
	 * Setup: credits_used=10, credits_limit=10, tier='starter'
	 */
	public function test_check_quota_blocked_when_exhausted(): void {
		$GLOBALS['_cp_usage_option'] = array(
			'credits_used'  => 10,
			'credits_limit' => 10,
			'tier'          => 'starter',
			'reset_date'    => date( 'Y-m-01 00:00:00', strtotime( 'first day of next month' ) ),
			'last_updated'  => date( 'Y-m-d H:i:s' ),
		);

		$usage = new ConsensusPress_Usage();
		$quota = $usage->check_quota( 'create' );

		$this->assertFalse( $quota['allowed'] );
		$this->assertSame( 0, $quota['credits_remaining'] );
		$this->assertNotEmpty( $quota['message'] );
	}

	// -------------------------------------------------------------------------
	// Test 6: check_quota rescue blocked with only 1 credit remaining
	// -------------------------------------------------------------------------

	/**
	 * Rescue blocked when only 1 credit remains (rescue costs 2).
	 *
	 * D5: test_check_quota_rescue_needs_two_credits
	 * Setup: credits_used=9, credits_limit=10, tier='starter'
	 *        Remaining = 1. Rescue cost = 2. 1 < 2 → blocked.
	 */
	public function test_check_quota_rescue_needs_two_credits(): void {
		$GLOBALS['_cp_usage_option'] = array(
			'credits_used'  => 9,
			'credits_limit' => 10,
			'tier'          => 'starter',
			'reset_date'    => date( 'Y-m-01 00:00:00', strtotime( 'first day of next month' ) ),
			'last_updated'  => date( 'Y-m-d H:i:s' ),
		);

		$usage = new ConsensusPress_Usage();
		$quota = $usage->check_quota( 'rescue' );

		// 10 - 9 = 1 remaining, rescue costs 2 → not allowed.
		$this->assertFalse( $quota['allowed'] );
	}

	// -------------------------------------------------------------------------
	// Test 7: handle_quota_exceeded marks as exhausted
	// -------------------------------------------------------------------------

	/**
	 * handle_quota_exceeded() sets credits_used = credits_limit.
	 *
	 * D5: test_handle_quota_exceeded_marks_as_exhausted
	 * Setup: credits_used=5, credits_limit=10
	 */
	public function test_handle_quota_exceeded_marks_as_exhausted(): void {
		$GLOBALS['_cp_usage_option'] = array(
			'credits_used'  => 5,
			'credits_limit' => 10,
			'tier'          => 'starter',
			'reset_date'    => date( 'Y-m-01 00:00:00', strtotime( 'first day of next month' ) ),
			'last_updated'  => date( 'Y-m-d H:i:s' ),
		);

		$result = array(
			'success'     => false,
			'data'        => null,
			'error'       => array( 'code' => 'usage_exceeded', 'message' => 'Limit reached.' ),
			'http_status' => 402,
		);

		$usage = new ConsensusPress_Usage();
		$usage->handle_quota_exceeded( $result );
		$data = $usage->get_usage();

		// credits_used must equal credits_limit (exhausted).
		$this->assertSame( $data['credits_limit'], $data['credits_used'] );
	}

	// -------------------------------------------------------------------------
	// Test 8: auto-reset when past reset_date
	// -------------------------------------------------------------------------

	/**
	 * Credits reset to 0 when reset_date is in the past.
	 *
	 * D5: test_auto_reset_when_past_reset_date
	 * Setup: credits_used=8, credits_limit=10, reset_date well in the past.
	 */
	public function test_auto_reset_when_past_reset_date(): void {
		$GLOBALS['_cp_usage_option'] = array(
			'credits_used'  => 8,
			'credits_limit' => 10,
			'tier'          => 'starter',
			'reset_date'    => '2020-01-01 00:00:00', // well in the past
			'last_updated'  => '2020-01-01 00:00:00',
		);

		$usage = new ConsensusPress_Usage();
		$data  = $usage->get_usage();

		// Auto-reset triggered: credits_used should be 0.
		$this->assertSame( 0, $data['credits_used'] );
		// reset_date must have advanced past 2020.
		$this->assertGreaterThan( strtotime( '2020-01-01' ), strtotime( $data['reset_date'] ) );
	}
}
