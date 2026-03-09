#!/usr/bin/env php
<?php
/**
 * =============================================================================
 * inventory_php.php — Function/Method Inventory for D0 Preservation Contracts
 * =============================================================================
 * file:        docs/tooling/php-afd/inventory_php.php
 * version:     1.0.0
 * purpose:     Machine-generate function/method inventory for CCI D0 section
 * author:      C-C (Session 02, ConsensusPress)
 * spec:        CCI-CP-P0-01 Tool 2
 * php_version: 8.1+
 * dependencies: None — PHP built-in only
 * reusable:    Yes — standalone CLI tool for any PHP project
 * =============================================================================
 * USAGE:
 *   php inventory_php.php <file.php>
 *
 * OUTPUT:
 *   YAML to stdout — pipe to file or paste into CCI D0 section.
 *
 * EXIT CODES:
 *   0 — Success (inventory printed)
 *   2 — Error (bad arguments, file not found, parse error)
 *
 * RULE: D0 must NEVER be populated manually. Always machine-generated.
 * =============================================================================
 */

declare(strict_types=1);

if ( ! defined( 'EXIT_CLEAN' ) ) { define( 'EXIT_CLEAN', 0 ); }
if ( ! defined( 'EXIT_ERROR' ) ) { define( 'EXIT_ERROR', 2 ); }

const INVENTORY_VERSION = '1.0.0';

// ---------------------------------------------------------------------------
// MAIN
// ---------------------------------------------------------------------------

/**
 * CLI entry point.
 *
 * @param array<int, string> $argv CLI arguments.
 * @return int Exit code.
 */
function inventory_main( array $argv ): int {
    if ( count( $argv ) < 2 ) {
        fwrite( STDERR, "Usage: php inventory_php.php <file.php>\n" );
        return EXIT_ERROR;
    }

    $filepath = $argv[1];

    if ( ! file_exists( $filepath ) ) {
        fwrite( STDERR, "Error: File not found: {$filepath}\n" );
        return EXIT_ERROR;
    }

    if ( ! is_file( $filepath ) ) {
        fwrite( STDERR, "Error: Not a file: {$filepath}\n" );
        return EXIT_ERROR;
    }

    try {
        $inventory = inventory_file( $filepath );
    } catch ( \RuntimeException $e ) {
        fwrite( STDERR, "Error: {$e->getMessage()}\n" );
        return EXIT_ERROR;
    }

    print_yaml( $inventory );
    return EXIT_CLEAN;
}

// ---------------------------------------------------------------------------
// INVENTORY
// ---------------------------------------------------------------------------

/**
 * Token-based function/method inventory of a PHP file.
 *
 * @param string $filepath Path to PHP file.
 * @return array{
 *   source_file:      string,
 *   inventory_date:    string,
 *   inventory_sha256:  string,
 *   function_count:    int,
 *   functions:         array<int, array{
 *     name:           string,
 *     line:           int,
 *     kind:           string,
 *     visibility:     string,
 *     class:          string,
 *     interface:      string,
 *     signature_hash: string,
 *     must_preserve:  bool
 *   }>
 * }
 * @throws \RuntimeException If file cannot be read or parsed.
 */
