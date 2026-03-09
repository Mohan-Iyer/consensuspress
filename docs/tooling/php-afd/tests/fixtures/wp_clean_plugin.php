<?php
/**
 * Fixture: wp_clean_plugin.php — No WP compliance violations.
 * file: docs/tooling/php-afd/tests/fixtures/wp_clean_plugin.php
 */
if ( ! defined( 'ABSPATH' ) ) { exit; }

function render_page() {
    if ( ! current_user_can( 'manage_options' ) ) { return; }
    $name = get_option( 'my_name', '' );
    echo '<h1>' . esc_html( $name ) . '</h1>';
}

function handle_save() {
    check_ajax_referer( 'my_nonce', 'nonce' );
    if ( ! current_user_can( 'manage_options' ) ) {
        wp_send_json_error( 'Forbidden' );
    }
    $value = sanitize_text_field( wp_unslash( $_POST['value'] ) );
    update_option( 'my_value', $value );
    wp_send_json_success( 'Saved' );
}
add_action( 'wp_ajax_my_save', 'handle_save' );