<?php
if ( ! defined( 'ABSPATH' ) ) { exit; }
/**
 * DNA Header
 *
 * File:         includes/class-consensuspress-usage.php
 * Version:      1.0.1
 * Purpose:      Credit-based usage tracking and tier enforcement.
 *               Stores state in wp_options. Auto-resets monthly on read.
 *               Soft gate via check_quota(). Hard enforcement via handle_quota_exceeded() on 402.
 * Author:       C-C (Session 07, Sprint 5)
 * Spec:         sprint_5_d1_d7_instructions.yaml D1 class_consensuspress_usage
 * PHP version:  7.4+
 * Dependencies: WordPress core (get_option, update_option, add_action, current_time)
 * Reusable:     Yes — called by consensuspress.php (quota gate) and class-consensuspress-async.php
 *
 * @package ConsensusPress
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class ConsensusPress_Usage
 *
 * Credit-based usage tracking and tier enforcement.
 * All state persisted to wp_options under OPTION_KEY.
 * Monthly reset is applied automatically on every read (auto-reset-on-read pattern).
 *
 * Architecture decision D-05-01: auto-reset-on-read over WP-Cron.
 * Architecture decision D-05-02: default tier = 'free' (3 posts/month).
 * Architecture decision D-05-03: rescue = 2 credits (2x pricing alignment).
 */
class ConsensusPress_Usage {

	// -------------------------------------------------------------------------
	// Constants
	// -------------------------------------------------------------------------

	/**
	 * wp_options key for serialised usage state.
	 *
	 * @var string
	 */
	const OPTION_KEY = 'consensuspress_usage';

	/**
	 * Credits debited per operation. Rescue costs 2x per HLR pricing.
	 *
	 * @var array<string, int>
	 */
	const CREDIT_COST = array(
		'create' => 1,
		'rescue' => 2,
	);

	/**
	 * Tier definitions. Limit = monthly credit allowance.
	 *
	 * @var array<string, array{label: string, limit: int}>
	 */
	const TIERS = array(
		'free'       => array( 'label' => 'Free',        'limit' => 3      ),
		'starter'    => array( 'label' => 'Starter',     'limit' => 10     ),
		'pro'        => array( 'label' => 'Pro',         'limit' => 30     ),
		'business'   => array( 'label' => 'Business',    'limit' => 100    ),
		'agency'     => array( 'label' => 'Agency',      'limit' => 50     ),
		'enterprise' => array( 'label' => 'Enterprise',  'limit' => 200    ),
		'unlimited'  => array( 'label' => 'Unlimited',   'limit' => 999999 ),
	);

	/**
	 * Admin notice appears when credits_used / credits_limit >= WARN_THRESHOLD.
	 *
	 * @var float
	 */
	const WARN_THRESHOLD = 0.80;

	// -------------------------------------------------------------------------
	// Public Methods
	// -------------------------------------------------------------------------

	/**
	 * Register admin notice hook for quota warnings on ConsensusPress pages.
	 *
	 * @return void
	 */
	public function init(): void {
		add_action( 'admin_notices', array( $this, 'render_quota_notice' ) );
	}

	/**
	 * Increment credits_used after a successful consensus job.
	 *
	 * Create = 1 credit. Rescue = 2 credits.
	 * Silently ignores unknown modes (logs nothing — no bloat).
	 * Called by ConsensusPress_Async::process_job() AFTER successful draft creation.
	 *
	 * @param string $mode Job mode — 'create' | 'rescue'. Determines credit cost.
	 * @return void
	 */
	public function record_usage( string $mode ): void {
		$cost = self::CREDIT_COST[ $mode ] ?? 0;
		if ( 0 === $cost ) {
			return;
		}
		$usage                  = $this->get_stored_usage();
		$usage['credits_used']  = $usage['credits_used'] + $cost;
		$usage['last_updated']  = current_time( 'mysql' );
		update_option( self::OPTION_KEY, $usage );
	}

