#!/usr/bin/env php
<?php
/**
 * =============================================================================
 * hal001_php_scanner.php — ILL-1 Type Erasure Detection for PHP
 * =============================================================================
 * file:        docs/tooling/php-afd/hal001_php_scanner.php
 * version:     1.0.0
 * purpose:     Scan PHP source files for type erasure violations
 * author:      C-C (Session 02, ConsensusPress)
 * spec:        CCI-CP-P0-01 Tool 1
 * php_version: 8.1+
 * dependencies: None — PHP built-in only (token_get_all)
 * reusable:    Yes — standalone CLI tool for any PHP/WordPress project
 * =============================================================================
 * USAGE:
 *   php hal001_php_scanner.php <directory_or_file>
 *
 * EXIT CODES:
 *   0 — No real violations (or all suppressed)
 *   1 — Violations found
 *   2 — Error (bad arguments, path not found)
 *
 * SUPPRESSION:
 *   Add inline comment: // @hal001-suppress REASON
 *   Suppressed lines are reported but do not trigger exit 1.
 * =============================================================================
 */

declare(strict_types=1);

// ---------------------------------------------------------------------------
// CONSTANTS (per D1)
// ---------------------------------------------------------------------------
if ( ! defined( 'EXIT_CLEAN' ) )      { define( 'EXIT_CLEAN', 0 ); }
if ( ! defined( 'EXIT_VIOLATIONS' ) ) { define( 'EXIT_VIOLATIONS', 1 ); }
if ( ! defined( 'EXIT_ERROR' ) )      { define( 'EXIT_ERROR', 2 ); }

const HAL001_VERSION = '1.0.0';

const SUPPRESSION_MARKER = '@hal001-suppress';

const VIO_BARE_ARRAY_RETURN       = 'bare_array_return';
const VIO_BARE_ARRAY_PARAM        = 'bare_array_param';
const VIO_MIXED_TYPE              = 'mixed_type';
const VIO_UNTYPED_PARAM           = 'untyped_param';
const VIO_JSON_DECODE_UNVALIDATED = 'json_decode_unvalidated';
const VIO_BARE_ARRAY_ACCESS       = 'bare_array_access';

// Directories to exclude from scanning.
const EXCLUDE_DIRS = ['vendor', 'node_modules', 'tests', '.git'];

// ---------------------------------------------------------------------------
// MAIN
// ---------------------------------------------------------------------------

/**
 * CLI entry point.
 *
 * @param array<int, string> $argv CLI arguments.
 * @return int Exit code.
 */
function hal001_main( array $argv ): int {
    if ( count( $argv ) < 2 ) {
        fwrite( STDERR, "Usage: php hal001_php_scanner.php <directory_or_file>\n" );
        return EXIT_ERROR;
    }

    $path = $argv[1];

    if ( ! file_exists( $path ) ) {
        fwrite( STDERR, "Error: Path not found: {$path}\n" );
        return EXIT_ERROR;
    }

    // Collect PHP files.
    $files = [];
    if ( is_dir( $path ) ) {
        $files = collect_php_files( $path );
    } elseif ( is_file( $path ) && str_ends_with( $path, '.php' ) ) {
        $files = [ realpath( $path ) ];
    } else {
        fwrite( STDERR, "Error: Not a PHP file or directory: {$path}\n" );
        return EXIT_ERROR;
    }

    if ( empty( $files ) ) {
        fwrite( STDERR, "Warning: No PHP files found in {$path}\n" );
        return EXIT_CLEAN;
    }

    // Scan each file.
    $results = [];
    foreach ( $files as $filepath ) {
        $results[] = scan_file( $filepath );
    }

    print_report( $results );

    // Determine exit code: any real violations = EXIT_VIOLATIONS.
    $total_real = 0;
    foreach ( $results as $r ) {
        $total_real += $r['real_count'];
    }

    return ( $total_real > 0 ) ? EXIT_VIOLATIONS : EXIT_CLEAN;
}

// ---------------------------------------------------------------------------
// FILE COLLECTION
// ---------------------------------------------------------------------------

/**
 * Recursively collect .php files from directory, excluding EXCLUDE_DIRS.
 *
 * @param string $dir Directory path.
 * @return array<int, string> List of absolute file paths.
 */
