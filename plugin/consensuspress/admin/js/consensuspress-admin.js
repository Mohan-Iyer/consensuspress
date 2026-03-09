/**
 * DNA Header
 * File:         admin/js/consensuspress-admin.js
 * Version:      1.0.0
 * Purpose:      Settings page: Test Connection AJAX handler
 * Author:       C-C (Session 02, Sprint 1)
 * Spec:         sprint1_d1_d7.yaml D1 admin_js_admin
 * Dependencies: jQuery (WordPress bundled), consensuspressAdmin (wp_localize_script)
 * Reusable:     No — settings page only
 */

( function ( $ ) {
	'use strict';

	/**
	 * Handle Test Connection button click.
	 * Reads api_key from the settings field, POSTs to admin-ajax.php,
	 * and displays success/error result inline.
	 */
	$( document ).on( 'click', '#consensuspress-test-connection', function () {
		var $button = $( this );
		var $result = $( '#consensuspress-test-result' );
		var apiKey  = $( '#consensuspress_api_key' ).val().trim();

		if ( ! apiKey ) {
			$result
				.removeClass( 'success' )
				.addClass( 'error consensuspress-test-connection-result' )
				.text( consensuspressAdmin.i18n.emptyKey )
				.show();
			return;
		}

		$button.prop( 'disabled', true );
		$result
			.removeClass( 'success error' )
			.addClass( 'consensuspress-test-connection-result' )
			.text( consensuspressAdmin.i18n.testing )
			.show();

		$.ajax( {
			url:     consensuspressAdmin.ajaxUrl,
			method:  'POST',
			timeout: 30000,
			data: {
				action:  'consensuspress_test_connection',
				nonce:   consensuspressAdmin.nonce,
				api_key: apiKey,
			},
			success: function ( response ) {
				if ( response.success ) {
					$result
						.removeClass( 'error' )
						.addClass( 'success' )
						.text( consensuspressAdmin.i18n.success );
				} else {
					var msg = ( response.data && response.data.message )
						? response.data.message
						: consensuspressAdmin.i18n.error;
					$result
						.removeClass( 'success' )
						.addClass( 'error' )
						.text( msg );
				}
			},
			error: function () {
				$result
					.removeClass( 'success' )
					.addClass( 'error' )
					.text( consensuspressAdmin.i18n.error );
			},
			complete: function () {
				$button.prop( 'disabled', false );
			},
		} );
	} );

} )( jQuery );
