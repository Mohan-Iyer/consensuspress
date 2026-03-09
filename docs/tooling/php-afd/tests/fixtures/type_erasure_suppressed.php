<?php
/**
 * Fixture: type_erasure_suppressed.php — Violations with suppression markers.
 * file: docs/tooling/php-afd/tests/fixtures/type_erasure_suppressed.php
 *
 * Expected: suppressed_count > 0, real_count = 0, exit code 0
 */
if ( ! defined( 'ABSPATH' ) ) { exit; }

// @hal001-suppress WP core returns mixed
function get_wp_option( string $key ): mixed {
    return get_option( $key );
}

// @hal001-suppress Legacy API returns unstructured array
function get_legacy_data(): array {
    return array( 'old' => 'format' );
}

/** @return array{id: int, name: string} */
function get_clean_data(): array {
    return array( 'id' => 1, 'name' => 'test' );
}