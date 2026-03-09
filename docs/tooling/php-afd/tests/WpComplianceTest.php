<?php
/**
 * =============================================================================
 * test-wp-compliance.php — Tests for wp_compliance_scanner.php
 * =============================================================================
 * file:     docs/tooling/php-afd/tests/test-wp-compliance.php
 * covers:   wp_compliance_scanner.php
 * scenarios: WP-T-001 through WP-T-005
 * =============================================================================
 */

declare(strict_types=1);

// Load the scanner functions.
require_once __DIR__ . '/../wp_compliance_scanner.php';

use PHPUnit\Framework\TestCase;

class WpComplianceTest extends TestCase {

    private string $fixtures_dir;

    protected function setUp(): void {
        $this->fixtures_dir = __DIR__ . '/fixtures';
    }

    // -----------------------------------------------------------------------
    // WP-T-001: Clean plugin file returns zero violations
    // -----------------------------------------------------------------------
    public function test_clean_plugin_zero_violations(): void {
        $result = scan_wp_compliance( $this->fixtures_dir . '/wp_clean_plugin.php' );

        $this->assertSame(
            0,
            $result['count'],
            'Clean plugin file should have 0 violations. Got: ' . $this->format_violations( $result['violations'] )
        );
    }

    // -----------------------------------------------------------------------
    // WP-T-002: Unescaped output detected (HAL-WP-002)
    // -----------------------------------------------------------------------
    public function test_unescaped_output_detected(): void {
        $result = scan_wp_compliance( $this->fixtures_dir . '/wp_violations.php' );

        $wp002 = array_filter(
            $result['violations'],
            fn( $v ) => $v['code'] === WP_UNESCAPED_OUTPUT
        );

        $this->assertGreaterThanOrEqual(
            1,
            count( $wp002 ),
            'Should detect at least 1 HAL-WP-002 (unescaped output) violation'
        );
    }

    // -----------------------------------------------------------------------
    // WP-T-003: Unsanitized input detected (HAL-WP-001)
    // -----------------------------------------------------------------------
    public function test_unsanitized_input_detected(): void {
        $result = scan_wp_compliance( $this->fixtures_dir . '/wp_violations.php' );

        $wp001 = array_filter(
            $result['violations'],
            fn( $v ) => $v['code'] === WP_UNSANITIZED_INPUT
        );

        $this->assertGreaterThanOrEqual(
            1,
            count( $wp001 ),
            'Should detect at least 1 HAL-WP-001 (unsanitized input) violation'
        );
    }

    // -----------------------------------------------------------------------
    // WP-T-004: Missing nonce on AJAX handler (HAL-WP-003)
    // -----------------------------------------------------------------------
    public function test_missing_nonce_detected(): void {
        $result = scan_wp_compliance( $this->fixtures_dir . '/wp_violations.php' );

        $wp003 = array_filter(
            $result['violations'],
            fn( $v ) => $v['code'] === WP_MISSING_NONCE
        );

        $this->assertGreaterThanOrEqual(
            1,
            count( $wp003 ),
            'Should detect at least 1 HAL-WP-003 (missing nonce) violation'
        );
    }

    // -----------------------------------------------------------------------
    // WP-T-005: Missing ABSPATH check detected (HAL-WP-006)
    // -----------------------------------------------------------------------
    public function test_missing_abspath_detected(): void {
        $result = scan_wp_compliance( $this->fixtures_dir . '/wp_violations.php' );

        $wp006 = array_filter(
            $result['violations'],
            fn( $v ) => $v['code'] === WP_NO_ABSPATH
        );

        $this->assertGreaterThanOrEqual(
            1,
            count( $wp006 ),
            'Should detect at least 1 HAL-WP-006 (missing ABSPATH) violation'
        );
    }

    // -----------------------------------------------------------------------
    // Additional: Violations file total count
    // -----------------------------------------------------------------------
    public function test_violations_file_total_count(): void {
        $result = scan_wp_compliance( $this->fixtures_dir . '/wp_violations.php' );

        // Expected: WP-006 (ABSPATH), WP-002 (echo), WP-001 ($_POST),
        // WP-003 (nonce), WP-004 (capability) = 5 minimum
        $this->assertGreaterThanOrEqual(
            4,
            $result['count'],
            'Violations file should have at least 4 WP compliance violations'
        );
    }

    // -----------------------------------------------------------------------
    // Additional: Clean file has ABSPATH check — not flagged
    // -----------------------------------------------------------------------
    public function test_clean_file_abspath_not_flagged(): void {
        $result = scan_wp_compliance( $this->fixtures_dir . '/wp_clean_plugin.php' );

        $wp006 = array_filter(
            $result['violations'],
            fn( $v ) => $v['code'] === WP_NO_ABSPATH
        );

        $this->assertCount(
            0,
            $wp006,
            'File with ABSPATH check should NOT be flagged for HAL-WP-006'
        );
    }

    /**
     * Helper: format violations for assertion messages.
     *
     * @param array<int, array{line: int, code: string, message: string}> $violations
     * @return string
     */
    private function format_violations( array $violations ): string {
        if ( empty( $violations ) ) {
            return '(none)';
        }
        $parts = [];
        foreach ( $violations as $v ) {
            $parts[] = "L{$v['line']} {$v['code']}: {$v['message']}";
        }
        return implode( '; ', $parts );
    }
}