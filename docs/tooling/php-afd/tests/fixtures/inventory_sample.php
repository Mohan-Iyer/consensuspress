<?php
/**
 * Fixture: inventory_sample.php — Known inventory: 6 callables.
 * file: docs/tooling/php-afd/tests/fixtures/inventory_sample.php
 *
 * Expected inventory:
 *   1. global_function_one     (function, global)
 *   2. global_function_two     (function, global)
 *   3. public_method           (method, public, SampleClass)
 *   4. protected_method        (method, protected, SampleClass)
 *   5. private_method          (method, private, SampleClass)
 *   6. interface_method        (interface_method, public, SampleInterface)
 */

function global_function_one(): void {
    // Empty function.
}

function global_function_two( string $arg ): string {
    return $arg;
}

class SampleClass {

    public function public_method(): int {
        return 1;
    }

    protected function protected_method(): void {
        // Protected.
    }

    private function private_method( string $x ): string {
        return $x;
    }
}

interface SampleInterface {

    public function interface_method(): array;
}