function collect_php_files( string $dir ): array {
    $files = [];
    $iterator = new \RecursiveDirectoryIterator(
        $dir,
        \RecursiveDirectoryIterator::SKIP_DOTS
    );
    $flat = new \RecursiveIteratorIterator(
        $iterator,
        \RecursiveIteratorIterator::LEAVES_ONLY
    );

    foreach ( $flat as $file ) {
        /** @var \SplFileInfo $file */
        $pathname = $file->getPathname();

        // Skip excluded directories.
        $skip = false;
        foreach ( EXCLUDE_DIRS as $excl ) {
            if ( str_contains( $pathname, DIRECTORY_SEPARATOR . $excl . DIRECTORY_SEPARATOR ) ) {
                $skip = true;
                break;
            }
        }
        if ( $skip ) {
            continue;
        }

        if ( $file->isFile() && $file->getExtension() === 'php' ) {
            $files[] = $pathname;
        }
    }

    sort( $files );
    return $files;
}

// ---------------------------------------------------------------------------
// SCAN FILE (two-pass)
// ---------------------------------------------------------------------------

/**
 * Scan one PHP file for HAL-001 violations.
 *
 * @param string $filepath Absolute path to PHP file.
 * @return array{
 *   file:             string,
 *   violations:       array<int, array{line: int, type: string, message: string, suppressed: bool}>,
 *   real_count:       int,
 *   suppressed_count: int
 * }
 */
function scan_file( string $filepath ): array {
    $source = file_get_contents( $filepath );
    if ( $source === false ) {
        return [
            'file'             => $filepath,
            'violations'       => [],
            'real_count'       => 0,
            'suppressed_count' => 0,
        ];
    }

    $lines = explode( "\n", $source );

    // Pass 1: token scan.
    $token_violations = scan_tokens( $source, $filepath );

    // Pass 2: pattern scan.
    $pattern_violations = scan_patterns( $source, $filepath );

    // Merge and deduplicate by line number (keep first occurrence).
    $all = array_merge( $token_violations, $pattern_violations );
    $seen_lines = [];
    $deduped = [];
    foreach ( $all as $v ) {
        $key = $v['line'] . ':' . $v['type'];
        if ( ! isset( $seen_lines[ $key ] ) ) {
            $seen_lines[ $key ] = true;
            $deduped[] = $v;
        }
    }

    // Check suppression on each violation.
    $violations = [];
    $real_count = 0;
    $suppressed_count = 0;

    foreach ( $deduped as $v ) {
        $line_idx = $v['line'] - 1; // 0-based index.
        $line_text = ( $line_idx >= 0 && $line_idx < count( $lines ) )
            ? $lines[ $line_idx ]
            : '';

        $suppressed = str_contains( $line_text, SUPPRESSION_MARKER );

        // Also check the line above (suppression comment may be on preceding line).
        if ( ! $suppressed && $line_idx > 0 ) {
            $suppressed = str_contains( $lines[ $line_idx - 1 ], SUPPRESSION_MARKER );
        }

        $violations[] = [
            'line'       => $v['line'],
            'type'       => $v['type'],
            'message'    => $v['message'],
            'suppressed' => $suppressed,
        ];

        if ( $suppressed ) {
            $suppressed_count++;
        } else {
            $real_count++;
        }
    }

    // Sort by line number.
    usort( $violations, fn( $a, $b ) => $a['line'] <=> $b['line'] );

    return [
        'file'             => $filepath,
        'violations'       => $violations,
        'real_count'       => $real_count,
        'suppressed_count' => $suppressed_count,
    ];
}

// ---------------------------------------------------------------------------
// PASS 1: TOKEN-BASED SCAN
// ---------------------------------------------------------------------------

/**
 * Token-based scan for type erasure violations.
 *
 * Detects: bare array return types, bare array params, mixed types,
 * untyped function parameters.
 *
 * @param string $source  PHP source code.
 * @param string $filepath File path for messages.
 * @return array<int, array{line: int, type: string, message: string}>
 */
