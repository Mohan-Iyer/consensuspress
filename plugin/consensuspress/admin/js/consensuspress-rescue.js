/**
 * DNA Header
 *
 * File:         admin/js/consensuspress-rescue.js
 * Version:      1.0.0
 * Purpose:      Rescue Mode async polling — submit, poll, redirect on complete
 * Author:       C-C (Session 07, Sprint 4)
 * Spec:         sprint_4_d1_d7_instructions.yaml D1 js_rescue
 * Dependencies: jQuery, consensuspressRescue (wp_localize_script)
 * Reusable:     No — rescue page only
 */

/* global consensuspressRescue */
( function ( $ ) {
	'use strict';

	var POLL_INTERVAL_MS  = 5000;
	var MAX_POLL_ATTEMPTS = 24; // 24 × 5s = 120s timeout
	var pollTimer         = null;
	var pollAttempts      = 0;
	var currentJobId      = null;

	/**
	 * Display a status message in the rescue status div.
	 *
	 * @param {string} message  Text to display.
	 * @param {string} cssClass Optional CSS class: 'polling'|'success'|'error'.
	 */
	function showStatus( message, cssClass ) {
		var $status = $( '#cp-rescue-status' );
		$status.removeClass( 'polling success error' );
		if ( cssClass ) {
			$status.addClass( cssClass );
		}
		$status.text( message ).show();
	}

	/**
	 * Poll for rescue job status.
	 */
	function poll() {
		if ( pollAttempts >= MAX_POLL_ATTEMPTS ) {
			clearTimeout( pollTimer );
			showStatus( consensuspressRescue.i18n.timeout, 'error' );
			$( '#cp-rescue-submit' ).prop( 'disabled', false );
			return;
		}

		pollAttempts++;

		$.post( consensuspressRescue.ajax_url, {
			action:  'consensuspress_poll_rescue_job',
			job_id:  currentJobId,
			nonce:   consensuspressRescue.pollNonce,
		} )
		.done( function ( response ) {
			if ( ! response.success ) {
				showStatus( consensuspressRescue.i18n.failed, 'error' );
				$( '#cp-rescue-submit' ).prop( 'disabled', false );
				return;
			}

			var status = response.data.status;

			if ( 'complete' === status ) {
				showStatus( consensuspressRescue.i18n.complete, 'success' );
				window.location.href = response.data.edit_url;
				return;
			}

			if ( 'failed' === status ) {
				showStatus( consensuspressRescue.i18n.failed, 'error' );
				$( '#cp-rescue-submit' ).prop( 'disabled', false );
				return;
			}

			// Still pending or processing — keep polling.
			pollTimer = setTimeout( poll, POLL_INTERVAL_MS );
		} )
		.fail( function () {
			showStatus( consensuspressRescue.i18n.failed, 'error' );
			$( '#cp-rescue-submit' ).prop( 'disabled', false );
		} );
	}

	/**
	 * Start the polling loop.
	 */
	function startPolling() {
		pollAttempts = 0;
		pollTimer    = setTimeout( poll, POLL_INTERVAL_MS );
	}

	/**
	 * Handle Rescue submit button click.
	 */
	function handleSubmit() {
		var postId = parseInt( $( '#cp-rescue-post-select' ).val(), 10 );

		if ( ! postId || postId <= 0 ) {
			showStatus( consensuspressRescue.i18n.select_post, 'error' );
			return;
		}

		$( '#cp-rescue-submit' ).prop( 'disabled', true );
		showStatus( consensuspressRescue.i18n.processing, 'polling' );

		$.post( consensuspressRescue.ajax_url, {
			action:  'consensuspress_rescue_post',
			post_id: postId,
			nonce:   consensuspressRescue.nonce,
		} )
		.done( function ( response ) {
			if ( ! response.success ) {
				showStatus( response.data.message || consensuspressRescue.i18n.failed, 'error' );
				$( '#cp-rescue-submit' ).prop( 'disabled', false );
				return;
			}
			currentJobId = response.data.job_id;
			startPolling();
		} )
		.fail( function () {
			showStatus( consensuspressRescue.i18n.failed, 'error' );
			$( '#cp-rescue-submit' ).prop( 'disabled', false );
		} );
	}

	// Bind on DOM ready.
	$( function () {
		$( '#cp-rescue-submit' ).on( 'click', handleSubmit );
	} );

} )( jQuery );
