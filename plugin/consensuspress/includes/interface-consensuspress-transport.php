<?php
if ( ! defined( 'ABSPATH' ) ) { exit; }
/**
 * DNA Header
 *
 * File:         includes/interface-consensuspress-transport.php
 * Version:      1.0.1
 * Purpose:      Transport interface for HTTP layer injection (production vs test)
 * Author:       C-C (Session 02, Sprint 1)
 * Spec:         sprint1_d1_d7.yaml D1 transport_interface
 * PHP Version:  7.4+
 * Dependencies: none
 * Reusable:     Yes — all ConsensusPress_API consumers
 *
 * @package ConsensusPress
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Interface ConsensusPress_Transport_Interface
 *
 * Allows swapping HTTP transport in tests without changing business logic.
 */
interface ConsensusPress_Transport_Interface {

	/**
	 * Send a POST request.
	 *
	 * @param string $url  Endpoint URL.
	 * @param array  $args Request arguments (headers, body, timeout).
	 * @return array{response: array{code: int}, body: string}|WP_Error
	 */
	public function post( string $url, array $args );  // @hal001-suppress bare_array_param — PHPDoc shape deferred post-submission  // HAL-SUPPRESS: bare_array_param — PHPDoc shape deferred post-submission
}