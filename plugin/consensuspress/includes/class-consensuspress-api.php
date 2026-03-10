<?php
/**
 * DNA Header
 * File:         includes/class-consensuspress-api.php
 * Version:      1.1.1
 * Purpose:      HTTP client for Seekrates AI /api/v1/cp/consensus endpoint.
 *               Authenticates with Bearer token, handles T&C gate (HTTP 200
 *               with needs_tc_acceptance body), 401 auth failures, 402 quota
 *               exceeded, and general API errors.
 * Author:       C-C (Session 02, Sprint 1) | Modified: C-C (Session 09, Sprint 7)
 * Spec:         docs/sprint_7_D1_d7_instructions.yaml D4 api_php_patch
 * PHP Version:  7.4+
 * Dependencies: WordPress HTTP API (wp_remote_post, wp_remote_retrieve_*)
 * Reusable:     Yes — used by ConsensusPress_Async::process_job()
 * Changes v1.1.0:
 *   - Added T&C gate handler in query():
 *     HTTP 200 + body.status === 'needs_tc_acceptance' returns error array
 *     with code 'needs_tc_acceptance' and tc_url from response body.
 *     Positioned AFTER $parsed assignment, BEFORE existing 401 check.
 *     No other changes. Public method signature unchanged.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Seekrates AI API client.
 *
 * Sends consensus queries to POST /api/v1/consensus on the Railway server
 * and normalises all response and error shapes into a single array contract.
 *
 * @since 1.0.0
 */
class ConsensusPress_API {

	/**
	 * Production API endpoint.
	 *
	 * @var string
	 */
	const ENDPOINT = 'https://app.seekrates-ai.com/api/v1/cp/consensus';

	/**
	 * Request timeout in seconds (consensus queries take 30–60 s).
	 *
	 * @var int
	 */
	const TIMEOUT = 120;

	/**
	 * Maximum retry attempts on transient failure.
	 *
	 * @var int
	 */
	const MAX_RETRIES = 3;

	/**
	 * Bearer session token stored in wp_options.
	 *
	 * @var string
	 */
	private string $api_key;

	/**
	 * HTTP transport — null uses wp_remote_post(), injected for tests.
	 *
	 * @var ConsensusPress_Transport_Interface|null
	 */
	private $transport;

	// =========================================================================
	// CONSTRUCTOR
	// =========================================================================

	/**
	 * @param string                                  $api_key   Bearer session token.
	 * @param ConsensusPress_Transport_Interface|null $transport Optional transport for test injection.
	 */
	public function __construct( string $api_key, $transport = null ) {
		$this->api_key   = $api_key;
		$this->transport = $transport;
	}

	// =========================================================================
	// PUBLIC METHODS — ConsensusPress_Transport contract
	// =========================================================================

	/**
	 * Send a consensus query to the Seekrates AI endpoint.
	 *
	 * @param string $query   Topic or question (min 10 chars).
	 * @param string $mode    'create' or 'rescue'.
	 * @param string $context Optional additional context (rescue mode).
	 * @return array{
	 *     success:     bool,
	 *     data:        array|null,
	 *     error:       array{code: string, message: string, tc_url?: string}|null,
	 *     http_status: int
	 * }
	 */
	public function query( string $query, string $mode, string $context = '' ): array {
		$attempt  = 0;
		$last_err = null;

		while ( $attempt < self::MAX_RETRIES ) {
			$args = array(
				'timeout' => self::TIMEOUT,
				'headers' => array(
					'Content-Type'  => 'application/json',
					'Authorization' => 'Bearer ' . $this->api_key,
				),
				'body'    => wp_json_encode(
					array(
						'query'   => $query,
						'mode'    => $mode,
						'context' => $context,
					)
				),
			);
			$response = $this->transport
				? $this->transport->post( self::ENDPOINT, $args )
				: wp_remote_post( self::ENDPOINT, $args );

			if ( is_wp_error( $response ) ) {
				$last_err = array(
					'code'    => 'http_error',
					'message' => $response->get_error_message(),
				);
				$attempt++;
				sleep( (int) pow( 2, $attempt ) ); // Exponential backoff: 2s, 4s, 8s.
				continue;
			}

			$status = (int) wp_remote_retrieve_response_code( $response );
			$body   = wp_remote_retrieve_body( $response );
			$parsed = json_decode( $body, true );
			if ( ! is_array( $parsed ) ) {
				return array( 'success' => false, 'error' => array( 'message' => 'Invalid JSON response from server.' ) );
			}
			// -----------------------------------------------------------------
			// T&C gate — HTTP 200 with needs_tc_acceptance body.
			// Positioned BEFORE 401 check per spec D4 api_php_patch.
			// This is NOT an error HTTP status — server returns 200 intentionally.
			// -----------------------------------------------------------------
			if ( 200 === $status && isset( $parsed['status'] ) && 'needs_tc_acceptance' === $parsed['status'] ) {
				return array(
					'success'     => false,
					'data'        => null,
					'error'       => array(
						'code'    => 'needs_tc_acceptance',
						'message' => 'Please accept Seekrates AI Terms & Conditions to use ConsensusPress.',
						'tc_url'  => esc_url_raw( $parsed['tc_url'] ?? 'https://seekrates-ai.com/website-t-c/' ),
					),
					'http_status' => 200,
				);
			}

			// -----------------------------------------------------------------
			// Auth failure.
			// -----------------------------------------------------------------
			if ( 401 === $status ) {
				return array(
					'success'     => false,
					'data'        => null,
					'error'       => array(
						'code'    => 'auth_error',
						'message' => 'Invalid or expired API key. Please update in Settings.',
					),
					'http_status' => 401,
				);
			}

			// -----------------------------------------------------------------
			// Quota exceeded (tier limit).
			// -----------------------------------------------------------------
			if ( 402 === $status ) {
				return array(
					'success'     => false,
					'data'        => null,
					'error'       => array(
						'code'    => 'quota_exceeded',
						'message' => $parsed['detail'] ?? 'Query limit reached for your current plan.',
					),
					'http_status' => 402,
				);
			}

			// -----------------------------------------------------------------
			// Successful response.
			// -----------------------------------------------------------------
			if ( 200 === $status && ! empty( $parsed['success'] ) ) {
				return array(
					'success'     => true,
					'data'        => $parsed['data'] ?? null,
					'error'       => null,
					'http_status' => 200,
				);
			}

			// -----------------------------------------------------------------
			// Server error or malformed response — retry.
			// -----------------------------------------------------------------
			$last_err = array(
				'code'    => 'server_error',
				'message' => $parsed['error']['message'] ?? 'Unexpected response from Seekrates AI.',
			);
			$attempt++;

			if ( $attempt < self::MAX_RETRIES ) {
				sleep( (int) pow( 2, $attempt ) );
			}
		}

		// All retries exhausted.
		return array(
			'success'     => false,
			'data'        => null,
			'error'       => $last_err ?? array(
				'code'    => 'unknown_error',
				'message' => 'All retry attempts failed.',
			),
			'http_status' => 0,
		);
	}
}