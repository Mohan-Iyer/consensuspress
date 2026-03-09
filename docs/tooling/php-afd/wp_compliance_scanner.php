#!/usr/bin/env php
<?php
/**
 * =============================================================================
 * wp_compliance_scanner.php — WordPress.org Compliance Detection
 * =============================================================================
 * file:        docs/tooling/php-afd/wp_compliance_scanner.php
 * version:     1.0.0
 * purpose:     Detect WordPress.org submission compliance violations
 * author:      C-C (Session 02, ConsensusPress)
 * spec:        CCI-CP-P0-01 Tool 3
 * php_version: 8.1+
 * dependencies: None — PHP built-in only
 * reusable:    Yes — standalone CLI tool for any WordPress plugin
 * =============================================================================
 * USAGE:
 *   php wp_compliance_scanner.php <directory>
 *
 * EXIT CODES:
 *   0 — No violations
 *   1 — Violations found
 *   2 — Error (bad arguments, path not found)
 *
 * DETECTS: HAL-WP-001 through HAL-WP-008
 * =============================================================================
 */

declare(strict_types=1);

if ( ! defined( 'EXIT_CLEAN' ) )      { define( 'EXIT_CLEAN', 0 ); }
if ( ! defined( 'EXIT_VIOLATIONS' ) ) { define( 'EXIT_VIOLATIONS', 1 ); }
if ( ! defined( 'EXIT_ERROR' ) )      { define( 'EXIT_ERROR', 2 ); }

const WP_SCANNER_VERSION = '1.0.0';

// Violation codes (per D1).
const WP_UNSANITIZED_INPUT = 'HAL-WP-001';
const WP_UNESCAPED_OUTPUT  = 'HAL-WP-002';
const WP_MISSING_NONCE     = 'HAL-WP-003';
const WP_MISSING_CAPABILITY = 'HAL-WP-004';
const WP_INLINE_SCRIPT     = 'HAL-WP-005';
const WP_NO_ABSPATH        = 'HAL-WP-006';
const WP_HARDCODED_PATH    = 'HAL-WP-007';
const WP_UNPREPARED_SQL    = 'HAL-WP-008';

// Directories to exclude.
const WP_EXCLUDE_DIRS = ['vendor', 'node_modules', '.git', 'tests'];

// Sanitization functions — presence of these around superglobals is acceptable.
const SANITIZE_FUNCTIONS = [
    'sanitize_text_field', 'absint', 'intval', 'sanitize_email',
    'sanitize_file_name', 'sanitize_key', 'sanitize_title',
    'sanitize_url', 'sanitize_mime_type', 'sanitize_option',
    'sanitize_user', 'wp_kses', 'wp_kses_post', 'wp_kses_data',
    'wp_unslash', '(int)', '(float)', '(bool)',
    'array_map', 'map_deep',
];

// Escape functions — presence of these around output is acceptable.
const ESCAPE_FUNCTIONS = [
    'esc_html', 'esc_attr', 'esc_url', 'esc_textarea', 'esc_js',
    'esc_html__', 'esc_html_e', 'esc_attr__', 'esc_attr_e',
    'wp_kses', 'wp_kses_post', 'wp_kses_data',
    'absint', 'intval', '(int)', '(float)',
    'wp_json_encode', 'number_format', 'count',
];

// ---------------------------------------------------------------------------
// MAIN
// ---------------------------------------------------------------------------

/**
 * CLI entry point.
 *
 * @param array<int, string> $argv CLI arguments.
 * @return int Exit code.
 */
function wp_compliance_main( array $argv ): int {
    if ( count( $argv ) < 2 ) {
        fwrite( STDERR, "Usage: php wp_compliance_scanner.php <directory>\n" );
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
        $files = wp_collect_php_files( $path );
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

    $results = [];
    foreach ( $files as $filepath ) {
        $results[] = scan_wp_compliance( $filepath );
    }

    print_wp_report( $results );

    $total = 0;
    foreach ( $results as $r ) {
        $total += $r['count'];
    }

    return ( $total > 0 ) ? EXIT_VIOLATIONS : EXIT_CLEAN;
}

// ---------------------------------------------------------------------------
// FILE COLLECTION
// ---------------------------------------------------------------------------

/**
 * Recursively collect .php files, excluding WP_EXCLUDE_DIRS.
 *
 * @param string $dir Directory path.
 * @return array<int, string> Absolute file paths.
 */
function wp_collect_php_files( string $dir ): array {
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

        $skip = false;
        foreach ( WP_EXCLUDE_DIRS as $excl ) {
            if ( str_contains( $pathname, DIRECTORY_SEPARATOR . $excl . DIRECTORY_SEPARATOR ) ) {
                $skip = true;
                break;
            }
        }
        if ( $skip ) { continue; }

        if ( $file->isFile() && $file->getExtension() === 'php' ) {
            $files[] = $pathname;
        }
    }

    sort( $files );
    return $files;
}