	/**
	 * Check whether current usage state allows another job of the given mode.
	 *
	 * Returns array{allowed: bool, credits_remaining: int, message: string}.
	 * Used as a soft gate in AJAX handlers BEFORE scheduling the job.
	 * Placement rule (D-05-05): AFTER security checks (nonce + cap), BEFORE business logic.
	 *
	 * @param string $mode Job mode — 'create' | 'rescue'. Default 'create'.
	 * @return array{allowed: bool, credits_remaining: int, message: string}
	 */
	public function check_quota( string $mode = 'create' ): array {
		$usage     = $this->get_stored_usage();
		$cost      = self::CREDIT_COST[ $mode ] ?? 1;
		$remaining = $usage['credits_limit'] - $usage['credits_used'];
		$allowed   = ( $remaining >= $cost );

		if ( $allowed ) {
			return array(
				'allowed'           => true,
				'credits_remaining' => $remaining,
				'message'           => '',
			);
		}

		$tier_label = self::TIERS[ $usage['tier'] ]['label'] ?? 'Free';
		return array(
			'allowed'           => false,
			'credits_remaining' => max( 0, $remaining ),
			'message'           => sprintf(
				/* translators: %1$s = tier name, %2$s = reset date */
				__(
					'Monthly limit reached on your %1$s plan. Credits reset on %2$s. Upgrade at seekrates-ai.com/pricing.',
					'consensuspress'
				),
				esc_html( $tier_label ),
				esc_html( date( 'M j, Y', strtotime( $usage['reset_date'] ) ) )
			),
		);
	}

	/**
	 * Called by process_job() when API returns HTTP 402 (usage_exceeded).
	 *
	 * Updates stored usage to reflect server truth.
	 * Accepts full $result array from ConsensusPress_API::query().
	 * Extracts tier/used/limit if present in error_data (future-proofs against
	 * API returning extra 402 fields). At minimum, sets credits_used = credits_limit
	 * to signal the quota is exhausted locally.
	 *
	 * @param array{success: bool, data: null, error: array{code: string, message: string}, http_status: int} $api_result Full result from ConsensusPress_API::query() on 402 response.
	 * @return void
	 */
	public function handle_quota_exceeded( array $api_result ): void {  // @hal001-suppress bare_array_param — PHPDoc shape deferred post-submission  // HAL-SUPPRESS: bare_array_param — PHPDoc shape deferred post-submission
		$usage = $this->get_stored_usage();

		// Update with any server-provided tier/limit data.
		// The API class currently extracts only code + message from error bodies.
		// This block is future-proof: if richer 402 data is ever passed through,
		// it will be used automatically.
		$error = $api_result['error'] ?? array();
		if ( isset( $error['tier'] ) && array_key_exists( $error['tier'], self::TIERS ) ) {
			$usage['tier']          = $error['tier'];
			$usage['credits_limit'] = self::TIERS[ $error['tier'] ]['limit'];
		}
		if ( isset( $error['used'] ) && is_int( $error['used'] ) ) {
			$usage['credits_used'] = $error['used'];
		} else {
			// Minimum: mark as exhausted by setting used = limit.
			$usage['credits_used'] = $usage['credits_limit'];
		}

		$usage['last_updated'] = current_time( 'mysql' );
		update_option( self::OPTION_KEY, $usage );
	}

	/**
	 * Return current usage state. Auto-resets if past reset_date.
	 *
	 * Safe to call frequently — no side effects beyond auto-reset.
	 *
	 * @return array{credits_used: int, credits_limit: int, tier: string, reset_date: string, credits_remaining: int, last_updated: string}
	 */
	public function get_usage(): array {
		$usage                      = $this->get_stored_usage();
		$usage['credits_remaining'] = max( 0, $usage['credits_limit'] - $usage['credits_used'] );
		return $usage;
	}

