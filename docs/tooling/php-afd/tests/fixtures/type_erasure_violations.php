<?php
/**
 * Fixture: type_erasure_violations.php — Known HAL-001 violations.
 * file: docs/tooling/php-afd/tests/fixtures/type_erasure_violations.php
 *
 * DO NOT ADD @hal001-suppress — these must be detected.
 *
 * Expected violations:
 *   L19: bare_array_return (get_settings returns bare array)
 *   L24: mixed_type (process_data has mixed param)
 *   L29: untyped_param (transform has untyped $data)
 *   L34: json_decode_unvalidated (parse_response no null check)
 *   L34: bare_array_return (parse_response returns bare array)
 */
if ( ! defined( 'ABSPATH' ) ) { exit; }

// VIO_BARE_ARRAY_RETURN — returns array, no PHPDoc shape.
function get_settings(): array {
    return array( 'key' => 'value', 'timeout' => 120 );
}

// VIO_MIXED_TYPE — mixed parameter type.
function process_data( mixed $input ): string {
    return (string) $input;
}

// VIO_UNTYPED_PARAM — no type hint, no @param.
function transform( $data ) {
    return $data;
}

// VIO_JSON_DECODE_UNVALIDATED + VIO_BARE_ARRAY_RETURN
function parse_response( string $body ): array {
    $data = json_decode( $body, true );
    return $data['result'];
}