// ---------------------------------------------------------------------------
// SCAN
// ---------------------------------------------------------------------------

/**
 * Scan one PHP file for WordPress.org compliance violations.
 *
 * @param string $filepath Absolute path to PHP file.
 * @return array{
 *   file:       string,
 *   violations: array<int, array{line: int, code: string, message: string}>,
 *   count:      int
 * }
 */
function scan_wp_compliance( string $filepath ): array {
    $violations = [];

    $source = file_get_contents( $filepath );
    if ( $source === false ) {
        return [ 'file' => $filepath, 'violations' => [], 'count' => 0 ];
    }

    $lines = explode( "\n", $source );
    $line_count = count( $lines );

    // Determine if this is a "template" file (in admin/views/ etc.).
    $is_template = str_contains( $filepath, 'views' . DIRECTORY_SEPARATOR )
        || str_contains( $filepath, 'templates' . DIRECTORY_SEPARATOR );

    // Determine if this is the main plugin file (skip ABSPATH check for it
    // only if it has a Plugin Name header).
    $is_main_plugin = str_contains( $source, 'Plugin Name:' );

    // -----------------------------------------------------------------------
    // HAL-WP-006: Missing ABSPATH check
    // -----------------------------------------------------------------------
    if ( ! $is_main_plugin ) {
        $has_abspath = false;
        // Check first 10 lines for ABSPATH guard.
        $check_lines = min( 30, $line_count );
        for ( $i = 0; $i < $check_lines; $i++ ) {
            $line = $lines[ $i ];
            if ( str_contains( $line, 'ABSPATH' ) || str_contains( $line, 'WP_UNINSTALL_PLUGIN' ) ) {
                $has_abspath = true;
                break;
            }
        }
        if ( ! $has_abspath ) {
            $violations[] = [
                'line'    => 1,
                'code'    => WP_NO_ABSPATH,
                'message' => 'No ABSPATH check — direct file access not prevented',
            ];
        }
    }

    // -----------------------------------------------------------------------
    // Line-by-line checks
    // -----------------------------------------------------------------------
    for ( $i = 0; $i < $line_count; $i++ ) {
        $line = $lines[ $i ];
        $line_num = $i + 1;
        $trimmed = trim( $line );

        // Skip comment lines.
        if ( str_starts_with( $trimmed, '//' )
            || str_starts_with( $trimmed, '*' )
            || str_starts_with( $trimmed, '/*' )
            || str_starts_with( $trimmed, '#' )
        ) {
            continue;
        }

        // --- HAL-WP-001: Unsanitized superglobal input ---
        if ( preg_match( '/\$_(POST|GET|REQUEST|SERVER|COOKIE)\s*\[/', $line ) ) {
            $is_sanitized = false;
            foreach ( SANITIZE_FUNCTIONS as $func ) {
                if ( str_contains( $line, $func ) ) {
                    $is_sanitized = true;
                    break;
                }
            }
            // Check if it's inside an isset/empty check (acceptable for checking existence).
            if ( preg_match( '/\b(isset|empty|array_key_exists)\s*\(/', $line ) ) {
                $is_sanitized = true;
            }
            if ( ! $is_sanitized ) {
                $violations[] = [
                    'line'    => $line_num,
                    'code'    => WP_UNSANITIZED_INPUT,
                    'message' => 'Superglobal access without sanitization function',
                ];
            }
        }

        // --- HAL-WP-002: Unescaped output ---
        // Match: echo $var, echo get_option(...), print $var
        if ( preg_match( '/\b(echo|print)\s+\$/', $line )
            || preg_match( '/\b(echo|print)\s+get_option\s*\(/', $line )
        ) {
            $is_escaped = false;
            foreach ( ESCAPE_FUNCTIONS as $func ) {
                if ( str_contains( $line, $func ) ) {
                    $is_escaped = true;
                    break;
                }
            }
            // Check for printf/sprintf with escape inside.
            if ( preg_match( '/\bprintf\s*\(/', $line ) ) {
                $is_escaped = true;
            }
            // Check for wp_send_json (self-escaping).
            if ( str_contains( $line, 'wp_send_json' ) ) {
                $is_escaped = true;
            }
            if ( ! $is_escaped ) {
                $violations[] = [
                    'line'    => $line_num,
                    'code'    => WP_UNESCAPED_OUTPUT,
                    'message' => 'Output of variable without escape function',
                ];
            }
        }

        // --- HAL-WP-005: Inline scripts/styles ---
        if ( preg_match( '/<script[\s>]/i', $line ) || preg_match( '/<style[\s>]/i', $line ) ) {
            // Exclude wp_add_inline_script/style and string literals.
            if ( ! str_contains( $line, 'wp_add_inline_script' )
                && ! str_contains( $line, 'wp_add_inline_style' )
                && ! str_contains( $line, 'type="application/ld+json"' )
                && ! preg_match( '/[\'"][^\'"]*<(?:script|style)/i', $line )
            ) {
                $violations[] = [
                    'line'    => $line_num,
                    'code'    => WP_INLINE_SCRIPT,
                    'message' => 'Inline <script> or <style> tag — use wp_enqueue_*',
                ];
            }
        }

        // --- HAL-WP-007: Hardcoded paths ---
        if ( preg_match( "/'[^']*\\/wp-(content|includes|admin)\\/[^']*'/", $line )
            || preg_match( '/"[^"]*\\/wp-(content|includes|admin)\\/[^"]*"/', $line )
        ) {
            // Exclude comments and PHPDoc.
            if ( ! str_contains( $line, '//' ) || strpos( $line, '//' ) > strpos( $line, 'wp-' ) ) {
                $violations[] = [
                    'line'    => $line_num,
                    'code'    => WP_HARDCODED_PATH,
                    'message' => 'Hardcoded WordPress path — use plugin_dir_path() / ABSPATH',
                ];
            }
        }

        // --- HAL-WP-008: Unprepared SQL ---
        if ( preg_match( '/\$wpdb->(query|get_results|get_var|get_row|get_col)\s*\(/', $line ) ) {
            if ( ! str_contains( $line, 'prepare' ) ) {
                $violations[] = [
                    'line'    => $line_num,
                    'code'    => WP_UNPREPARED_SQL,
                    'message' => '$wpdb query without $wpdb->prepare() — SQL injection risk',
                ];
            }
        }
    }

    // -----------------------------------------------------------------------
    // Multi-line checks: AJAX handlers
    // -----------------------------------------------------------------------
    $ajax_violations = scan_ajax_handlers( $source, $lines );
    $violations = array_merge( $violations, $ajax_violations );

    // Sort by line number.
    usort( $violations, fn( $a, $b ) => $a['line'] <=> $b['line'] );

    return [
        'file'       => $filepath,
        'violations' => $violations,
        'count'      => count( $violations ),
    ];
}