	/**
	 * Admin notice hook. Renders quota warning ONLY on ConsensusPress admin pages.
	 *
	 * Warning at >= 80% used. Error (red) at >= 100% used.
	 * Uses standard WordPress notice classes — no custom JS needed.
	 * Guard: get_current_screen() — no output outside CP pages (D-05-06).
	 *
	 * @return void
	 */
	public function render_quota_notice(): void {
		// Only render on ConsensusPress admin pages.
		$screen = get_current_screen();
		if ( ! $screen || false === strpos( $screen->id, 'consensuspress' ) ) {
			return;
		}

		$usage = $this->get_stored_usage();
		$used  = $usage['credits_used'];
		$limit = $usage['credits_limit'];
		$ratio = ( $limit > 0 ) ? ( $used / $limit ) : 0.0;

		if ( $ratio < self::WARN_THRESHOLD ) {
			return; // Under 80% — no notice needed.
		}

		$tier_label = self::TIERS[ $usage['tier'] ]['label'] ?? 'Free';
		$reset      = date( 'M j, Y', strtotime( $usage['reset_date'] ) );

		if ( $ratio >= 1.0 ) {
			$class   = 'notice notice-error';
			$message = sprintf(
				/* translators: %1$s = tier name, %2$d = credits used, %3$d = credits limit, %4$s = reset date, %5$s = upgrade URL */
				__( '<strong>ConsensusPress:</strong> Monthly limit reached on your %1$s plan (%2$d/%3$d credits). Credits reset on %4$s. <a href="%5$s">Upgrade now.</a>', 'consensuspress' ),
				esc_html( $tier_label ),
				(int) $used,
				(int) $limit,
				esc_html( $reset ),
				esc_url( 'https://seekrates-ai.com/pricing' )
			);
		} else {
			$class   = 'notice notice-warning';
			$message = sprintf(
				/* translators: %1$d = credits used, %2$d = credits limit, %3$s = tier name, %4$s = reset date */
				__( '<strong>ConsensusPress:</strong> %1$d of %2$d monthly credits used on your %3$s plan. Credits reset on %4$s.', 'consensuspress' ),
				(int) $used,
				(int) $limit,
				esc_html( $tier_label ),
				esc_html( $reset )
			);
		}

		printf( '<div class="%s"><p>%s</p></div>', esc_attr( $class ), wp_kses_post( $message ) );
	}

	// -------------------------------------------------------------------------
	// Private Methods
	// -------------------------------------------------------------------------

	/**
	 * Read stored usage from wp_options. Apply defaults for fresh install.
	 *
	 * Calls auto_reset_if_needed() before returning — ensures billing period
	 * is always current on every read (auto-reset-on-read pattern, D-05-01).
	 *
	 * @return array{credits_used: int, credits_limit: int, tier: string, reset_date: string, last_updated: string}
	 */
	private function get_stored_usage(): array {
		$defaults = array(
			'credits_used'  => 0,
			'credits_limit' => self::TIERS['free']['limit'],
			'tier'          => 'free',
			'reset_date'    => $this->next_reset_date(),
			'last_updated'  => current_time( 'mysql' ),
		);

		$stored = get_option( self::OPTION_KEY, array() );
		if ( ! is_array( $stored ) ) {
			$stored = array();
		}

		$usage = array_merge( $defaults, $stored );
		return $this->auto_reset_if_needed( $usage );
	}

	/**
	 * If current time has passed reset_date, zero credits_used and advance reset_date.
	 *
	 * Saves to wp_options if reset was applied.
	 *
	 * @param array{credits_used: int, credits_limit: int, tier: string, reset_date: string, last_updated: string} $usage Current usage state.
	 * @return array{credits_used: int, credits_limit: int, tier: string, reset_date: string, last_updated: string}
	 */
	private function auto_reset_if_needed( array $usage ): array {
		if ( time() > strtotime( $usage['reset_date'] ) ) {
			$usage['credits_used'] = 0;
			$usage['reset_date']   = $this->next_reset_date();
			$usage['last_updated'] = current_time( 'mysql' );
			update_option( self::OPTION_KEY, $usage );
		}
		return $usage;
	}

	/**
	 * Return first day of next calendar month as MySQL datetime string.
	 *
	 * Example: called 2026-03-15 → returns '2026-04-01 00:00:00'.
	 *
	 * @return string MySQL datetime — 'YYYY-MM-01 00:00:00'
	 */
	private function next_reset_date(): string {
		return date( 'Y-m-01 00:00:00', strtotime( 'first day of next month' ) );
	}
}