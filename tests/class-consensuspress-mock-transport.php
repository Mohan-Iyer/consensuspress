<?php
/**
 * DNA Header
 *
 * File:         tests/class-consensuspress-mock-transport.php
 * Version:      1.0.0
 * Purpose:      Mock HTTP transport for PHPUnit tests — returns fixture JSON
 * Author:       C-C (Session 02, Sprint 1)
 * Spec:         sprint1_d1_d7.yaml D1 mock_transport
 * PHP Version:  7.4+
 * Dependencies: interface-consensuspress-transport.php
 * Reusable:     Yes — all test files using ConsensusPress_API
 *
 * @package ConsensusPress
 */

/**
 * Class ConsensusPress_Mock_Transport
 *
 * Injects a canned JSON response into ConsensusPress_API for tests.
 * Implements ConsensusPress_Transport_Interface.
 */
class ConsensusPress_Mock_Transport implements ConsensusPress_Transport_Interface {

	/** @var string */
	private string $response_json;

	/** @var int */
	private int $http_status;

	/**
	 * @param string $response_json Fixture JSON string to return.
	 * @param int    $http_status   HTTP status code (default 200).
	 */
	public function __construct( string $response_json, int $http_status = 200 ) {
		$this->response_json = $response_json;
		$this->http_status   = $http_status;
	}

	/**
	 * Return the preset fixture response.
	 *
	 * @param string $url  Endpoint URL (ignored in mock).
	 * @param array  $args Request arguments (ignored in mock).
	 * @return array{response: array{code: int}, body: string}
	 */
	public function post( string $url, array $args ): array {
		return array(
			'response' => array( 'code' => $this->http_status ),
			'body'     => $this->response_json,
		);
	}
}
