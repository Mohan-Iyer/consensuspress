<?php
/**
 * =============================================================================
 * test-inventory.php — Tests for inventory_php.php
 * =============================================================================
 * file:     docs/tooling/php-afd/tests/test-inventory.php
 * covers:   inventory_php.php
 * scenarios: INV-T-001 through INV-T-005
 * =============================================================================
 */

declare(strict_types=1);

// Load the inventory functions.
require_once __DIR__ . '/../inventory_php.php';

use PHPUnit\Framework\TestCase;

class InventoryTest extends TestCase {

    private string $fixture_path;

    protected function setUp(): void {
        $this->fixture_path = __DIR__ . '/fixtures/inventory_sample.php';
    }

    // -----------------------------------------------------------------------
    // INV-T-001: Correct function count
    // -----------------------------------------------------------------------
    public function test_correct_function_count(): void {
        $inventory = inventory_file( $this->fixture_path );

        $this->assertSame(
            6,
            $inventory['function_count'],
            'inventory_sample.php should have exactly 6 callables'
        );
    }

    // -----------------------------------------------------------------------
    // INV-T-002: Class methods have correct visibility and class name
    // -----------------------------------------------------------------------
    public function test_class_methods_have_correct_attributes(): void {
        $inventory = inventory_file( $this->fixture_path );
        $functions = $inventory['functions'];

        // Build lookup by name.
        $by_name = [];
        foreach ( $functions as $f ) {
            $by_name[ $f['name'] ] = $f;
        }

        // public_method
        $this->assertArrayHasKey( 'public_method', $by_name );
        $this->assertSame( 'method', $by_name['public_method']['kind'] );
        $this->assertSame( 'public', $by_name['public_method']['visibility'] );
        $this->assertSame( 'SampleClass', $by_name['public_method']['class'] );

        // protected_method
        $this->assertArrayHasKey( 'protected_method', $by_name );
        $this->assertSame( 'method', $by_name['protected_method']['kind'] );
        $this->assertSame( 'protected', $by_name['protected_method']['visibility'] );
        $this->assertSame( 'SampleClass', $by_name['protected_method']['class'] );

        // private_method
        $this->assertArrayHasKey( 'private_method', $by_name );
        $this->assertSame( 'method', $by_name['private_method']['kind'] );
        $this->assertSame( 'private', $by_name['private_method']['visibility'] );
        $this->assertSame( 'SampleClass', $by_name['private_method']['class'] );
    }

    // -----------------------------------------------------------------------
    // INV-T-003: Interface methods detected
    // -----------------------------------------------------------------------
    public function test_interface_methods_detected(): void {
        $inventory = inventory_file( $this->fixture_path );
        $functions = $inventory['functions'];

        $iface_methods = array_filter(
            $functions,
            fn( $f ) => $f['kind'] === 'interface_method'
        );

        $this->assertCount( 1, $iface_methods, 'Should detect 1 interface method' );

        $method = array_values( $iface_methods )[0];
        $this->assertSame( 'interface_method', $method['name'] );
        $this->assertSame( 'SampleInterface', $method['interface'] );
    }

    // -----------------------------------------------------------------------
    // INV-T-004: Signature hash is stable
    // -----------------------------------------------------------------------
    public function test_signature_hash_stable(): void {
        $inv1 = inventory_file( $this->fixture_path );
        $inv2 = inventory_file( $this->fixture_path );

        $this->assertSame(
            count( $inv1['functions'] ),
            count( $inv2['functions'] ),
            'Two runs should produce same function count'
        );

        for ( $i = 0; $i < count( $inv1['functions'] ); $i++ ) {
            $this->assertSame(
                $inv1['functions'][ $i ]['signature_hash'],
                $inv2['functions'][ $i ]['signature_hash'],
                "Signature hash for {$inv1['functions'][$i]['name']} should be stable"
            );
        }
    }

    // -----------------------------------------------------------------------
    // INV-T-005: SHA256 of source file is correct
    // -----------------------------------------------------------------------
    public function test_sha256_matches_file_contents(): void {
        $inventory = inventory_file( $this->fixture_path );

        $expected_hash = hash( 'sha256', file_get_contents( $this->fixture_path ) );

        $this->assertSame(
            $expected_hash,
            $inventory['inventory_sha256'],
            'inventory_sha256 should match SHA256 of file contents'
        );
    }

    // -----------------------------------------------------------------------
    // Additional: Global functions have correct attributes
    // -----------------------------------------------------------------------
    public function test_global_functions_correct(): void {
        $inventory = inventory_file( $this->fixture_path );

        $by_name = [];
        foreach ( $inventory['functions'] as $f ) {
            $by_name[ $f['name'] ] = $f;
        }

        $this->assertArrayHasKey( 'global_function_one', $by_name );
        $this->assertSame( 'function', $by_name['global_function_one']['kind'] );
        $this->assertSame( 'global', $by_name['global_function_one']['visibility'] );
        $this->assertSame( '', $by_name['global_function_one']['class'] );

        $this->assertArrayHasKey( 'global_function_two', $by_name );
        $this->assertSame( 'function', $by_name['global_function_two']['kind'] );
    }

    // -----------------------------------------------------------------------
    // Additional: must_preserve defaults to true for all entries
    // -----------------------------------------------------------------------
    public function test_must_preserve_defaults_true(): void {
        $inventory = inventory_file( $this->fixture_path );

        foreach ( $inventory['functions'] as $f ) {
            $this->assertTrue(
                $f['must_preserve'],
                "{$f['name']} must_preserve should default to true"
            );
        }
    }
}