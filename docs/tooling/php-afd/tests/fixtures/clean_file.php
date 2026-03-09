<?php
/**
 * Fixture: clean_file.php — No violations expected.
 * file: docs/tooling/php-afd/tests/fixtures/clean_file.php
 */
if ( ! defined( 'ABSPATH' ) ) { exit; }

/**
 * Query the API with proper PHPDoc array shape.
 *
 * @param string $topic Query topic.
 * @return array{
 *   success: bool,
 *   data:    array{score: float, provider: string}|null,
 *   error:   array{code: string, message: string}|null
 * }
 */
function query_api( string $topic ): array {
    $response = wp_remote_post( 'https://example.com/api', array( 'body' => $topic ) );
    if ( is_wp_error( $response ) ) {
        return array(
            'success' => false,
            'data'    => null,
            'error'   => array( 'code' => 'connection', 'message' => 'Failed' ),
        );
    }
    $body = wp_remote_retrieve_body( $response );
    $data = json_decode( $body, true );
    if ( null === $data ) {
        return array(
            'success' => false,
            'data'    => null,
            'error'   => array( 'code' => 'bad_json', 'message' => json_last_error_msg() ),
        );
    }
    return array(
        'success' => true,
        'data'    => $data,
        'error'   => null,
    );
}

/**
 * Create a draft post.
 *
 * @param array{title: string, content: string} $post_data Post fields.
 * @return int Post ID.
 */
function create_draft( array $post_data ): int {
    return wp_insert_post( $post_data );
}

/**
 * Format a score for display.
 *
 * @param float $score Score value.
 * @return string Formatted score.
 */
function format_score( float $score ): string {
    return number_format( $score, 1 ) . '%';
}