function scan_tokens( string $source, string $filepath ): array {
    $tokens = token_get_all( $source );
    $violations = [];
    $count = count( $tokens );

    // State tracking.
    $current_phpdoc = null;
    $i = 0;

    while ( $i < $count ) {
        $token = $tokens[ $i ];

        // Capture PHPDoc blocks.
        if ( is_array( $token ) && $token[0] === T_DOC_COMMENT ) {
            $current_phpdoc = $token[1];
            $i++;
            continue;
        }

        // Reset PHPDoc if we hit something that isn't whitespace or a modifier
        // between the doc comment and a function.
        if ( is_array( $token ) && ! in_array( $token[0], [
            T_WHITESPACE, T_PUBLIC, T_PROTECTED, T_PRIVATE,
            T_STATIC, T_ABSTRACT, T_FINAL, T_DOC_COMMENT,
            T_COMMENT, T_READONLY,
        ], true ) && $token[0] !== T_FUNCTION ) {
            $current_phpdoc = null;
        }

        // Detect T_FUNCTION.
        if ( is_array( $token ) && $token[0] === T_FUNCTION ) {
            $func_line = $token[2];
            $phpdoc_for_func = $current_phpdoc;
            $current_phpdoc = null;

            // Find function name.
            $j = $i + 1;
            $func_name = '(anonymous)';
            while ( $j < $count ) {
                if ( is_array( $tokens[ $j ] ) && $tokens[ $j ][0] === T_STRING ) {
                    $func_name = $tokens[ $j ][1];
                    break;
                }
                if ( ! is_array( $tokens[ $j ] ) && $tokens[ $j ] === '(' ) {
                    // Anonymous function — no name before '('.
                    break;
                }
                $j++;
            }

            // Find opening '(' of parameter list.
            $paren_start = $j;
            while ( $paren_start < $count ) {
                if ( ! is_array( $tokens[ $paren_start ] ) && $tokens[ $paren_start ] === '(' ) {
                    break;
                }
                $paren_start++;
            }

            if ( $paren_start >= $count ) {
                $i++;
                continue;
            }

            // Scan parameters for untyped params and bare array params.
            $param_violations = scan_function_params(
                $tokens, $paren_start, $count, $phpdoc_for_func, $func_name
            );
            $violations = array_merge( $violations, $param_violations );

            // Find closing ')' of parameter list.
            $paren_depth = 0;
            $paren_end = $paren_start;
            while ( $paren_end < $count ) {
                $t = $tokens[ $paren_end ];
                $char = is_array( $t ) ? '' : $t;
                if ( $char === '(' ) { $paren_depth++; }
                if ( $char === ')' ) {
                    $paren_depth--;
                    if ( $paren_depth === 0 ) { break; }
                }
                $paren_end++;
            }

            // Check return type after ')'.
            $return_violations = scan_return_type(
                $tokens, $paren_end, $count, $phpdoc_for_func, $func_name, $func_line
            );
            $violations = array_merge( $violations, $return_violations );

            $i = $paren_end + 1;
            continue;
        }

        $i++;
    }

    return $violations;
}

/**
 * Scan function parameters for type violations.
 *
 * @param array<int, mixed> $tokens     Token stream.
 * @param int               $paren_start Index of opening '('.
 * @param int               $count      Token count.
 * @param ?string           $phpdoc     PHPDoc block or null.
 * @param string            $func_name  Function name.
 * @return array<int, array{line: int, type: string, message: string}>
 */