/**
 * Scan for AJAX handler compliance (nonce + capability checks).
 *
 * @param string             $source Full source code.
 * @param array<int, string> $lines  Lines array.
 * @return array<int, array{line: int, code: string, message: string}>
 */
function scan_ajax_handlers( string $source, array $lines ): array {
    $violations = [];

    // Find all wp_ajax_ registrations.
    // Pattern: add_action( 'wp_ajax_...' , callback )
    if ( ! preg_match_all(
        '/add_action\s*\(\s*[\'"]wp_ajax_\w+[\'"]\s*,\s*(.+?)\s*\)/s',
        $source,
        $matches,
        PREG_OFFSET_CAPTURE
    ) ) {
        return [];
    }

    foreach ( $matches[0] as $idx => $full_match_pair ) {
        // With PREG_OFFSET_CAPTURE each match is [string, offset].
        $offset = $full_match_pair[1];

        // Find line number of the add_action call.
        $line_num = substr_count( $source, "\n", 0, (int) $offset ) + 1;

        // Extract callback name/reference.
        $callback = trim( $matches[1][ $idx ][0] );

        // Try to find the callback function body in the source.
        $callback_body = find_callback_body( $source, $callback );

        if ( $callback_body === null ) {
            // Can't find body — skip (might be in another file).
            continue;
        }

        // HAL-WP-003: Check for nonce verification.
        if ( ! str_contains( $callback_body, 'check_ajax_referer' )
            && ! str_contains( $callback_body, 'wp_verify_nonce' )
        ) {
            $violations[] = [
                'line'    => $line_num,
                'code'    => WP_MISSING_NONCE,
                'message' => "AJAX handler missing nonce verification (callback: {$callback})",
            ];
        }

        // HAL-WP-004: Check for capability check.
        if ( ! str_contains( $callback_body, 'current_user_can' ) ) {
            $violations[] = [
                'line'    => $line_num,
                'code'    => WP_MISSING_CAPABILITY,
                'message' => "AJAX handler missing current_user_can() check (callback: {$callback})",
            ];
        }
    }

    return $violations;
}

