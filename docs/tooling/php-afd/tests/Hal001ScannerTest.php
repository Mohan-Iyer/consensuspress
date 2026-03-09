<?php
/**
 * =============================================================================
 * test-hal001-scanner.php — Tests for hal001_php_scanner.php
 * =============================================================================
 * file:     docs/tooling/php-afd/tests/test-hal001-scanner.php
 * covers:   hal001_php_scanner.php
 * scenarios: HAL-T-001 through HAL-T-007
 * =============================================================================
 */

declare(strict_types=1);

// Load the scanner functions.
require_once __DIR__ . '/../hal001_php_scanner.php';

use PHPUnit\Framework\TestCase;

class Hal001ScannerTest extends TestCase {

    private string $fixtures_dir;

    protected function setUp(): void {
        $this->fixtures_dir = __DIR__ . '/fixtures';
    }

    // -----------------------------------------------------------------------
    // HAL-T-001: Clean file returns zero violations
    // -----------------------------------------------------------------------
    public function test_clean_file_returns_zero_violations(): void {
        $result = scan_file( $this->fixtures_dir . '/clean_file.php' );

        $this->assertSame( 0, $result['real_count'], 'Clean file should have 0 real violations' );
        $this->assertSame( 0, $result['suppressed_count'], 'Clean file should have 0 suppressed violations' );
    }

    // -----------------------------------------------------------------------
    // HAL-T-002: Bare array return type detected
    // -----------------------------------------------------------------------
    public function test_bare_array_return_detected(): void {
        $result = scan_file( $this->fixtures_dir . '/type_erasure_violations.php' );

        $bare_array_returns = array_filter(
            $result['violations'],
            fn( $v ) => $v['type'] === VIO_BARE_ARRAY_RETURN && ! $v['suppressed']
        );

        $this->assertGreaterThanOrEqual(
            1,
            count( $bare_array_returns ),
            'Should detect at least 1 bare_array_return violation'
        );

        // Verify get_settings is flagged.
        $messages = array_map( fn( $v ) => $v['message'], $bare_array_returns );
        $has_get_settings = false;
        foreach ( $messages as $msg ) {
            if ( str_contains( $msg, 'get_settings' ) ) {
                $has_get_settings = true;
                break;
            }
        }
        $this->assertTrue( $has_get_settings, 'get_settings() should be flagged for bare array return' );
    }

    // -----------------------------------------------------------------------
    // HAL-T-003: Mixed type detected
    // -----------------------------------------------------------------------
    public function test_mixed_type_detected(): void {
        $result = scan_file( $this->fixtures_dir . '/type_erasure_violations.php' );

        $mixed = array_filter(
            $result['violations'],
            fn( $v ) => $v['type'] === VIO_MIXED_TYPE && ! $v['suppressed']
        );

        $this->assertGreaterThanOrEqual(
            1,
            count( $mixed ),
            'Should detect at least 1 mixed_type violation'
        );
    }

    // -----------------------------------------------------------------------
    // HAL-T-004: Untyped parameter detected
    // -----------------------------------------------------------------------
    public function test_untyped_param_detected(): void {
        $result = scan_file( $this->fixtures_dir . '/type_erasure_violations.php' );

        $untyped = array_filter(
            $result['violations'],
            fn( $v ) => $v['type'] === VIO_UNTYPED_PARAM && ! $v['suppressed']
        );

        $this->assertGreaterThanOrEqual(
            1,
            count( $untyped ),
            'Should detect at least 1 untyped_param violation'
        );

        // Verify transform's $data is flagged.
        $messages = array_map( fn( $v ) => $v['message'], $untyped );
        $has_transform = false;
        foreach ( $messages as $msg ) {
            if ( str_contains( $msg, 'transform' ) && str_contains( $msg, '$data' ) ) {
                $has_transform = true;
                break;
            }
        }
        $this->assertTrue( $has_transform, 'transform() $data should be flagged as untyped' );
    }

    // -----------------------------------------------------------------------
    // HAL-T-005: json_decode without validation detected
    // -----------------------------------------------------------------------
    public function test_json_decode_unvalidated_detected(): void {
        $result = scan_file( $this->fixtures_dir . '/type_erasure_violations.php' );

        $json_violations = array_filter(
            $result['violations'],
            fn( $v ) => $v['type'] === VIO_JSON_DECODE_UNVALIDATED && ! $v['suppressed']
        );

        $this->assertGreaterThanOrEqual(
            1,
            count( $json_violations ),
            'Should detect at least 1 json_decode_unvalidated violation'
        );
    }

    // -----------------------------------------------------------------------
    // HAL-T-006: Suppressed violations reported but don't trigger failure
    // -----------------------------------------------------------------------
    public function test_suppressed_violations_dont_fail(): void {
        $result = scan_file( $this->fixtures_dir . '/type_erasure_suppressed.php' );

        $this->assertGreaterThan(
            0,
            $result['suppressed_count'],
            'Suppressed file should have suppressed_count > 0'
        );

        $this->assertSame(
            0,
            $result['real_count'],
            'Suppressed file should have real_count = 0'
        );
    }

    // -----------------------------------------------------------------------
    // HAL-T-007: PHPDoc array shape prevents violation
    // -----------------------------------------------------------------------
    public function test_phpdoc_shape_prevents_violation(): void {
        $result = scan_file( $this->fixtures_dir . '/clean_file.php' );

        // query_api returns array but has @return array{...} — should NOT be flagged.
        $bare_returns = array_filter(
            $result['violations'],
            fn( $v ) => $v['type'] === VIO_BARE_ARRAY_RETURN && ! $v['suppressed']
        );

        // create_draft also has @param array{...} — should NOT be flagged.
        $bare_params = array_filter(
            $result['violations'],
            fn( $v ) => $v['type'] === VIO_BARE_ARRAY_PARAM && ! $v['suppressed']
        );

        $this->assertCount(
            0,
            $bare_returns,
            'Functions with @return array{...} should NOT be flagged'
        );
        $this->assertCount(
            0,
            $bare_params,
            'Params with @param array{...} should NOT be flagged'
        );
    }

    // -----------------------------------------------------------------------
    // Additional: Verify total violation count on violations file
    // -----------------------------------------------------------------------
    public function test_violations_file_total_count(): void {
        $result = scan_file( $this->fixtures_dir . '/type_erasure_violations.php' );

        // At minimum: bare_array_return (x2), mixed_type (x1), untyped_param (x1),
        // json_decode_unvalidated (x1) = 5
        $this->assertGreaterThanOrEqual(
            4,
            $result['real_count'],
            'Violations file should have at least 4 real violations'
        );
    }
}