function inventory_file( string $filepath ): array {
    $source = file_get_contents( $filepath );
    if ( $source === false ) {
        throw new \RuntimeException( "Cannot read file: {$filepath}" );
    }

    $file_hash = hash( 'sha256', $source );
    $tokens = token_get_all( $source );
    $count = count( $tokens );

    $functions = [];

    // State tracking.
    $current_class     = '';
    $current_interface = '';
    $class_brace_depth = -1;      // Brace depth when class/interface was entered.
    $iface_brace_depth = -1;
    $brace_depth       = 0;
    $pending_visibility = 'public';
    $pending_static     = false;
    $pending_abstract   = false;

    $i = 0;
    while ( $i < $count ) {
        $t = $tokens[ $i ];

        // Track braces for class/interface scope.
        if ( ! is_array( $t ) ) {
            if ( $t === '{' ) {
                $brace_depth++;
            }
            if ( $t === '}' ) {
                $brace_depth--;
                // Exiting class scope?
                if ( $current_class !== '' && $brace_depth === $class_brace_depth ) {
                    $current_class = '';
                    $class_brace_depth = -1;
                }
                // Exiting interface scope?
                if ( $current_interface !== '' && $brace_depth === $iface_brace_depth ) {
                    $current_interface = '';
                    $iface_brace_depth = -1;
                }
            }
            $i++;
            continue;
        }

        $tok_id  = $t[0];
        $tok_val = $t[1];
        $tok_line = $t[2];

        // Track visibility modifiers.
        if ( $tok_id === T_PUBLIC )    { $pending_visibility = 'public';    $i++; continue; }
        if ( $tok_id === T_PROTECTED ) { $pending_visibility = 'protected'; $i++; continue; }
        if ( $tok_id === T_PRIVATE )   { $pending_visibility = 'private';   $i++; continue; }
        if ( $tok_id === T_STATIC )    { $pending_static = true;            $i++; continue; }
        if ( $tok_id === T_ABSTRACT )  { $pending_abstract = true;          $i++; continue; }

        // Detect class declaration.
        if ( $tok_id === T_CLASS ) {
            $j = $i + 1;
            while ( $j < $count ) {
                if ( is_array( $tokens[ $j ] ) && $tokens[ $j ][0] === T_STRING ) {
                    $current_class = $tokens[ $j ][1];
                    $class_brace_depth = $brace_depth;
                    break;
                }
                $j++;
            }
            $i = $j + 1;
            continue;
        }

        // Detect interface declaration.
        if ( $tok_id === T_INTERFACE ) {
            $j = $i + 1;
            while ( $j < $count ) {
                if ( is_array( $tokens[ $j ] ) && $tokens[ $j ][0] === T_STRING ) {
                    $current_interface = $tokens[ $j ][1];
                    $iface_brace_depth = $brace_depth;
                    break;
                }
                $j++;
            }
            $i = $j + 1;
            continue;
        }

        // Detect function/method.
        if ( $tok_id === T_FUNCTION ) {
            $func_line = $tok_line;

            // Find function name.
            $j = $i + 1;
            $func_name = null;
            while ( $j < $count ) {
                if ( is_array( $tokens[ $j ] ) && $tokens[ $j ][0] === T_STRING ) {
                    $func_name = $tokens[ $j ][1];
                    break;
                }
                // Anonymous function (closure) — skip.
                if ( ! is_array( $tokens[ $j ] ) && $tokens[ $j ] === '(' ) {
                    break;
                }
                $j++;
            }

            if ( $func_name === null ) {
                // Anonymous function — skip, not part of inventory.
                $pending_visibility = 'public';
                $pending_static = false;
                $pending_abstract = false;
                $i = $j + 1;
                continue;
            }

            // Capture parameter list.
            $params_str = capture_params( $tokens, $j, $count );

            // Capture return type.
            $return_type = capture_return_type( $tokens, $j, $count );

            // Determine kind.
            $kind = 'function';
            $class_name = '';
            $iface_name = '';

            if ( $current_class !== '' ) {
                $kind = 'method';
                $class_name = $current_class;
            } elseif ( $current_interface !== '' ) {
                $kind = 'interface_method';
                $iface_name = $current_interface;
            }

            $visibility = ( $kind === 'function' ) ? 'global' : $pending_visibility;

            // Build normalised signature and hash.
            $sig = normalize_signature( $visibility, $func_name, $params_str, $return_type );
            $sig_hash = substr( hash( 'sha256', $sig ), 0, 8 );

            $functions[] = [
                'name'           => $func_name,
                'line'           => $func_line,
                'kind'           => $kind,
                'visibility'     => $visibility,
                'class'          => $class_name,
                'interface'      => $iface_name,
                'signature_hash' => $sig_hash,
                'must_preserve'  => true,
            ];

            // Reset modifiers.
            $pending_visibility = 'public';
            $pending_static = false;
            $pending_abstract = false;

            $i = $j + 1;
            continue;
        }

        // Reset modifiers on non-modifier tokens.
        if ( ! in_array( $tok_id, [
            T_WHITESPACE, T_COMMENT, T_DOC_COMMENT,
            T_PUBLIC, T_PROTECTED, T_PRIVATE,
            T_STATIC, T_ABSTRACT, T_FINAL, T_READONLY,
        ], true ) ) {
            $pending_visibility = 'public';
            $pending_static = false;
            $pending_abstract = false;
        }

        $i++;
    }

    return [
        'source_file'      => $filepath,
        'inventory_date'   => date( 'c' ),
        'inventory_sha256' => $file_hash,
        'function_count'   => count( $functions ),
        'functions'        => $functions,
    ];
}

/**
 * Capture parameter list text from token stream.
 *
 * @param array<int, mixed> $tokens Token stream.
 * @param int               &$pos  Current position (updated to after closing ')').
 * @param int               $count Token count.
 * @return string Normalised parameter string.
 */
function capture_params( array $tokens, int &$pos, int $count ): string {
    // Find opening '('.
    while ( $pos < $count ) {
        if ( ! is_array( $tokens[ $pos ] ) && $tokens[ $pos ] === '(' ) {
            break;
        }
        $pos++;
    }

    if ( $pos >= $count ) {
        return '';
    }

    $depth = 0;
    $parts = [];
    $start = $pos;

    while ( $pos < $count ) {
        $t = $tokens[ $pos ];
        $char = is_array( $t ) ? '' : $t;

        if ( $char === '(' ) { $depth++; }
        if ( $char === ')' ) {
            $depth--;
            if ( $depth === 0 ) {
                break;
            }
        }

        if ( $depth >= 1 ) {
            $text = is_array( $t ) ? $t[1] : $t;
            $parts[] = $text;
        }

        $pos++;
    }

    return trim( implode( '', $parts ) );
}