/**
 * Find the body of a callback function in source code.
 *
 * Handles:
 *  - 'function_name' (string callback)
 *  - array( $this, 'method_name' ) / [ $this, 'method_name' ]
 *  - inline closure: function() { ... }
 *
 * @param string $source   Full source code.
 * @param string $callback Callback reference as extracted from add_action.
 * @return ?string Function body or null if not found.
 */
function find_callback_body( string $source, string $callback ): ?string {
    // Extract function/method name from various callback formats.
    $func_name = null;

    // Array callback: array( $this, 'method_name' ) or [$this, 'method_name']
    if ( preg_match( '/[\'"](\w+)[\'"]/', $callback, $m ) ) {
        $func_name = $m[1];
    }

    if ( $func_name === null ) {
        return null;
    }

    // Find: function func_name( ... ) { ... }
    $pattern = '/function\s+' . preg_quote( $func_name, '/' ) . '\s*\([^)]*\)\s*(?::\s*\S+\s*)?\{/';
    if ( ! preg_match( $pattern, $source, $m, PREG_OFFSET_CAPTURE ) ) {
        return null;
    }

    // With PREG_OFFSET_CAPTURE, $m[0] is [match_string, offset].
    $start = $m[0][1];

    // Find the opening '{' and extract until matching '}'.
    $brace_pos = strpos( $source, '{', (int) $start );
    if ( $brace_pos === false ) {
        return null;
    }

    $depth = 0;
    $len = strlen( $source );
    $body_start = $brace_pos;

    for ( $i = $brace_pos; $i < $len; $i++ ) {
        if ( $source[ $i ] === '{' ) { $depth++; }
        if ( $source[ $i ] === '}' ) {
            $depth--;
            if ( $depth === 0 ) {
                return substr( $source, $body_start, $i - $body_start + 1 );
            }
        }
    }

    return null;
}

// ---------------------------------------------------------------------------
// REPORT
// ---------------------------------------------------------------------------

/**
 * Print WordPress compliance report to stdout.
 *
 * @param array<int, array{
 *   file:       string,
 *   violations: array<int, array{line: int, code: string, message: string}>,
 *   count:      int
 * }> $results Scan results per file.
 * @return void
 */
function print_wp_report( array $results ): void {
    $sep = str_repeat( '=', 60 );
    $dash = str_repeat( '-', 60 );

    echo "\n{$sep}\n";
    echo "wp_compliance_scanner v" . WP_SCANNER_VERSION . " — WordPress.org Compliance\n";
    echo "{$sep}\n\n";

    $total_files = count( $results );
    $total_violations = 0;

    foreach ( $results as $r ) {
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
                $line_pad = str_pad( 'L' . $v['line'], 6 );
                $code_pad = str_pad( $v['code'], 12 );
                echo "  {$line_pad}  {$code_pad}  {$v['message']}\n";
            }
        }
        echo "\n";

        $total_violations += $r['count'];
    }

    echo "{$dash}\n";
    echo "Files scanned:  {$total_files}\n";
    echo "Violations:     {$total_violations}\n";
    echo "{$dash}\n";

    if ( $total_violations === 0 ) {
        echo "PASS\n";
    } else {
        echo "FAIL — {$total_violations} violation(s) found\n";
    }

    echo "{$sep}\n\n";
}

// ---------------------------------------------------------------------------
// RUN (only when invoked directly, not when require_once'd by tests)
// ---------------------------------------------------------------------------
if ( PHP_SAPI === 'cli' && isset( $argv[0] ) && realpath( $argv[0] ) === realpath( __FILE__ ) ) {
    exit( wp_compliance_main( $argv ) );
}
