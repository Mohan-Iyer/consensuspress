<?php
/**
 * Fixture: wp_violations.php — Known WP compliance violations.
 * file: docs/tooling/php-afd/tests/fixtures/wp_violations.php
 *
 * Expected violations:
 *   L1:  HAL-WP-006 — No defined-guard check
 *   L14: HAL-WP-002 — echo $name without escaping
 *   L19: HAL-WP-001 — $_POST['value'] without sanitization
 *   L25: HAL-WP-003 — wp_ajax_ handler without nonce check
 *   L25: HAL-WP-004 — wp_ajax_ handler without capability check
 */

// HAL-WP-002: Unescaped output
function show_name() {
    $name = get_option( 'my_name' );
    echo $name;
}

// HAL-WP-001: Unsanitized input
function save_data() {
    $value = $_POST['value'];
    update_option( 'my_value', $value );
}

// HAL-WP-003 + HAL-WP-004: Missing nonce and capability on AJAX handler
function ajax_handler() {
    $data = sanitize_text_field( wp_unslash( $_POST['data'] ) );
    wp_send_json_success( $data );
}
add_action( 'wp_ajax_my_action', 'ajax_handler' );