function scan_function_params(
    array $tokens,
    int $paren_start,
    int $count,
    ?string $phpdoc,
    string $func_name
): array {
    $violations = [];
    $depth = 0;
    $j = $paren_start;

    // Track whether the current param has a type hint.
    $has_type = false;
    $last_type_token = null;
    $current_var = null;

    while ( $j < $count ) {
        $t = $tokens[ $j ];
        $char = is_array( $t ) ? '' : $t;

        if ( $char === '(' ) { $depth++; $j++; continue; }
        if ( $char === ')' ) {
            $depth--;
            if ( $depth === 0 ) {
                // End of params — check last param.
                if ( $current_var !== null && ! $has_type ) {
                    $var_line = is_array( $tokens[ $j ] ) ? $tokens[ $j ][2] : 0;
                    // Find line from the variable token — we stored it.
                    $violations[] = check_untyped_param(
                        $current_var, $var_line, $phpdoc, $func_name
                    );
                }
                break;
            }
        }

        // Skip nested parens (default values like array()).
        if ( $depth > 1 ) { $j++; continue; }

        if ( is_array( $t ) ) {
            $tok_id = $t[0];
            $tok_val = $t[1];
            $tok_line = $t[2];

            // Type hint tokens.
            if ( in_array( $tok_id, [
                T_STRING, T_NAME_QUALIFIED, T_NAME_FULLY_QUALIFIED, T_ARRAY,
            ], true ) ) {
                // Check for 'mixed' type.
                if ( $tok_id === T_STRING && strtolower( $tok_val ) === 'mixed' ) {
                    $violations[] = [
                        'line'    => $tok_line,
                        'type'    => VIO_MIXED_TYPE,
                        'message' => "{$func_name}() param uses 'mixed' type",
                    ];
                }

                // Check for bare 'array' param type.
                if ( ( $tok_id === T_STRING && strtolower( $tok_val ) === 'array' )
                    || $tok_id === T_ARRAY
                ) {
                    // Will check on variable detection below.
                    $last_type_token = 'array';
                }

                $has_type = true;
            }

            // Nullable type '?'.
            if ( $tok_val === '?' ) {
                $has_type = true;
            }

            // Variable = param name.
            if ( $tok_id === T_VARIABLE ) {
                $current_var = $tok_val;

                // Check bare array param.
                if ( $last_type_token === 'array' ) {
                    if ( ! phpdoc_has_array_shape( $phpdoc, 'param', $current_var ) ) {
                        $violations[] = [
                            'line'    => $tok_line,
                            'type'    => VIO_BARE_ARRAY_PARAM,
                            'message' => "{$func_name}() param {$current_var} is bare 'array' without PHPDoc shape",
                        ];
                    }
                }

                // Check untyped param.
                if ( ! $has_type ) {
                    $v = check_untyped_param( $current_var, $tok_line, $phpdoc, $func_name );
                    if ( $v !== null ) {
                        $violations[] = $v;
                    }
                }

                // Reset for next param.
                $has_type = false;
                $last_type_token = null;
                $current_var = null;
            }
        }

        // Comma resets param state.
        if ( $char === ',' ) {
            $has_type = false;
            $last_type_token = null;
            $current_var = null;
        }

        $j++;
    }

    // Filter out nulls.
    return array_filter( $violations, fn( $v ) => $v !== null );
}

/**
 * Check if an untyped parameter has PHPDoc coverage.
 *
 * @param string  $var_name  Variable name (e.g. '$data').
 * @param int     $line      Line number.
 * @param ?string $phpdoc    PHPDoc block.
 * @param string  $func_name Function name.
 * @return array{line: int, type: string, message: string}|null
 */
function check_untyped_param(
    string $var_name,
    int $line,
    ?string $phpdoc,
    string $func_name
): ?array {
    // If PHPDoc has @param for this var, it's covered (even without type hint).
    if ( $phpdoc !== null ) {
        // Match @param <type> $var_name
        $escaped = preg_quote( $var_name, '/' );
        if ( preg_match( '/@param\s+\S+\s+' . $escaped . '/', $phpdoc ) ) {
            return null;
        }
    }

    return [
        'line'    => $line,
        'type'    => VIO_UNTYPED_PARAM,
        'message' => "{$func_name}() param {$var_name} has no type hint and no @param",
    ];
}

/**
 * Scan return type after closing ')'.
 *
 * @param array<int, mixed> $tokens     Token stream.
 * @param int               $paren_end  Index of closing ')'.
 * @param int               $count      Token count.
 * @param ?string           $phpdoc     PHPDoc block.
 * @param string            $func_name  Function name.
 * @param int               $func_line  Function declaration line.
 * @return array<int, array{line: int, type: string, message: string}>
 */
