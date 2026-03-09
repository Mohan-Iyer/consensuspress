/**
 * DNA Header
 * File:         admin/js/consensuspress-create.js
 * Version:      1.1.0
 * Purpose:      Create Post page: AJAX form submission + async job polling
 * Author:       C-C (Session 03, Sprint 2) | Modified: C-C (Session 05, Sprint 3)
 * Spec:         sprint2_d1_d7.yaml D1 admin_js_create | sprint3_d1_d7.yaml D1 admin_js_create_polling
 * Dependencies: jQuery (WordPress bundled), consensuspressCreate (wp_localize_script)
 * Reusable:     No — Create page only
 */

( function ( $ ) {
	'use strict';

	var POLL_INTERVAL_MS  = 5000;
	var MAX_POLL_ATTEMPTS = 24; // 24 × 5s = 120s timeout
	var pollTimer         = null;
	var pollAttempts      = 0;
	var currentJobId      = null;

	/**
	 * Show the status area with a message.
	 *
	 * @param {string} message  Text to display.
	 * @param {string} cssClass Optional CSS class: 'success' | 'error' | 'polling' | ''.
	 */
	function showStatus( message, cssClass ) {
		var $status  = $( '#consensuspress-create-status' );
		var $spinner = $status.find( '.spinner' );
		var $msg     = $( '#cp-status-message' );

		$msg.text( message );
		$status.show();

		if ( 'success' === cssClass || 'error' === cssClass ) {
			$spinner.removeClass( 'is-active' ).hide();
			$msg.removeClass( 'success error polling' ).addClass( cssClass );
		} else {
			$spinner.addClass( 'is-active' ).show();
			$msg.removeClass( 'success error' ).addClass( cssClass || '' );
		}
	}

	/**
	 * Stop the polling timer.
	 */
	function stopPolling() {
		if ( pollTimer ) {
			clearInterval( pollTimer );
			pollTimer = null;
		}
	}

	/**
	 * Poll for job status every POLL_INTERVAL_MS milliseconds.
	 *
	 * @param {string} jobId UUID returned from create_post handler.
	 */
	function startPolling( jobId ) {
		pollAttempts = 0;
		currentJobId = jobId;
		showStatus( consensuspressCreate.i18n.processing, 'polling' );

		pollTimer = setInterval( function () {
			pollAttempts++;

			if ( pollAttempts > MAX_POLL_ATTEMPTS ) {
				stopPolling();
				showStatus( consensuspressCreate.i18n.timeout || 'Timed out. Please check your drafts.', 'error' );
				$( '#cp-create-submit' ).prop( 'disabled', false );
				return;
			}

			$.ajax( {
				url:    consensuspressCreate.ajaxUrl,
				method: 'POST',
				data: {
					action:  'consensuspress_poll_job',
					nonce:   consensuspressCreate.pollNonce,
					job_id:  currentJobId,
				},
				success: function ( response ) {
					if ( ! response.success ) {
						stopPolling();
						showStatus( consensuspressCreate.i18n.error, 'error' );
						$( '#cp-create-submit' ).prop( 'disabled', false );
						return;
					}

					var status = response.data ? response.data.status : '';

					if ( 'complete' === status ) {
						stopPolling();
						showStatus( consensuspressCreate.i18n.success, 'success' );
						// Redirect to draft edit screen after short delay.
						if ( response.data.edit_url ) {
							setTimeout( function () {
								window.location.href = response.data.edit_url;
							}, 2000 );
						}
					} else if ( 'failed' === status ) {
						stopPolling();
						var errMsg = ( response.data.error && response.data.error.message )
							? response.data.error.message
							: consensuspressCreate.i18n.error;
						showStatus( errMsg, 'error' );
						$( '#cp-create-submit' ).prop( 'disabled', false );
					}
					// 'pending' | 'processing' — keep polling.
				},
				error: function () {
					// Network hiccup — keep polling, don't abort.
				},
			} );
		}, POLL_INTERVAL_MS );
	}

	/**
	 * Handle Generate Consensus Draft button click.
	 */
	$( document ).on( 'click', '#cp-create-submit', function () {
		var $button  = $( this );
		var topic    = $( '#cp-topic' ).val().trim();
		var context  = $( '#cp-context' ).val().trim();

		if ( topic.length < 10 ) {
			showStatus( consensuspressCreate.i18n.topicTooShort || 'Topic must be at least 10 characters.', 'error' );
			return;
		}

		$button.prop( 'disabled', true );
		showStatus( consensuspressCreate.i18n.submitting || 'Submitting…', '' );

		$.ajax( {
			url:     consensuspressCreate.ajaxUrl,
			method:  'POST',
			timeout: 30000,
			data: {
				action:  'consensuspress_create_post',
				nonce:   consensuspressCreate.nonce,
				topic:   topic,
				context: context,
			},
			success: function ( response ) {
				if ( response.success && response.data && response.data.job_id ) {
					startPolling( response.data.job_id );
				} else {
					var msg = ( response.data && response.data.message )
						? response.data.message
						: consensuspressCreate.i18n.error;
					showStatus( msg, 'error' );
					$button.prop( 'disabled', false );
				}
			},
			error: function () {
				showStatus( consensuspressCreate.i18n.error, 'error' );
				$button.prop( 'disabled', false );
			},
		} );
	} );

} )( jQuery );