/**
 * Capture return type after closing ')'.
 *
 * @param array<int, mixed> $tokens Token stream.
 * @param int               $pos    Position at or after closing ')'.
 * @param int               $count  Token count.
 * @return string Return type string or '' if none.
 */
function capture_return_type( array $tokens, int $pos, int $count ): string {
    // Find ')' first.
    $depth = 0;
    while ( $pos < $count ) {
        $t = $tokens[ $pos ];
        $char = is_array( $t ) ? '' : $t;
        if ( $char === '(' ) { $depth++; }
        if ( $char === ')' ) {
            $depth--;
            if ( $depth === 0 ) { break; }
        }
        $pos++;
    }

    $pos++; // Past ')'.

    // Look for ':'.
    while ( $pos < $count ) {
        $t = $tokens[ $pos ];
        if ( is_array( $t ) && $t[0] === T_WHITESPACE ) {
            $pos++;
            continue;
        }
        break;
    }

    if ( $pos >= $count ) {
        return '';
    }

    $t = $tokens[ $pos ];
    $char = is_array( $t ) ? '' : $t;

    if ( $char !== ':' ) {
        return '';
    }

    $pos++; // Past ':'.

    // Collect return type tokens until '{', ';', or end.
    $parts = [];
    while ( $pos < $count ) {
        $t = $tokens[ $pos ];
        $char = is_array( $t ) ? '' : $t;

        if ( $char === '{' || $char === ';' ) {
            break;
        }

        if ( is_array( $t ) && $t[0] !== T_WHITESPACE ) {
            $parts[] = $t[1];
        } elseif ( ! is_array( $t ) && $t === '?' ) {
            $parts[] = '?';
        }

        $pos++;
    }

    return implode( '', $parts );
}

/**
 * Build normalised signature string for hashing.
 *
 * @param string $visibility Visibility or 'global'.
 * @param string $name       Function name.
 * @param string $params     Parameter string.
 * @param string $return_type Return type string.
 * @return string Normalised signature.
 */
function normalize_signature(
    string $visibility,
    string $name,
    string $params,
    string $return_type
): string {
    // Strip extra whitespace from params.
    $params = preg_replace( '/\s+/', ' ', trim( $params ) );
    $return_type = trim( $return_type );

    $sig = "{$visibility} function {$name}({$params})";
    if ( $return_type !== '' ) {
        $sig .= ": {$return_type}";
    }

    return $sig;
}

// ---------------------------------------------------------------------------
// YAML OUTPUT
// ---------------------------------------------------------------------------

/**
 * Print inventory as YAML to stdout.
 *
 * @param array{
 *   source_file:      string,
 *   inventory_date:    string,
 *   inventory_sha256:  string,
 *   function_count:    int,
 *   functions:         array<int, array{
 *     name: string, line: int, kind: string, visibility: string,
 *     class: string, interface: string, signature_hash: string,
 *     must_preserve: bool
 *   }>
 * } $inventory Inventory data.
 * @return void
 */
function print_yaml( array $inventory ): void {
    $source = $inventory['source_file'];
    $date   = $inventory['inventory_date'];
    $hash   = $inventory['inventory_sha256'];
    $fcount = $inventory['function_count'];

    echo "# Generated by inventory_php.php v" . INVENTORY_VERSION . "\n";
    echo "# Source: {$source}\n";
    echo "# Date: {$date}\n";
    echo "source_file: \"{$source}\"\n";
    echo "inventory_date: \"{$date}\"\n";
    echo "inventory_sha256: \"{$hash}\"\n";
    echo "function_count: {$fcount}\n";
    echo "functions:\n";

    foreach ( $inventory['functions'] as $f ) {
        $preserve = $f['must_preserve'] ? 'true' : 'false';

        echo "  - name: \"{$f['name']}\"\n";
        echo "    line: {$f['line']}\n";
        echo "    kind: \"{$f['kind']}\"\n";
        echo "    visibility: \"{$f['visibility']}\"\n";
        echo "    class: \"{$f['class']}\"\n";
        echo "    interface: \"{$f['interface']}\"\n";
        echo "    signature_hash: \"{$f['signature_hash']}\"\n";
        echo "    must_preserve: {$preserve}\n";
    }
}

// ---------------------------------------------------------------------------
// RUN (only when invoked directly, not when require_once'd by tests)
// ---------------------------------------------------------------------------
if ( PHP_SAPI === 'cli' && isset( $argv[0] ) && realpath( $argv[0] ) === realpath( __FILE__ ) ) {
    exit( inventory_main( $argv ) );
}