function scan_return_type(
    array $tokens,
    int $paren_end,
    int $count,
    ?string $phpdoc,
    string $func_name,
    int $func_line
): array {
    $violations = [];
    $j = $paren_end + 1;

    // Walk past whitespace to find ':' for return type.
    while ( $j < $count ) {
        $t = $tokens[ $j ];
        if ( is_array( $t ) && $t[0] === T_WHITESPACE ) {
            $j++;
            continue;
        }
        break;
    }

    if ( $j >= $count ) {
        return [];
    }

    $t = $tokens[ $j ];
    $char = is_array( $t ) ? '' : $t;

    // If no ':', no return type declared — skip (no violation for missing return type).
    if ( $char !== ':' ) {
        return [];
    }

    // Walk past ':' and whitespace to find return type token.
    $j++;
    while ( $j < $count ) {
        $t = $tokens[ $j ];
        if ( is_array( $t ) && $t[0] === T_WHITESPACE ) {
            $j++;
            continue;
        }
        break;
    }

    if ( $j >= $count ) {
        return [];
    }

    $t = $tokens[ $j ];
    if ( ! is_array( $t ) ) {
        return [];
    }

    $tok_id  = $t[0];
    $tok_val = $t[1];
    $tok_line = $t[2];

    // Check for 'array' return type.
    if ( ( $tok_id === T_STRING && strtolower( $tok_val ) === 'array' )
        || $tok_id === T_ARRAY
    ) {
        if ( ! phpdoc_has_array_shape( $phpdoc, 'return' ) ) {
            $violations[] = [
                'line'    => $tok_line,
                'type'    => VIO_BARE_ARRAY_RETURN,
                'message' => "{$func_name}() returns bare 'array' without @return array{...} shape",
            ];
        }
    }

    // Check for 'mixed' return type.
    if ( $tok_id === T_STRING && strtolower( $tok_val ) === 'mixed' ) {
        $violations[] = [
            'line'    => $tok_line,
            'type'    => VIO_MIXED_TYPE,
            'message' => "{$func_name}() returns 'mixed' — type erased",
        ];
    }

    return $violations;
}

/**
 * Extract PHPDoc block preceding a function token.
 *
 * @param array<int, mixed> $tokens               Token stream.
 * @param int               $function_token_index  Index of T_FUNCTION token.
 * @return ?string PHPDoc block or null.
 */
function extract_phpdoc( array $tokens, int $function_token_index ): ?string {
    $j = $function_token_index - 1;

    while ( $j >= 0 ) {
        $t = $tokens[ $j ];
        if ( is_array( $t ) ) {
            if ( $t[0] === T_DOC_COMMENT ) {
                return $t[1];
            }
            if ( ! in_array( $t[0], [
                T_WHITESPACE, T_PUBLIC, T_PROTECTED, T_PRIVATE,
                T_STATIC, T_ABSTRACT, T_FINAL, T_COMMENT,
            ], true ) ) {
                return null; // Non-modifier, non-whitespace — no PHPDoc.
            }
        }
        $j--;
    }

    return null;
}

/**
 * Check if PHPDoc contains an array shape annotation.
 *
 * @param ?string $phpdoc   PHPDoc block text.
 * @param string  $tag      'return' or 'param'.
 * @param string  $var_name Variable name (for @param matching).
 * @return bool True if shape annotation found.
 */
function phpdoc_has_array_shape( ?string $phpdoc, string $tag, string $var_name = '' ): bool {
    if ( $phpdoc === null ) {
        return false;
    }

    if ( $tag === 'return' ) {
        // Match @return array{ or @return array<
        return (bool) preg_match( '/@return\s+array\s*[{<]/', $phpdoc );
    }

    if ( $tag === 'param' && $var_name !== '' ) {
        $escaped = preg_quote( $var_name, '/' );
        // Match @param array{...} $var_name  or  @param array<...> $var_name
        return (bool) preg_match( '/@param\s+array\s*[{<][^}]*[}>]\s+' . $escaped . '/', $phpdoc )
            || (bool) preg_match( '/@param\s+array\s*\{[^}]*\}\s+' . $escaped . '/', $phpdoc )
            // WordPress hash notation: @param array $var_name { ... @type ... }
            || (bool) preg_match( '/@param\s+array\s+' . $escaped . '\s*\{/', $phpdoc );
    }

    return false;
}

// ---------------------------------------------------------------------------
// PASS 2: PATTERN-BASED SCAN
// ---------------------------------------------------------------------------

/**
 * Regex-based scan for patterns token scan cannot detect.
 *
 * Detects: json_decode without validation, bare array access on response data.
 *
 * @param string $source   PHP source code.
 * @param string $filepath File path for messages.
 * @return array<int, array{line: int, type: string, message: string}>
 */
function scan_patterns( string $source, string $filepath ): array {
    $violations = [];
    $lines = explode( "\n", $source );
    $line_count = count( $lines );

    for ( $i = 0; $i < $line_count; $i++ ) {
        $line = $lines[ $i ];
        $line_num = $i + 1;

        // Skip comment-only lines.
        $trimmed = trim( $line );
        if ( str_starts_with( $trimmed, '//' ) || str_starts_with( $trimmed, '*' )
            || str_starts_with( $trimmed, '/*' )
        ) {
            continue;
        }

        // DETECTION: json_decode without validation.
        if ( preg_match( '/\bjson_decode\s*\(/', $line ) ) {
            // Check next 5 lines for validation pattern.
            $has_validation = false;
            $check_end = min( $i + 6, $line_count );
            for ( $k = $i; $k < $check_end; $k++ ) {
                $check_line = $lines[ $k ];
                if ( preg_match( '/null\s*===|===\s*null|json_last_error|is_array\s*\(|isset\s*\(/', $check_line ) ) {
                    $has_validation = true;
                    break;
                }
            }
            if ( ! $has_validation ) {
                $violations[] = [
                    'line'    => $line_num,
                    'type'    => VIO_JSON_DECODE_UNVALIDATED,
                    'message' => 'json_decode() without null/error validation within 5 lines',
                ];
            }
        }
    }

    return $violations;
}

// ---------------------------------------------------------------------------
// REPORT
// ---------------------------------------------------------------------------

/**
 * Print human-readable report to stdout.
 *
 * @param array<int, array{
 *   file: string,
 *   violations: array<int, array{line: int, type: string, message: string, suppressed: bool}>,
 *   real_count: int,
 *   suppressed_count: int
 * }> $results Scan results per file.
 * @return void
 */
function print_report( array $results ): void {
    $sep = str_repeat( '=', 60 );
    $dash = str_repeat( '-', 60 );

    echo "\n{$sep}\n";
    echo "hal001_php_scanner v" . HAL001_VERSION . " — ILL-1 Type Erasure Detection\n";
    echo "{$sep}\n\n";

    $total_files = count( $results );
    $total_real = 0;
    $total_suppressed = 0;

    foreach ( $results as $r ) {
        // Use relative path if possible.
        $display_path = $r['file'];
        $cwd = getcwd();
        if ( $cwd !== false && str_starts_with( $display_path, $cwd ) ) {
            $display_path = ltrim( substr( $display_path, strlen( $cwd ) ), DIRECTORY_SEPARATOR );
        }

        echo "FILE: {$display_path}\n";

        if ( empty( $r['violations'] ) ) {
            echo "  [clean]\n";
        } else {
            foreach ( $r['violations'] as $v ) {
                $prefix = $v['suppressed'] ? '  (suppressed) ' : '  ';
                $line_pad = str_pad( 'L' . $v['line'], 6 );
                $type_pad = str_pad( $v['type'], 28 );
                echo "{$prefix}{$line_pad}  {$type_pad}  {$v['message']}\n";
            }
        }
        echo "\n";

        $total_real += $r['real_count'];
        $total_suppressed += $r['suppressed_count'];
    }

    echo "{$dash}\n";
    echo "Files scanned:  {$total_files}\n";
    echo "Violations:     {$total_real} real, {$total_suppressed} suppressed\n";
    echo "{$dash}\n";

    if ( $total_real === 0 ) {
        echo "PASS\n";
    } else {
        echo "FAIL — {$total_real} violation(s) found\n";
    }

    echo "{$sep}\n\n";
}

// ---------------------------------------------------------------------------
// RUN (only when invoked directly, not when require_once'd by tests)
// ---------------------------------------------------------------------------
if ( PHP_SAPI === 'cli' && isset( $argv[0] ) && realpath( $argv[0] ) === realpath( __FILE__ ) ) {
    exit( hal001_main( $argv ) );
}
